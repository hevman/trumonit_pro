@php
    $isOwner = (auth()->user()?->role ?? null) !== 'member';
@endphp

<div
    class="fixed inset-0 z-40 bg-black/50 transition-opacity lg:hidden"
    x-show="sidebarOpen"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="sidebarOpen = false"
    x-cloak
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex min-h-screen w-72 flex-none -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-out dark:border-gray-800 dark:bg-gray-900 lg:sticky lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen }"
>
    <div class="flex h-16 items-center gap-3 border-b border-gray-200 px-4 dark:border-gray-800">
        <x-application-logo class="h-8 w-auto fill-current text-gray-800 dark:text-gray-100" />
        <span class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name', 'TruMonit') }}</span>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm" @click="if ($event.target.closest('a')) sidebarOpen = false">
        @if ($isOwner)
            <div class="mb-3 space-y-2">
                <button type="button" class="app-button-primary w-full justify-center" x-on:click="$dispatch('open-modal', 'quick-add-monitor')">
                    {{ __('messages.dashboard.action_create_monitor') }}
                </button>
                <div class="flex items-center justify-center gap-3 text-xs">
                    <a href="{{ route('onboarding.show') }}" class="app-link">
                        {{ __('messages.quick_add.open_wizard') }}
                    </a>
                    <a href="{{ route('monitored-urls.create') }}" class="app-link">
                        {{ __('messages.dashboard.onboarding_advanced') }}
                    </a>
                </div>
            </div>
        @endif

        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('dashboard') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 14h6v6h-6z" /></svg>
            {{ __('messages.nav.dashboard') }}
        </a>

        <a href="{{ route('monitored-urls.index') }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('monitored-urls.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 5h5m-8 9h14a2 2 0 002-2V7a2 2 0 00-2-2h-3.172a2 2 0 01-1.414-.586l-.828-.828A2 2 0 0012.172 3H11.83a2 2 0 00-1.414.586l-.828.828A2 2 0 018.172 5H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            {{ __('messages.nav.monitored_urls') }}
        </a>

        <a href="{{ route('incidents.index') }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('incidents.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L2.33 16c-.77 1.333.192 3 1.732 3z" /></svg>
            {{ __('messages.nav.incidents') }}
        </a>

        <a href="{{ route('status-pages.index') }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('status-pages.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h8M3 7h18M5 7v10a2 2 0 002 2h2m0 0h8a2 2 0 002-2V7" /></svg>
            {{ __('messages.nav.status_pages') }}
        </a>

        @if ($isOwner)
            <div class="my-3 border-t border-gray-200 dark:border-gray-800"></div>

            <a href="{{ route('notification-channels.edit') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('notification-channels.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                {{ __('messages.nav.notifications') }}
            </a>

            <a href="{{ route('sso.edit') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('sso.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10" /></svg>
                {{ __('messages.nav.sso') }}
            </a>

            <a href="{{ route('billing.index') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('billing.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                {{ __('messages.nav.billing') }}
            </a>
        @endif

        <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-800">
            <a href="{{ route('profile.edit') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('profile.*') ? 'bg-gray-900 font-medium text-white dark:bg-indigo-500 dark:text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A11.955 11.955 0 0112 15c2.56 0 4.932.804 6.879 2.173M15 11a3 3 0 11-6 0 3 3 0 016 0zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ __('Profile') }}
            </a>
        </div>
    </nav>
</aside>
