<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('messages.auth.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('messages.auth.password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="app-checkbox" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:text-gray-300 dark:hover:text-gray-100 dark:focus:ring-offset-gray-900" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if ((bool) config('wids.sso.oidc.enabled', false) || (bool) config('wids.sso.saml.enabled', false))
        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Single sign-on') }}</p>
            <form method="GET" action="{{ route('sso.redirect') }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <input
                    type="text"
                    name="tenant"
                    value="{{ old('tenant') }}"
                    class="app-input"
                    placeholder="{{ __('workspace-slug') }}"
                    required
                />
                <input
                    type="email"
                    name="email"
                    value="{{ old('sso_email', old('email')) }}"
                    class="app-input"
                    placeholder="{{ __('email@company.com (optional)') }}"
                />
                <x-secondary-button type="submit">
                    {{ __('Continue with SSO') }}
                </x-secondary-button>
            </form>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Use workspace slug from admin. Optional email enables domain-based IdP routing.') }}</p>
        </div>
    @endif
</x-guest-layout>
