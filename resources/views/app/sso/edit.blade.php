<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-100">{{ __('SSO (OIDC)') }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('sso.saml.edit') }}" class="app-link">{{ __('SAML settings') }}</a>
                <a href="{{ route('dashboard') }}" class="app-link">
                    {{ __('messages.nav.dashboard') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if (! $oidcGlobalEnabled)
                <div class="app-card app-card-warning">
                    {{ __('OIDC is disabled globally. Set') }} <code>WIDS_SSO_OIDC_ENABLED=true</code> {{ __('in environment before enabling workspace SSO.') }}
                </div>
            @endif

            <div class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Workspace OIDC configuration') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Configure OpenID Connect for this workspace. Use provider metadata discovery via issuer URL, then review/override endpoints if needed.') }}
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="app-help">
                        {{ __('Login start URL') }}:
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-xs dark:border-gray-700 dark:bg-gray-900">{{ $loginStartUrl }}</div>
                    </div>
                    <div class="app-help">
                        {{ __('Callback URL') }}:
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 font-mono text-xs dark:border-gray-700 dark:bg-gray-900">{{ $callbackUrl }}</div>
                    </div>
                </div>
            </div>

            <div class="app-card">
                <form method="POST" action="{{ route('sso.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Identity governance policy') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Define who must use SSO, which providers are allowed, and optional maximum session duration.') }}
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label class="app-label">{{ __('Enforce SSO for') }}</label>
                                @php($enforceSsoFor = old('identity_enforce_sso_for', $identityPolicy['enforce_sso_for'] ?? 'off'))
                                <select name="identity_enforce_sso_for" class="app-input">
                                    <option value="off" @selected($enforceSsoFor === 'off')>{{ __('OFF') }}</option>
                                    <option value="owner" @selected($enforceSsoFor === 'owner')>{{ __('owner only') }}</option>
                                    <option value="all" @selected($enforceSsoFor === 'all')>{{ __('all users') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="app-label">{{ __('Default SSO provider') }}</label>
                                @php($defaultSsoProvider = old('identity_default_sso_provider', $identityPolicy['default_sso_provider'] ?? 'auto'))
                                <select name="identity_default_sso_provider" class="app-input">
                                    <option value="auto" @selected($defaultSsoProvider === 'auto')>{{ __('auto') }}</option>
                                    <option value="oidc" @selected($defaultSsoProvider === 'oidc')>{{ __('oidc') }}</option>
                                    <option value="saml" @selected($defaultSsoProvider === 'saml')>{{ __('saml') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="app-label">{{ __('Max session duration (minutes)') }}</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="43200"
                                    step="1"
                                    name="identity_session_max_minutes"
                                    value="{{ old('identity_session_max_minutes', (int) ($identityPolicy['session_max_minutes'] ?? 0)) }}"
                                    class="app-input"
                                    placeholder="{{ __('0 = unlimited') }}"
                                />
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                            @php($allowPasswordLogin = (bool) old('identity_allow_password_login', (bool) ($identityPolicy['allow_password_login'] ?? true)))
                            @php($allowedProviders = is_array($identityPolicy['allowed_sso_providers'] ?? null) ? $identityPolicy['allowed_sso_providers'] : ['oidc', 'saml'])
                            @php($allowOidc = (bool) old('identity_allow_oidc', in_array('oidc', $allowedProviders, true)))
                            @php($allowSaml = (bool) old('identity_allow_saml', in_array('saml', $allowedProviders, true)))
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="identity_allow_password_login" value="0">
                                <input type="checkbox" class="app-checkbox" name="identity_allow_password_login" value="1" @checked($allowPasswordLogin)>
                                <span>{{ __('Allow password login') }}</span>
                            </label>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="identity_allow_oidc" value="0">
                                <input type="checkbox" class="app-checkbox" name="identity_allow_oidc" value="1" @checked($allowOidc)>
                                <span>{{ __('Allow OIDC SSO') }}</span>
                            </label>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="identity_allow_saml" value="0">
                                <input type="checkbox" class="app-checkbox" name="identity_allow_saml" value="1" @checked($allowSaml)>
                                <span>{{ __('Allow SAML SSO') }}</span>
                            </label>
                        </div>

                        <div class="mt-4">
                            <label class="app-label">{{ __('Domain-based SSO routing (optional)') }}</label>
                            @php($domainRoutes = is_array($identityPolicy['sso_domain_routing'] ?? null) ? $identityPolicy['sso_domain_routing'] : [])
                            @php($domainRoutesText = collect($domainRoutes)->map(fn ($provider, $domain) => $domain.' '.$provider)->implode(PHP_EOL))
                            <textarea name="identity_sso_domain_routing" rows="4" class="app-input" placeholder="partner.com saml&#10;company.com oidc">{{ old('identity_sso_domain_routing', $domainRoutesText) }}</textarea>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Format: one rule per line, e.g.') }} <code>example.com saml</code>.</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="oidc_enabled" value="0">
                            <input type="checkbox" class="app-checkbox" name="oidc_enabled" value="1" @checked((bool) old('oidc_enabled', $provider->is_enabled))>
                            <span>{{ __('Enable OIDC for this workspace') }}</span>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="hidden" name="enforce_verified_email" value="0">
                            <input type="checkbox" class="app-checkbox" name="enforce_verified_email" value="1" @checked((bool) old('enforce_verified_email', $provider->enforce_verified_email))>
                            <span>{{ __('Require verified email claim') }}</span>
                        </label>

                        <div>
                            <label class="app-label">{{ __('Issuer URL') }}</label>
                            <input type="url" name="issuer_url" value="{{ old('issuer_url', $provider->issuer_url) }}" class="app-input" placeholder="https://idp.example.com/realms/main" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Client ID') }}</label>
                            <input type="text" name="client_id" value="{{ old('client_id', $provider->client_id) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Client Secret') }}</label>
                            <input type="password" name="client_secret" value="" class="app-input" placeholder="{{ ($provider->client_secret ?? null) ? __('Leave blank to keep existing secret') : '' }}" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Scopes') }}</label>
                            <input type="text" name="scopes" value="{{ old('scopes', $provider->scopes) }}" class="app-input" placeholder="openid profile email" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Email claim') }}</label>
                            <input type="text" name="email_claim" value="{{ old('email_claim', $provider->email_claim) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Name claim') }}</label>
                            <input type="text" name="name_claim" value="{{ old('name_claim', $provider->name_claim) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Subject claim') }}</label>
                            <input type="text" name="subject_claim" value="{{ old('subject_claim', $provider->subject_claim) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Authorization endpoint (optional override)') }}</label>
                            <input type="url" name="authorization_endpoint" value="{{ old('authorization_endpoint', $provider->authorization_endpoint) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Token endpoint (optional override)') }}</label>
                            <input type="url" name="token_endpoint" value="{{ old('token_endpoint', $provider->token_endpoint) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('Userinfo endpoint (optional override)') }}</label>
                            <input type="url" name="userinfo_endpoint" value="{{ old('userinfo_endpoint', $provider->userinfo_endpoint) }}" class="app-input" />
                        </div>

                        <div>
                            <label class="app-label">{{ __('JWKS URI (optional override)') }}</label>
                            <input type="url" name="jwks_uri" value="{{ old('jwks_uri', $provider->jwks_uri) }}" class="app-input" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="app-label">{{ __('Allowed email domains (optional)') }}</label>
                            <textarea name="domain_allowlist" rows="4" class="app-input" placeholder="example.com&#10;partner.example">{{ old('domain_allowlist', $domainAllowlistText) }}</textarea>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('One domain per line. Empty means no domain restriction.') }}</div>
                        </div>

                        <div class="space-y-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="auto_provision" value="0">
                                <input type="checkbox" class="app-checkbox" name="auto_provision" value="1" @checked((bool) old('auto_provision', $provider->auto_provision))>
                                <span>{{ __('Auto-provision new users from OIDC') }}</span>
                            </label>

                            <div>
                                <label class="app-label">{{ __('Auto-provision role') }}</label>
                                <select name="auto_provision_role" class="app-input">
                                    @php($autoProvisionRole = old('auto_provision_role', $provider->auto_provision_role ?? 'member'))
                                    <option value="member" @selected($autoProvisionRole === 'member')>{{ __('member') }}</option>
                                    <option value="owner" @selected($autoProvisionRole === 'owner')>{{ __('owner') }}</option>
                                </select>
                            </div>

                            @if (is_string($provider->last_error) && trim($provider->last_error) !== '')
                                <div class="app-card app-card-danger text-xs">
                                    {{ __('Last discovery error') }}: {{ $provider->last_error }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="app-button-primary">
                            {{ __('Save SSO settings') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
