@php
    $sectionLabel = match (true) {
        request()->routeIs('dashboard') => __('messages.nav.dashboard'),
        request()->routeIs('monitored-urls.*') => __('messages.nav.monitored_urls'),
        request()->routeIs('incidents.*') => __('messages.nav.incidents'),
        request()->routeIs('status-pages.*') => __('messages.nav.status_pages'),
        request()->routeIs('notification-channels.*') => __('messages.nav.notifications'),
        request()->routeIs('sso.*') => __('messages.nav.sso'),
        request()->routeIs('billing.*') => __('messages.nav.billing'),
        request()->routeIs('profile.*') => __('Profile'),
        default => __('messages.nav.dashboard'),
    };
    $langSwitchEn = request()->fullUrlWithQuery(['lang' => 'en']);
    $langSwitchPl = request()->fullUrlWithQuery(['lang' => 'pl']);

@endphp

<nav class="sticky top-0 z-50 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none lg:hidden dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                aria-label="{{ __('Menu') }}"
            >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2">
                <x-application-logo class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-100" />
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name', 'TruMonit') }}</span>
            </a>
            <span class="hidden text-sm text-gray-400 lg:inline">/</span>
            <span class="hidden text-sm font-medium text-gray-600 lg:inline dark:text-gray-300">{{ $sectionLabel }}</span>
        </div>

        <div class="flex items-center gap-3">
            <x-theme-toggle />

            <div class="text-sm text-gray-600 dark:text-gray-300">
                <a href="{{ $langSwitchEn }}" class="{{ app()->getLocale() === 'en' ? 'font-semibold text-gray-900 dark:text-white' : '' }}">EN</a>
                <span class="mx-1 text-gray-300 dark:text-gray-600">/</span>
                <a href="{{ $langSwitchPl }}" class="{{ app()->getLocale() === 'pl' ? 'font-semibold text-gray-900 dark:text-white' : '' }}">PL</a>
            </div>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</nav>
