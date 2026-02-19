<?php

namespace App\Services;

use App\Models\TenantSsoProvider;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class SamlAuthService
{
    private const SAML_ASSERTION_NS = 'urn:oasis:names:tc:SAML:2.0:assertion';

    private const SAML_PROTOCOL_NS = 'urn:oasis:names:tc:SAML:2.0:protocol';

    private const XMLDSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private const XMLENC_NS = 'http://www.w3.org/2001/04/xmlenc#';

    public function isEnabled(): bool
    {
        return (bool) config('wids.sso.saml.enabled', false);
    }

    public function beginAuthorization(TenantSsoProvider $provider, Request $request, string $acsUrl, string $metadataUrl): \Illuminate\Http\RedirectResponse
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        if (! $provider->is_enabled) {
            throw new \RuntimeException('SAML SSO is not enabled for this workspace.');
        }

        $idpSsoUrl = trim((string) ($provider->authorization_endpoint ?? ''));
        if ($idpSsoUrl === '') {
            throw new \RuntimeException('SAML IdP SSO URL is missing.');
        }

        $requestId = '_'.strtolower((string) Str::uuid());
        $relayState = Str::random(64);
        $issuedAt = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $spEntityId = $this->serviceProviderEntityId($provider, $metadataUrl);
        $nameIdFormat = $this->metadataString(
            $provider,
            'nameid_format',
            (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress')
        );

        $authnRequestXml = $this->buildAuthnRequestXml(
            id: $requestId,
            issueInstant: $issuedAt,
            destination: $idpSsoUrl,
            acsUrl: $acsUrl,
            spEntityId: $spEntityId,
            nameIdFormat: $nameIdFormat,
        );

        $encodedRequest = base64_encode(gzdeflate($authnRequestXml));
        $request->session()->put($this->sessionKey($provider), [
            'relay_state' => $relayState,
            'request_id' => $requestId,
            'issued_at' => now()->getTimestamp(),
        ]);

        return redirect()->away(
            $idpSsoUrl.'?'.http_build_query([
                'SAMLRequest' => $encodedRequest,
                'RelayState' => $relayState,
            ], '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * @return array{
     *   provider_subject: string,
     *   name_id: string,
     *   session_index: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>,
     *   request_id: string,
     *   relay_state: string,
     *   response_bytes: int
     * }
     */
    public function consumeAcsCallback(
        TenantSsoProvider $provider,
        Request $request,
        string $acsUrl,
        string $metadataUrl,
    ): array {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        $statePayload = $request->session()->pull($this->sessionKey($provider));
        if (! is_array($statePayload)) {
            throw new \RuntimeException('SAML relay state is missing or expired.');
        }

        $expectedRelayState = is_string($statePayload['relay_state'] ?? null) ? (string) $statePayload['relay_state'] : '';
        $receivedRelayState = trim((string) $request->input('RelayState', ''));

        if ($expectedRelayState === '' || $receivedRelayState === '' || ! hash_equals($expectedRelayState, $receivedRelayState)) {
            throw new \RuntimeException('SAML relay state validation failed.');
        }

        $requestId = is_string($statePayload['request_id'] ?? null) ? (string) $statePayload['request_id'] : '';
        if ($requestId === '') {
            throw new \RuntimeException('SAML request identifier is missing in session state.');
        }

        $responseEncoded = trim((string) $request->input('SAMLResponse', ''));
        if ($responseEncoded === '') {
            throw new \RuntimeException('SAMLResponse is missing.');
        }

        $responseXml = $this->decodeSamlResponseToXml($responseEncoded);
        $responseBytes = strlen($responseXml);
        $document = $this->loadXmlDocument($responseXml);
        $xpath = $this->createXPath($document);
        $originalResponseXml = (string) $document->saveXML();

        $responseElement = $this->firstElement($xpath, '/samlp:Response');
        if (! $responseElement) {
            throw new \RuntimeException('SAMLResponse does not contain a valid Response root element.');
        }

        [$xpath, $assertionElement] = $this->resolveAssertionForAcs($document, $xpath, $provider);
        if (! $assertionElement) {
            throw new \RuntimeException('SAMLResponse does not contain an Assertion.');
        }

        $this->validateSuccessStatus($xpath, $responseElement);
        $this->validateIssuer($xpath, $responseElement, $provider, $assertionElement);
        $this->validateInResponseTo($responseElement, $requestId);
        $this->validateResponseDestination($responseElement, $acsUrl);
        $this->validateSubjectConfirmationData($xpath, $assertionElement, $acsUrl, $requestId);
        $this->validateAssertionConditions($xpath, $assertionElement);
        $this->validateAudienceRestriction($xpath, $assertionElement, $provider, $metadataUrl);
        $this->validateResponseSignature($document, $provider, $responseElement, $assertionElement, $originalResponseXml);

        $parsedClaims = $this->extractClaims($xpath, $assertionElement);
        $sessionIndex = trim((string) $xpath->evaluate('string(saml:AuthnStatement/@SessionIndex)', $assertionElement));
        $profile = $this->mapClaims($provider, $parsedClaims['name_id'], $parsedClaims['claims']);

        return $profile + [
            'name_id' => $parsedClaims['name_id'],
            'session_index' => $sessionIndex,
            'request_id' => $requestId,
            'relay_state' => $receivedRelayState,
            'response_bytes' => $responseBytes,
        ];
    }

    public function canBeginSingleLogout(TenantSsoProvider $provider): bool
    {
        if (! $this->isEnabled() || ! $provider->is_enabled) {
            return false;
        }

        return trim((string) ($provider->token_endpoint ?? '')) !== '';
    }

    public function beginSingleLogout(
        TenantSsoProvider $provider,
        Request $request,
        string $sloUrl,
        string $metadataUrl,
        string $nameId,
        ?string $sessionIndex = null,
    ): \Illuminate\Http\RedirectResponse {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        if (! $provider->is_enabled) {
            throw new \RuntimeException('SAML SSO is not enabled for this workspace.');
        }

        $idpSloUrl = trim((string) ($provider->token_endpoint ?? ''));
        if ($idpSloUrl === '') {
            throw new \RuntimeException('SAML IdP SLO URL is missing.');
        }

        $nameId = trim($nameId);
        if ($nameId === '') {
            throw new \RuntimeException('SAML NameID for logout is missing.');
        }

        $requestId = '_'.strtolower((string) Str::uuid());
        $relayState = Str::random(64);
        $issuedAt = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $spEntityId = $this->serviceProviderEntityId($provider, $metadataUrl);
        $nameIdFormat = $this->metadataString(
            $provider,
            'nameid_format',
            (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress')
        );

        $logoutRequestXml = $this->buildLogoutRequestXml(
            id: $requestId,
            issueInstant: $issuedAt,
            destination: $idpSloUrl,
            spEntityId: $spEntityId,
            nameId: $nameId,
            nameIdFormat: $nameIdFormat,
            sessionIndex: $sessionIndex,
        );

        $encodedRequest = base64_encode(gzdeflate($logoutRequestXml));
        $request->session()->put($this->sloSessionKey($provider), [
            'relay_state' => $relayState,
            'request_id' => $requestId,
            'issued_at' => now()->getTimestamp(),
        ]);

        return redirect()->away(
            $idpSloUrl.'?'.http_build_query([
                'SAMLRequest' => $encodedRequest,
                'RelayState' => $relayState,
            ], '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * @return array{
     *   request_id: string,
     *   relay_state: string,
     *   response_bytes: int
     * }
     */
    public function consumeSingleLogoutCallback(
        TenantSsoProvider $provider,
        Request $request,
        string $sloUrl,
    ): array {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        $statePayload = $request->session()->pull($this->sloSessionKey($provider));
        if (! is_array($statePayload)) {
            throw new \RuntimeException('SAML logout relay state is missing or expired.');
        }

        $expectedRelayState = is_string($statePayload['relay_state'] ?? null) ? (string) $statePayload['relay_state'] : '';
        $receivedRelayState = trim((string) $request->input('RelayState', ''));

        if ($expectedRelayState === '' || $receivedRelayState === '' || ! hash_equals($expectedRelayState, $receivedRelayState)) {
            throw new \RuntimeException('SAML logout relay state validation failed.');
        }

        $requestId = is_string($statePayload['request_id'] ?? null) ? (string) $statePayload['request_id'] : '';
        if ($requestId === '') {
            throw new \RuntimeException('SAML logout request identifier is missing in session state.');
        }

        $responseEncoded = trim((string) $request->input('SAMLResponse', ''));
        if ($responseEncoded === '') {
            throw new \RuntimeException('SAML logout response is missing.');
        }

        $responseXml = $this->decodeSamlResponseToXml($responseEncoded);
        $responseBytes = strlen($responseXml);
        $document = $this->loadXmlDocument($responseXml);
        $xpath = $this->createXPath($document);

        $logoutResponseElement = $this->firstElement($xpath, '/samlp:LogoutResponse');
        if (! $logoutResponseElement) {
            throw new \RuntimeException('SAML logout response root element is invalid.');
        }

        $this->validateSuccessStatus($xpath, $logoutResponseElement);
        $this->validateIssuer($xpath, $logoutResponseElement, $provider);
        $this->validateLogoutInResponseTo($logoutResponseElement, $requestId);
        $this->validateLogoutDestination($logoutResponseElement, $sloUrl);
        $this->validateLogoutResponseSignature($document, $provider, $logoutResponseElement);

        return [
            'request_id' => $requestId,
            'relay_state' => $receivedRelayState,
            'response_bytes' => $responseBytes,
        ];
    }

    /**
     * @return array{
     *   request_id: string,
     *   relay_state: string,
     *   request_bytes: int,
     *   name_id: string,
     *   session_index: string
     * }
     */
    public function consumeIdpInitiatedLogoutRequest(
        TenantSsoProvider $provider,
        Request $request,
        string $sloUrl,
    ): array {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        if (! $provider->is_enabled) {
            throw new \RuntimeException('SAML SSO is not enabled for this workspace.');
        }

        $requestEncoded = trim((string) $request->input('SAMLRequest', ''));
        if ($requestEncoded === '') {
            throw new \RuntimeException('SAML logout request is missing.');
        }

        $logoutRequestXml = $this->decodeSamlRequestToXml($requestEncoded);
        $requestBytes = strlen($logoutRequestXml);
        $document = $this->loadXmlDocument($logoutRequestXml);
        $xpath = $this->createXPath($document);

        $logoutRequestElement = $this->firstElement($xpath, '/samlp:LogoutRequest');
        if (! $logoutRequestElement) {
            throw new \RuntimeException('SAML logout request root element is invalid.');
        }

        $requestId = trim((string) $logoutRequestElement->getAttribute('ID'));
        if ($requestId === '') {
            throw new \RuntimeException('SAML logout request identifier is missing.');
        }

        $this->validateIssuer($xpath, $logoutRequestElement, $provider);
        $this->validateLogoutRequestDestination($logoutRequestElement, $sloUrl);
        $this->validateLogoutRequestTimeConstraints($logoutRequestElement);
        $this->validateLogoutRequestSignature($document, $provider, $logoutRequestElement, $request);

        $nameId = trim((string) $xpath->evaluate('string(saml:NameID)', $logoutRequestElement));
        $sessionIndex = trim((string) $xpath->evaluate('string(samlp:SessionIndex)', $logoutRequestElement));
        $relayState = trim((string) $request->input('RelayState', ''));

        return [
            'request_id' => $requestId,
            'relay_state' => $relayState,
            'request_bytes' => $requestBytes,
            'name_id' => $nameId,
            'session_index' => $sessionIndex,
        ];
    }

    public function buildIdpInitiatedLogoutResponse(
        TenantSsoProvider $provider,
        string $metadataUrl,
        string $inResponseTo,
        ?string $relayState = null,
    ): \Illuminate\Http\RedirectResponse {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('SAML SSO is disabled.');
        }

        if (! $provider->is_enabled) {
            throw new \RuntimeException('SAML SSO is not enabled for this workspace.');
        }

        $idpSloUrl = trim((string) ($provider->token_endpoint ?? ''));
        if ($idpSloUrl === '') {
            throw new \RuntimeException('SAML IdP SLO URL is missing.');
        }

        $inResponseTo = trim($inResponseTo);
        if ($inResponseTo === '') {
            throw new \RuntimeException('SAML logout response InResponseTo is missing.');
        }

        $responseId = '_'.strtolower((string) Str::uuid());
        $issuedAt = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $spEntityId = $this->serviceProviderEntityId($provider, $metadataUrl);
        $logoutResponseXml = $this->buildLogoutResponseXml(
            id: $responseId,
            issueInstant: $issuedAt,
            destination: $idpSloUrl,
            inResponseTo: $inResponseTo,
            spEntityId: $spEntityId,
        );

        $encodedResponse = base64_encode(gzdeflate($logoutResponseXml));
        $query = [
            'SAMLResponse' => $encodedResponse,
        ];

        $relayState = is_string($relayState) ? trim($relayState) : '';
        if ($relayState !== '') {
            $query['RelayState'] = $relayState;
        }

        return redirect()->away(
            $idpSloUrl.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986)
        );
    }

    public function metadataXml(TenantSsoProvider $provider, string $acsUrl, string $metadataUrl, ?string $sloUrl = null): string
    {
        $entityId = $this->serviceProviderEntityId($provider, $metadataUrl);
        $nameIdFormat = $this->metadataString(
            $provider,
            'nameid_format',
            (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress')
        );
        $wantAssertionsSigned = $this->metadataBoolean(
            $provider,
            'enforce_signed_assertion',
            (bool) config('wids.sso.saml.require_signed_assertion_default', true)
        );
        $binding = trim((string) config('wids.sso.saml.acs_binding', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'));
        $binding = $binding !== '' ? $binding : 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
        $sloBinding = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
        $sloUrl = is_string($sloUrl) ? trim($sloUrl) : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="'.$this->xmlEscape($entityId).'">'
            .'<SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol" AuthnRequestsSigned="false" WantAssertionsSigned="'.($wantAssertionsSigned ? 'true' : 'false').'">'
            .'<NameIDFormat>'.$this->xmlEscape($nameIdFormat).'</NameIDFormat>'
            .($sloUrl !== ''
                ? '<SingleLogoutService Binding="'.$this->xmlEscape($sloBinding).'" Location="'.$this->xmlEscape($sloUrl).'" />'
                : '')
            .'<AssertionConsumerService Binding="'.$this->xmlEscape($binding).'" Location="'.$this->xmlEscape($acsUrl).'" index="0" isDefault="true" />'
            .'</SPSSODescriptor>'
            .'</EntityDescriptor>';
    }

    private function validateSuccessStatus(DOMXPath $xpath, DOMElement $responseElement): void
    {
        $statusCode = trim((string) $xpath->evaluate('string(samlp:Status/samlp:StatusCode/@Value)', $responseElement));
        if ($statusCode === '' || $statusCode !== 'urn:oasis:names:tc:SAML:2.0:status:Success') {
            throw new \RuntimeException('SAML response status is not success.');
        }
    }

    private function validateIssuer(
        DOMXPath $xpath,
        DOMElement $responseElement,
        TenantSsoProvider $provider,
        ?DOMElement $assertionElement = null,
    ): void {
        $expectedIssuer = trim((string) ($provider->issuer_url ?? ''));
        if ($expectedIssuer === '') {
            return;
        }

        $issuer = trim((string) $xpath->evaluate('string(saml:Issuer)', $responseElement));

        if ($issuer === '') {
            if (! $assertionElement instanceof DOMElement) {
                $assertionElement = $this->firstElement($xpath, '/samlp:Response/saml:Assertion');
            }

            $issuer = $assertionElement instanceof DOMElement
                ? trim((string) $xpath->evaluate('string(saml:Issuer)', $assertionElement))
                : '';
        }

        if ($issuer === '') {
            throw new \RuntimeException('SAML issuer is missing.');
        }

        if (! hash_equals($expectedIssuer, $issuer)) {
            throw new \RuntimeException('SAML issuer does not match configured IdP entity ID.');
        }
    }

    private function validateInResponseTo(DOMElement $responseElement, string $expectedRequestId): void
    {
        $inResponseTo = trim((string) $responseElement->getAttribute('InResponseTo'));

        if ($inResponseTo === '' || ! hash_equals($expectedRequestId, $inResponseTo)) {
            throw new \RuntimeException('SAML InResponseTo validation failed.');
        }
    }

    private function validateResponseDestination(DOMElement $responseElement, string $expectedAcsUrl): void
    {
        if (! (bool) config('wids.sso.saml.require_destination', true)) {
            return;
        }

        $destination = trim((string) $responseElement->getAttribute('Destination'));
        if ($destination === '') {
            throw new \RuntimeException('SAML response destination is missing.');
        }

        if (! hash_equals($expectedAcsUrl, $destination)) {
            throw new \RuntimeException('SAML response destination does not match ACS URL.');
        }
    }

    private function validateLogoutInResponseTo(DOMElement $logoutResponseElement, string $expectedRequestId): void
    {
        $inResponseTo = trim((string) $logoutResponseElement->getAttribute('InResponseTo'));

        if ($inResponseTo === '' || ! hash_equals($expectedRequestId, $inResponseTo)) {
            throw new \RuntimeException('SAML logout InResponseTo validation failed.');
        }
    }

    private function validateLogoutDestination(DOMElement $logoutResponseElement, string $expectedSloUrl): void
    {
        if (! (bool) config('wids.sso.saml.require_destination', true)) {
            return;
        }

        $destination = trim((string) $logoutResponseElement->getAttribute('Destination'));
        if ($destination === '') {
            throw new \RuntimeException('SAML logout response destination is missing.');
        }

        if (! hash_equals($expectedSloUrl, $destination)) {
            throw new \RuntimeException('SAML logout response destination does not match SLO URL.');
        }
    }

    private function validateLogoutRequestDestination(DOMElement $logoutRequestElement, string $expectedSloUrl): void
    {
        if (! (bool) config('wids.sso.saml.require_destination', true)) {
            return;
        }

        $destination = trim((string) $logoutRequestElement->getAttribute('Destination'));
        if ($destination === '') {
            throw new \RuntimeException('SAML logout request destination is missing.');
        }

        if (! hash_equals($expectedSloUrl, $destination)) {
            throw new \RuntimeException('SAML logout request destination does not match SLO URL.');
        }
    }

    private function validateLogoutRequestTimeConstraints(DOMElement $logoutRequestElement): void
    {
        $notOnOrAfter = trim((string) $logoutRequestElement->getAttribute('NotOnOrAfter'));
        if ($notOnOrAfter !== '') {
            $this->assertDateNotExpired($notOnOrAfter, 'SAML logout request has expired.');
        }
    }

    private function validateSubjectConfirmationData(
        DOMXPath $xpath,
        DOMElement $assertionElement,
        string $expectedAcsUrl,
        string $expectedRequestId,
    ): void {
        $subjectConfirmationData = $this->firstElement($xpath, 'saml:Subject/saml:SubjectConfirmation/saml:SubjectConfirmationData', $assertionElement);
        if (! $subjectConfirmationData) {
            throw new \RuntimeException('SAML SubjectConfirmationData is missing.');
        }

        $recipient = trim((string) $subjectConfirmationData->getAttribute('Recipient'));
        if ($recipient !== '' && ! hash_equals($expectedAcsUrl, $recipient)) {
            throw new \RuntimeException('SAML SubjectConfirmationData recipient mismatch.');
        }

        $inResponseTo = trim((string) $subjectConfirmationData->getAttribute('InResponseTo'));
        if ($inResponseTo !== '' && ! hash_equals($expectedRequestId, $inResponseTo)) {
            throw new \RuntimeException('SAML SubjectConfirmationData InResponseTo mismatch.');
        }

        $notOnOrAfter = trim((string) $subjectConfirmationData->getAttribute('NotOnOrAfter'));
        if ($notOnOrAfter !== '') {
            $this->assertDateNotExpired($notOnOrAfter, 'SAML SubjectConfirmationData NotOnOrAfter has expired.');
        }
    }

    private function validateAssertionConditions(DOMXPath $xpath, DOMElement $assertionElement): void
    {
        $conditions = $this->firstElement($xpath, 'saml:Conditions', $assertionElement);
        if (! $conditions) {
            return;
        }

        $notBefore = trim((string) $conditions->getAttribute('NotBefore'));
        if ($notBefore !== '') {
            $this->assertDateNotInFuture($notBefore, 'SAML assertion is not yet valid.');
        }

        $notOnOrAfter = trim((string) $conditions->getAttribute('NotOnOrAfter'));
        if ($notOnOrAfter !== '') {
            $this->assertDateNotExpired($notOnOrAfter, 'SAML assertion has expired.');
        }
    }

    private function validateAudienceRestriction(
        DOMXPath $xpath,
        DOMElement $assertionElement,
        TenantSsoProvider $provider,
        string $metadataUrl,
    ): void {
        if (! (bool) config('wids.sso.saml.require_audience', true)) {
            return;
        }

        $expectedAudience = $this->serviceProviderEntityId($provider, $metadataUrl);
        $audiences = [];

        $nodes = $xpath->query('saml:Conditions/saml:AudienceRestriction/saml:Audience', $assertionElement);
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $audienceValue = trim((string) $node->textContent);
                if ($audienceValue !== '') {
                    $audiences[] = $audienceValue;
                }
            }
        }

        if ($audiences === []) {
            throw new \RuntimeException('SAML audience restriction is missing.');
        }

        foreach ($audiences as $audience) {
            if (hash_equals($expectedAudience, $audience)) {
                return;
            }
        }

        throw new \RuntimeException('SAML audience restriction does not match this service provider.');
    }

    private function validateResponseSignature(
        DOMDocument $document,
        TenantSsoProvider $provider,
        DOMElement $responseElement,
        DOMElement $assertionElement,
        ?string $fallbackXml = null,
    ): void {
        $signatureRequired = $this->metadataBoolean(
            $provider,
            'enforce_signed_assertion',
            (bool) config('wids.sso.saml.require_signed_assertion_default', true)
        );

        $certificatePem = $this->normalizeCertificatePem((string) ($provider->client_secret ?? ''));
        $responseId = trim((string) $responseElement->getAttribute('ID'));
        $assertionId = trim((string) $assertionElement->getAttribute('ID'));
        $currentXml = (string) $document->saveXML();
        $fallbackXml = is_string($fallbackXml) ? trim($fallbackXml) : '';
        $hasSignatures = false;

        $currentVerification = $this->verifyResponseSignaturesInXml(
            xml: $currentXml,
            certificatePem: $certificatePem,
            responseId: $responseId,
            assertionId: $assertionId,
        );
        $hasSignatures = $hasSignatures || $currentVerification['has_signatures'];

        if ($currentVerification['valid']) {
            return;
        }

        if ($fallbackXml !== '' && ! hash_equals($currentXml, $fallbackXml)) {
            $fallbackVerification = $this->verifyResponseSignaturesInXml(
                xml: $fallbackXml,
                certificatePem: $certificatePem,
                responseId: $responseId,
                assertionId: $assertionId,
            );
            $hasSignatures = $hasSignatures || $fallbackVerification['has_signatures'];

            if ($fallbackVerification['valid']) {
                return;
            }
        }

        if (! $hasSignatures && $signatureRequired) {
            throw new \RuntimeException('SAML response is not signed.');
        }

        if (! $hasSignatures) {
            return;
        }

        throw new \RuntimeException('SAML signature verification failed.');
    }

    /**
     * @return array{has_signatures: bool, valid: bool}
     */
    private function verifyResponseSignaturesInXml(
        string $xml,
        string $certificatePem,
        string $responseId,
        string $assertionId,
    ): array {
        $document = $this->loadXmlDocument($xml);
        $xpath = $this->createXPath($document);
        $signatureNodes = $xpath->query('/samlp:Response/ds:Signature | /samlp:Response/saml:Assertion/ds:Signature');
        $signatureCount = $signatureNodes !== false ? $signatureNodes->length : 0;

        if ($signatureCount === 0) {
            return [
                'has_signatures' => false,
                'valid' => false,
            ];
        }

        if ($certificatePem === '') {
            throw new \RuntimeException('SAML IdP certificate is missing for signature verification.');
        }

        for ($index = 1; $index <= $signatureCount; $index++) {
            $signatureResult = $this->verifySignatureAtIndex($xml, $index, $certificatePem);

            if (! $signatureResult['valid']) {
                continue;
            }

            $validatedIds = $signatureResult['validated_ids'];

            if (
                ($assertionId !== '' && in_array($assertionId, $validatedIds, true))
                || ($responseId !== '' && in_array($responseId, $validatedIds, true))
            ) {
                return [
                    'has_signatures' => true,
                    'valid' => true,
                ];
            }
        }

        return [
            'has_signatures' => true,
            'valid' => false,
        ];
    }

    private function validateLogoutResponseSignature(
        DOMDocument $document,
        TenantSsoProvider $provider,
        DOMElement $logoutResponseElement,
    ): void {
        $signatureRequired = $this->metadataBoolean(
            $provider,
            'enforce_signed_logout_response',
            false
        );

        $xpath = $this->createXPath($document);
        $signatureNodes = $xpath->query('/samlp:LogoutResponse/ds:Signature');
        $signatureCount = $signatureNodes !== false ? $signatureNodes->length : 0;

        if ($signatureCount === 0 && $signatureRequired) {
            throw new \RuntimeException('SAML logout response is not signed.');
        }

        if ($signatureCount === 0) {
            return;
        }

        $certificatePem = $this->normalizeCertificatePem((string) ($provider->client_secret ?? ''));
        if ($certificatePem === '') {
            throw new \RuntimeException('SAML IdP certificate is missing for logout signature verification.');
        }

        $logoutResponseId = trim((string) $logoutResponseElement->getAttribute('ID'));
        $xml = (string) $document->saveXML();

        for ($index = 1; $index <= $signatureCount; $index++) {
            $signatureResult = $this->verifySignatureAtQueryIndex(
                xml: $xml,
                query: '(/samlp:LogoutResponse/ds:Signature)['.$index.']',
                certificatePem: $certificatePem
            );

            if (! $signatureResult['valid']) {
                continue;
            }

            if ($logoutResponseId !== '' && in_array($logoutResponseId, $signatureResult['validated_ids'], true)) {
                return;
            }
        }

        throw new \RuntimeException('SAML logout signature verification failed.');
    }

    private function validateLogoutRequestSignature(
        DOMDocument $document,
        TenantSsoProvider $provider,
        DOMElement $logoutRequestElement,
        Request $request,
    ): void {
        $signatureRequired = $this->metadataBoolean(
            $provider,
            'enforce_signed_logout_request',
            (bool) config('wids.sso.saml.require_signed_logout_request_default', true)
        );

        $certificatePem = $this->normalizeCertificatePem((string) ($provider->client_secret ?? ''));
        $xml = (string) $document->saveXML();
        $xmlSignatureValid = false;
        $hasXmlSignature = false;
        $hasRedirectSignature = $this->hasRedirectSignatureParams($request);
        $redirectSignatureValid = false;

        if ($hasRedirectSignature) {
            if ($certificatePem === '') {
                throw new \RuntimeException('SAML IdP certificate is missing for logout request signature verification.');
            }

            $redirectSignatureValid = $this->verifyRedirectBindingSignature($request, $certificatePem, 'SAMLRequest');
            if (! $redirectSignatureValid) {
                throw new \RuntimeException('SAML logout request redirect signature verification failed.');
            }
        }

        $xpath = $this->createXPath($document);
        $signatureNodes = $xpath->query('/samlp:LogoutRequest/ds:Signature');
        $signatureCount = $signatureNodes !== false ? $signatureNodes->length : 0;
        $hasXmlSignature = $signatureCount > 0;

        if ($hasXmlSignature) {
            if ($certificatePem === '') {
                throw new \RuntimeException('SAML IdP certificate is missing for logout request signature verification.');
            }

            $logoutRequestId = trim((string) $logoutRequestElement->getAttribute('ID'));

            for ($index = 1; $index <= $signatureCount; $index++) {
                $signatureResult = $this->verifySignatureAtQueryIndex(
                    xml: $xml,
                    query: '(/samlp:LogoutRequest/ds:Signature)['.$index.']',
                    certificatePem: $certificatePem
                );

                if (! $signatureResult['valid']) {
                    continue;
                }

                if ($logoutRequestId !== '' && in_array($logoutRequestId, $signatureResult['validated_ids'], true)) {
                    $xmlSignatureValid = true;
                    break;
                }
            }

            if (! $xmlSignatureValid) {
                throw new \RuntimeException('SAML logout request XML signature verification failed.');
            }
        }

        $isSigned = $redirectSignatureValid || $xmlSignatureValid;

        if ($signatureRequired && ! $isSigned) {
            throw new \RuntimeException('SAML logout request is not signed.');
        }

        if (! $signatureRequired && ! $isSigned && ($hasRedirectSignature || $hasXmlSignature)) {
            throw new \RuntimeException('SAML logout request signature verification failed.');
        }
    }

    /**
     * @return array{valid: bool, validated_ids: list<string>}
     */
    private function verifySignatureAtIndex(string $xml, int $index, string $certificatePem): array
    {
        return $this->verifySignatureAtQueryIndex(
            xml: $xml,
            query: '(/samlp:Response/ds:Signature | /samlp:Response/saml:Assertion/ds:Signature)['.$index.']',
            certificatePem: $certificatePem
        );
    }

    /**
     * @return array{valid: bool, validated_ids: list<string>}
     */
    private function verifySignatureAtQueryIndex(string $xml, string $query, string $certificatePem): array
    {
        try {
            $document = $this->loadXmlDocument($xml);
            $xpath = $this->createXPath($document);
            $signatureNode = $this->firstElement($xpath, $query);

            if (! $signatureNode) {
                return [
                    'valid' => false,
                    'validated_ids' => [],
                ];
            }

            $signature = new XMLSecurityDSig();
            $signature->sigNode = $signatureNode;
            $signature->idKeys = ['ID'];
            $signature->idNS = [
                'saml' => self::SAML_ASSERTION_NS,
                'samlp' => self::SAML_PROTOCOL_NS,
            ];

            $signature->canonicalizeSignedInfo();
            $signature->validateReference();

            $key = $signature->locateKey();
            if (! $key instanceof XMLSecurityKey) {
                return [
                    'valid' => false,
                    'validated_ids' => [],
                ];
            }

            $key->loadKey($certificatePem, false, true);

            $isValid = $signature->verify($key) === 1;
            $validatedIds = [];
            $validatedNodes = $signature->getValidatedNodes();

            if (is_array($validatedNodes)) {
                foreach (array_keys($validatedNodes) as $validatedId) {
                    if (is_string($validatedId) && $validatedId !== '') {
                        $validatedIds[] = $validatedId;
                    }
                }
            }

            return [
                'valid' => $isValid,
                'validated_ids' => $validatedIds,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'validated_ids' => [],
            ];
        }
    }

    private function hasRedirectSignatureParams(Request $request): bool
    {
        $queryString = (string) $request->server('QUERY_STRING', '');

        return $queryString !== ''
            && $this->rawQueryParam($queryString, 'SAMLRequest') !== null
            && $this->rawQueryParam($queryString, 'SigAlg') !== null
            && $this->rawQueryParam($queryString, 'Signature') !== null;
    }

    private function verifyRedirectBindingSignature(Request $request, string $certificatePem, string $samlParam): bool
    {
        $queryString = (string) $request->server('QUERY_STRING', '');
        if ($queryString === '') {
            return false;
        }

        $rawSaml = $this->rawQueryParam($queryString, $samlParam);
        $rawSigAlg = $this->rawQueryParam($queryString, 'SigAlg');
        $rawSignature = $this->rawQueryParam($queryString, 'Signature');

        if ($rawSaml === null || $rawSigAlg === null || $rawSignature === null) {
            return false;
        }

        $parts = [
            $samlParam.'='.$rawSaml,
        ];

        $rawRelayState = $this->rawQueryParam($queryString, 'RelayState');
        if ($rawRelayState !== null) {
            $parts[] = 'RelayState='.$rawRelayState;
        }

        $parts[] = 'SigAlg='.$rawSigAlg;
        $signedPayload = implode('&', $parts);

        $signatureBinary = base64_decode(rawurldecode($rawSignature), true);
        if (! is_string($signatureBinary) || $signatureBinary === '') {
            return false;
        }

        $sigAlg = rawurldecode($rawSigAlg);
        $opensslAlg = $this->opensslAlgoFromSigAlg($sigAlg);
        if ($opensslAlg === null) {
            return false;
        }

        $result = openssl_verify($signedPayload, $signatureBinary, $certificatePem, $opensslAlg);

        return $result === 1;
    }

    private function opensslAlgoFromSigAlg(string $sigAlg): ?int
    {
        $sigAlg = trim($sigAlg);

        return match ($sigAlg) {
            'http://www.w3.org/2000/09/xmldsig#rsa-sha1' => OPENSSL_ALGO_SHA1,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256' => OPENSSL_ALGO_SHA256,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384' => defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : OPENSSL_ALGO_SHA256,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512' => defined('OPENSSL_ALGO_SHA512') ? OPENSSL_ALGO_SHA512 : OPENSSL_ALGO_SHA256,
            default => null,
        };
    }

    private function rawQueryParam(string $queryString, string $name): ?string
    {
        $pattern = '/(?:^|&)'.preg_quote($name, '/').'=([^&]*)/';

        if (preg_match($pattern, $queryString, $matches) !== 1) {
            return null;
        }

        return (string) ($matches[1] ?? '');
    }

    /**
     * @return array{name_id: string, claims: array<string, mixed>}
     */
    private function extractClaims(DOMXPath $xpath, DOMElement $assertionElement): array
    {
        $nameId = trim((string) $xpath->evaluate('string(saml:Subject/saml:NameID)', $assertionElement));
        $claims = [
            'NameID' => $nameId,
        ];

        $attributeNodes = $xpath->query('saml:AttributeStatement/saml:Attribute', $assertionElement);
        if ($attributeNodes === false) {
            return [
                'name_id' => $nameId,
                'claims' => $claims,
            ];
        }

        foreach ($attributeNodes as $attributeNode) {
            if (! $attributeNode instanceof DOMElement) {
                continue;
            }

            $attributeName = trim((string) $attributeNode->getAttribute('Name'));
            $friendlyName = trim((string) $attributeNode->getAttribute('FriendlyName'));
            $attributeValues = [];

            foreach ($xpath->query('saml:AttributeValue', $attributeNode) ?: [] as $valueNode) {
                if (! $valueNode instanceof DOMNode) {
                    continue;
                }

                $attributeValue = trim((string) $valueNode->textContent);
                if ($attributeValue !== '') {
                    $attributeValues[] = $attributeValue;
                }
            }

            if ($attributeValues === []) {
                continue;
            }

            $normalizedValue = count($attributeValues) === 1 ? $attributeValues[0] : $attributeValues;

            if ($attributeName !== '') {
                $claims[$attributeName] = $normalizedValue;
            }

            if ($friendlyName !== '' && ! array_key_exists($friendlyName, $claims)) {
                $claims[$friendlyName] = $normalizedValue;
            }
        }

        return [
            'name_id' => $nameId,
            'claims' => $claims,
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array{
     *   provider_subject: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>
     * }
     */
    private function mapClaims(TenantSsoProvider $provider, string $nameId, array $claims): array
    {
        $emailClaim = $this->normalizeClaimKey((string) ($provider->email_claim ?? ''), 'email');
        $nameClaim = $this->normalizeClaimKey((string) ($provider->name_claim ?? ''), 'name');
        $subjectClaim = $this->normalizeClaimKey((string) ($provider->subject_claim ?? ''), 'NameID');

        $providerSubject = trim($this->claimToString($claims[$subjectClaim] ?? null));
        if ($providerSubject === '') {
            $providerSubject = trim($nameId);
        }

        if ($providerSubject === '') {
            throw new \RuntimeException('SAML subject claim is missing.');
        }

        $email = strtolower(trim($this->claimToString($claims[$emailClaim] ?? null)));
        if ($email === '' && filter_var($nameId, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($nameId));
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SAML email claim is missing or invalid.');
        }

        if ((bool) ($provider->enforce_verified_email ?? false)) {
            $emailVerified = $this->toBooleanOrNull($claims['email_verified'] ?? null);

            if ($emailVerified === false) {
                throw new \RuntimeException('SAML user email is not verified.');
            }
        }

        $name = trim($this->claimToString($claims[$nameClaim] ?? null));
        if ($name === '') {
            $givenName = trim($this->claimToString($claims['givenName'] ?? null));
            $familyName = trim($this->claimToString($claims['sn'] ?? null));
            $name = trim($givenName.' '.$familyName);
        }

        if ($name === '') {
            $name = ucfirst((string) strstr($email, '@', true));
            $name = $name !== '' ? $name : 'User';
        }

        return [
            'provider_subject' => $providerSubject,
            'email' => $email,
            'name' => $name,
            'claims' => $claims,
        ];
    }

    private function assertDateNotExpired(string $isoDateTime, string $message): void
    {
        $expiresAt = $this->parseSamlDate($isoDateTime);
        if (! $expiresAt) {
            throw new \RuntimeException($message);
        }

        $skew = max(0, (int) config('wids.sso.saml.clock_skew_seconds', 120));
        $now = CarbonImmutable::now('UTC');

        if ($now->greaterThanOrEqualTo($expiresAt->addSeconds($skew))) {
            throw new \RuntimeException($message);
        }
    }

    private function assertDateNotInFuture(string $isoDateTime, string $message): void
    {
        $validFrom = $this->parseSamlDate($isoDateTime);
        if (! $validFrom) {
            throw new \RuntimeException($message);
        }

        $skew = max(0, (int) config('wids.sso.saml.clock_skew_seconds', 120));
        $now = CarbonImmutable::now('UTC');

        if ($now->lessThan($validFrom->subSeconds($skew))) {
            throw new \RuntimeException($message);
        }
    }

    private function parseSamlDate(string $isoDateTime): ?CarbonImmutable
    {
        $value = trim($isoDateTime);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone('UTC');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function decodeSamlResponseToXml(string $response): string
    {
        return $this->decodeSamlPayloadToXml($response, 'SAMLResponse');
    }

    private function decodeSamlRequestToXml(string $request): string
    {
        return $this->decodeSamlPayloadToXml($request, 'SAMLRequest');
    }

    /**
     * @return array{0: DOMXPath, 1: ?DOMElement}
     */
    private function resolveAssertionForAcs(
        DOMDocument $document,
        DOMXPath $xpath,
        TenantSsoProvider $provider,
    ): array {
        $assertionElement = $this->firstElement($xpath, '/samlp:Response/saml:Assertion');
        if ($assertionElement instanceof DOMElement) {
            return [$xpath, $assertionElement];
        }

        $encryptedAssertionElement = $this->firstElement($xpath, '/samlp:Response/saml:EncryptedAssertion');
        if (! $encryptedAssertionElement instanceof DOMElement) {
            return [$xpath, null];
        }

        $decryptedAssertionElement = $this->decryptAssertionElement(
            document: $document,
            xpath: $xpath,
            encryptedAssertionElement: $encryptedAssertionElement,
            provider: $provider,
        );

        return [$this->createXPath($document), $decryptedAssertionElement];
    }

    private function decryptAssertionElement(
        DOMDocument $document,
        DOMXPath $xpath,
        DOMElement $encryptedAssertionElement,
        TenantSsoProvider $provider,
    ): DOMElement {
        $encryptedDataElement = $this->firstElement($xpath, 'xenc:EncryptedData', $encryptedAssertionElement);
        if (! $encryptedDataElement instanceof DOMElement) {
            throw new \RuntimeException('SAML encrypted assertion payload is invalid.');
        }

        $privateKeyPem = $this->normalizePrivateKeyPem((string) ($provider->private_key ?? ''));
        if ($privateKeyPem === '') {
            throw new \RuntimeException('SAML encrypted assertion cannot be decrypted: SP private key is missing.');
        }

        $decryptedAssertionXml = $this->decryptAssertionXmlFragment($encryptedDataElement, $privateKeyPem);
        $decryptedAssertionElement = $this->parseDecryptedAssertionFragment($decryptedAssertionXml, $document);
        $parent = $encryptedAssertionElement->parentNode;

        if (! $parent instanceof DOMNode) {
            throw new \RuntimeException('SAML encrypted assertion cannot be attached to response.');
        }

        $parent->replaceChild($decryptedAssertionElement, $encryptedAssertionElement);

        return $decryptedAssertionElement;
    }

    private function decryptAssertionXmlFragment(DOMElement $encryptedDataElement, string $privateKeyPem): string
    {
        $ownerDocument = $encryptedDataElement->ownerDocument;
        if (! $ownerDocument instanceof DOMDocument) {
            throw new \RuntimeException('SAML encrypted assertion decryption failed.');
        }

        $xpath = new DOMXPath($ownerDocument);
        $xpath->registerNamespace('xenc', self::XMLENC_NS);
        $encryptionMethod = trim((string) $xpath->evaluate('string(./xenc:EncryptionMethod/@Algorithm)', $encryptedDataElement));

        if ($encryptionMethod === '') {
            throw new \RuntimeException('SAML encrypted assertion encryption method is missing.');
        }

        try {
            $dataKey = new XMLSecurityKey($encryptionMethod);
        } catch (\Throwable $e) {
            throw new \RuntimeException('SAML encrypted assertion uses unsupported encryption method.');
        }

        try {
            $keyInfo = XMLSecEnc::staticLocateKeyInfo($dataKey, $encryptedDataElement);
            if (! $keyInfo instanceof XMLSecurityKey) {
                throw new \RuntimeException('SAML encrypted assertion key information is missing.');
            }

            if ($keyInfo->isEncrypted) {
                $keyInfo->loadKey($privateKeyPem, false);
                $decryptedSessionKey = $keyInfo->encryptedCtx instanceof XMLSecEnc
                    ? $keyInfo->encryptedCtx->decryptKey($keyInfo)
                    : null;

                if (! is_string($decryptedSessionKey) || $decryptedSessionKey === '') {
                    throw new \RuntimeException('SAML encrypted assertion key decryption failed.');
                }

                $dataKey->key = $decryptedSessionKey;
            } elseif (is_string($keyInfo->key) && $keyInfo->key !== '') {
                $dataKey->key = $keyInfo->key;
            }

            if (! is_string($dataKey->key) || $dataKey->key === '') {
                throw new \RuntimeException('SAML encrypted assertion decryption key is unavailable.');
            }

            $encryption = new XMLSecEnc();
            $encryption->setNode($encryptedDataElement);
            $decryptedAssertionXml = $encryption->decryptNode($dataKey, false);

            if (! is_string($decryptedAssertionXml) || trim($decryptedAssertionXml) === '') {
                throw new \RuntimeException('SAML encrypted assertion decryption returned an empty payload.');
            }

            return trim($decryptedAssertionXml);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('SAML encrypted assertion decryption failed.');
        }
    }

    private function parseDecryptedAssertionFragment(string $assertionXml, DOMDocument $targetDocument): DOMElement
    {
        $wrappedAssertionXml = '<Envelope xmlns:saml="'.self::SAML_ASSERTION_NS.'" xmlns:ds="'.self::XMLDSIG_NS.'">'
            .$assertionXml
            .'</Envelope>';
        $wrappedDocument = $this->loadXmlDocument($wrappedAssertionXml);
        $wrappedXPath = new DOMXPath($wrappedDocument);
        $wrappedXPath->registerNamespace('saml', self::SAML_ASSERTION_NS);
        $assertionElement = $this->firstElement($wrappedXPath, '/Envelope/saml:Assertion');

        if (! $assertionElement instanceof DOMElement) {
            throw new \RuntimeException('SAML encrypted assertion payload does not contain an Assertion.');
        }

        $importedAssertion = $targetDocument->importNode($assertionElement, true);

        if (! $importedAssertion instanceof DOMElement) {
            throw new \RuntimeException('SAML encrypted assertion payload could not be imported.');
        }

        return $importedAssertion;
    }

    private function decodeSamlPayloadToXml(string $payload, string $payloadName): string
    {
        $decoded = base64_decode($payload, true);
        if (! is_string($decoded) || trim($decoded) === '') {
            throw new \RuntimeException($payloadName.' decoding failed.');
        }

        $decoded = trim($decoded);
        if (str_starts_with($decoded, '<')) {
            return $decoded;
        }

        $inflated = @gzinflate($decoded);
        if (! is_string($inflated) || trim($inflated) === '') {
            throw new \RuntimeException($payloadName.' decoded payload is not valid XML.');
        }

        return trim($inflated);
    }

    private function loadXmlDocument(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new \RuntimeException('Failed to parse SAML XML payload.');
        }

        return $document;
    }

    private function createXPath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('samlp', self::SAML_PROTOCOL_NS);
        $xpath->registerNamespace('saml', self::SAML_ASSERTION_NS);
        $xpath->registerNamespace('ds', self::XMLDSIG_NS);
        $xpath->registerNamespace('xenc', self::XMLENC_NS);

        return $xpath;
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMNode $contextNode = null): ?DOMElement
    {
        $nodes = $xpath->query($query, $contextNode);
        if ($nodes === false) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function serviceProviderEntityId(TenantSsoProvider $provider, string $metadataUrl): string
    {
        $spEntityId = trim((string) ($provider->client_id ?? ''));

        return $spEntityId !== '' ? $spEntityId : $metadataUrl;
    }

    private function buildAuthnRequestXml(
        string $id,
        string $issueInstant,
        string $destination,
        string $acsUrl,
        string $spEntityId,
        string $nameIdFormat,
    ): string {
        return '<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
            .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($id).'" '
            .'Version="2.0" '
            .'IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($destination).'" '
            .'AssertionConsumerServiceURL="'.$this->xmlEscape($acsUrl).'" '
            .'ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">'
            .'<saml:Issuer>'.$this->xmlEscape($spEntityId).'</saml:Issuer>'
            .'<samlp:NameIDPolicy AllowCreate="true" Format="'.$this->xmlEscape($nameIdFormat).'" />'
            .'</samlp:AuthnRequest>';
    }

    private function buildLogoutRequestXml(
        string $id,
        string $issueInstant,
        string $destination,
        string $spEntityId,
        string $nameId,
        string $nameIdFormat,
        ?string $sessionIndex = null,
    ): string {
        $sessionIndex = trim((string) $sessionIndex);

        return '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
            .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($id).'" '
            .'Version="2.0" '
            .'IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($destination).'">'
            .'<saml:Issuer>'.$this->xmlEscape($spEntityId).'</saml:Issuer>'
            .'<saml:NameID Format="'.$this->xmlEscape($nameIdFormat).'">'.$this->xmlEscape($nameId).'</saml:NameID>'
            .($sessionIndex !== ''
                ? '<samlp:SessionIndex>'.$this->xmlEscape($sessionIndex).'</samlp:SessionIndex>'
                : '')
            .'</samlp:LogoutRequest>';
    }

    private function buildLogoutResponseXml(
        string $id,
        string $issueInstant,
        string $destination,
        string $inResponseTo,
        string $spEntityId,
    ): string {
        return '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
            .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($id).'" '
            .'Version="2.0" '
            .'IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($destination).'" '
            .'InResponseTo="'.$this->xmlEscape($inResponseTo).'">'
            .'<saml:Issuer>'.$this->xmlEscape($spEntityId).'</saml:Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .'</samlp:LogoutResponse>';
    }

    private function normalizeCertificatePem(string $certificate): string
    {
        $certificate = trim($certificate);

        if ($certificate === '') {
            return '';
        }

        if (str_contains($certificate, 'BEGIN CERTIFICATE')) {
            return $certificate;
        }

        $certificate = preg_replace('/\s+/', '', $certificate) ?? '';
        if ($certificate === '') {
            return '';
        }

        return "-----BEGIN CERTIFICATE-----\n"
            .trim(chunk_split($certificate, 64, "\n"))
            ."\n-----END CERTIFICATE-----";
    }

    private function normalizePrivateKeyPem(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        if ($privateKey === '') {
            return '';
        }

        if (
            str_contains($privateKey, 'BEGIN PRIVATE KEY')
            || str_contains($privateKey, 'BEGIN RSA PRIVATE KEY')
            || str_contains($privateKey, 'BEGIN EC PRIVATE KEY')
        ) {
            return $privateKey;
        }

        $normalized = preg_replace('/\s+/', '', $privateKey) ?? '';
        if ($normalized === '') {
            return '';
        }

        $headers = [
            'PRIVATE KEY',
            'RSA PRIVATE KEY',
            'EC PRIVATE KEY',
        ];

        foreach ($headers as $header) {
            $candidate = "-----BEGIN {$header}-----\n"
                .trim(chunk_split($normalized, 64, "\n"))
                ."\n-----END {$header}-----";
            $resource = openssl_pkey_get_private($candidate);

            if ($resource !== false) {
                return $candidate;
            }
        }

        return $privateKey;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function sessionKey(TenantSsoProvider $provider): string
    {
        return 'sso:saml:state:tenant:'.$provider->tenant_id.':provider:'.$provider->provider;
    }

    private function sloSessionKey(TenantSsoProvider $provider): string
    {
        return 'sso:saml:slo:state:tenant:'.$provider->tenant_id.':provider:'.$provider->provider;
    }

    private function metadataString(TenantSsoProvider $provider, string $key, string $default): string
    {
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $value = is_string($metadata[$key] ?? null) ? trim((string) $metadata[$key]) : '';

        return $value !== '' ? $value : $default;
    }

    private function metadataBoolean(TenantSsoProvider $provider, string $key, bool $default): bool
    {
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];

        if (! array_key_exists($key, $metadata)) {
            return $default;
        }

        return filter_var($metadata[$key], FILTER_VALIDATE_BOOL);
    }

    private function normalizeClaimKey(string $value, string $default): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    private function claimToString(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $entry) {
                $normalized = $this->claimToString($entry);
                if ($normalized !== '') {
                    return $normalized;
                }
            }

            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function toBooleanOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === 'true' || $value === '1' || $value === 'yes') {
                return true;
            }

            if ($value === 'false' || $value === '0' || $value === 'no') {
                return false;
            }
        }

        return null;
    }
}
