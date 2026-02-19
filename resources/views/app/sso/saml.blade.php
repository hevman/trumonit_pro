<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-100">{{ __('SSO (SAML)') }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('sso.edit') }}" class="app-link">{{ __('OIDC settings') }}</a>
                <a href="{{ route('dashboard') }}" class="app-link">{{ __('messages.nav.dashboard') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if (! $samlGlobalEnabled)
                <div class="app-card app-card-warning">
                    {{ __('SAML is disabled globally. Set') }} <code>WIDS_SSO_SAML_ENABLED=true</code> {{ __('in environment before enabling workspace SAML.') }}
                </div>
            @endif

            <div class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Workspace SAML configuration') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Configure SAML SSO for this workspace (metadata, signed assertion validation, and user mapping/JIT provisioning policy).') }}
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="app-help">
                        {{ __('Login start URL') }}:
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-xs dark:border-gray-700 dark:bg-gray-900">{{ $loginStartUrl }}</div>
                    </div>
                    <div class="app-help">
                        {{ __('ACS URL') }}:
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-xs dark:border-gray-700 dark:bg-gray-900">{{ $acsUrl }}</div>
                    </div>
                    <div class="app-help">
                        {{ __('Metadata URL') }}:
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-xs dark:border-gray-700 dark:bg-gray-900">{{ $metadataUrl }}</div>
                    </div>
                </div>
            </div>

            <div class="app-card">
                <form method="POST" action="{{ route('sso.saml.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="saml_enabled" value="0">
                            <input type="checkbox" class="app-checkbox" name="saml_enabled" value="1" @checked((bool) old('saml_enabled', $provider->is_enabled))>
                            <span>{{ __('Enable SAML for this workspace') }}</span>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="enforce_signed_assertion" value="0">
                            <input type="checkbox" class="app-checkbox" name="enforce_signed_assertion" value="1" @checked((bool) old('enforce_signed_assertion', $enforceSignedAssertion))>
                            <span>{{ __('Require signed assertion') }}</span>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="enforce_signed_logout_request" value="0">
                            <input type="checkbox" class="app-checkbox" name="enforce_signed_logout_request" value="1" @checked((bool) old('enforce_signed_logout_request', $enforceSignedLogoutRequest))>
                            <span>{{ __('Require signed logout request') }}</span>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="enforce_signed_logout_response" value="0">
                            <input type="checkbox" class="app-checkbox" name="enforce_signed_logout_response" value="1" @checked((bool) old('enforce_signed_logout_response', $enforceSignedLogoutResponse))>
                            <span>{{ __('Require signed logout response') }}</span>
                        </label>

                        <div>
                            <label class="app-label">{{ __('IdP entity ID') }}</label>
                            <input type="text" name="idp_entity_id" value="{{ old('idp_entity_id', $provider->issuer_url) }}" class="app-input" placeholder="https://idp.example.com/metadata" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('IdP SSO URL') }}</label>
                            <input type="url" name="sso_url" value="{{ old('sso_url', $provider->authorization_endpoint) }}" class="app-input" placeholder="https://idp.example.com/sso" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('IdP SLO URL (optional)') }}</label>
                            <input type="url" name="slo_url" value="{{ old('slo_url', $provider->token_endpoint) }}" class="app-input" placeholder="https://idp.example.com/slo" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('IdP metadata URL (optional)') }}</label>
                            <input type="url" name="metadata_url" value="{{ old('metadata_url', $provider->jwks_uri) }}" class="app-input" placeholder="https://idp.example.com/metadata.xml" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('SP entity ID (optional override)') }}</label>
                            <input type="text" name="sp_entity_id" value="{{ old('sp_entity_id', $provider->client_id) }}" class="app-input" placeholder="{{ $metadataUrl }}" />
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('If empty, metadata URL is used as SP entity ID.') }}</div>
                        </div>

                        <div>
                            <label class="app-label">{{ __('NameID format') }}</label>
                            <input type="text" name="nameid_format" value="{{ old('nameid_format', $nameIdFormat) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Email attribute') }}</label>
                            <input type="text" name="email_attribute" value="{{ old('email_attribute', $provider->email_claim) }}" class="app-input" placeholder="email" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Name attribute') }}</label>
                            <input type="text" name="name_attribute" value="{{ old('name_attribute', $provider->name_claim) }}" class="app-input" placeholder="displayName" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Subject attribute') }}</label>
                            <input type="text" name="subject_attribute" value="{{ old('subject_attribute', $provider->subject_claim) }}" class="app-input" placeholder="NameID" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="app-label">{{ __('IdP X509 certificate (PEM)') }}</label>
                            <textarea name="x509_certificate" rows="6" class="app-input" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----">{{ old('x509_certificate', '') }}</textarea>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Leave empty to keep existing certificate.') }}</div>
                        </div>

                        <div>
                            <label class="app-label">{{ __('SP decryption private key (PEM, optional)') }}</label>
                            <textarea name="sp_private_key" rows="6" class="app-input" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----">{{ old('sp_private_key', '') }}</textarea>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Required only if IdP sends encrypted assertions. Leave empty to keep existing key.') }}</div>
                            @if ($spPrivateKeySet)
                                <div class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">{{ __('Encrypted assertion key is configured.') }}</div>
                            @endif
                        </div>

                        <div>
                            <label class="app-label">{{ __('Allowed email domains (optional)') }}</label>
                            <textarea name="domain_allowlist" rows="4" class="app-input" placeholder="example.com&#10;partner.example">{{ old('domain_allowlist', $domainAllowlistText) }}</textarea>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('One domain per line. Empty means no domain restriction.') }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="auto_provision" value="0">
                            <input type="checkbox" class="app-checkbox" name="auto_provision" value="1" @checked((bool) old('auto_provision', $provider->auto_provision))>
                            <span>{{ __('Auto-provision new users from SAML attributes') }}</span>
                        </label>

                        <div>
                            <label class="app-label">{{ __('Auto-provision role') }}</label>
                            <select name="auto_provision_role" class="app-input">
                                @php($autoProvisionRole = old('auto_provision_role', $provider->auto_provision_role ?? 'member'))
                                <option value="member" @selected($autoProvisionRole === 'member')>{{ __('member') }}</option>
                                <option value="owner" @selected($autoProvisionRole === 'owner')>{{ __('owner') }}</option>
                            </select>
                        </div>
                    </div>

                    @if (is_string($provider->last_error) && trim($provider->last_error) !== '')
                        <div class="app-card app-card-danger text-xs">
                            {{ __('Last SAML error') }}: {{ $provider->last_error }}
                        </div>
                    @endif

                    <div>
                        <button type="submit" class="app-button-primary">{{ __('Save SAML settings') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
