@props([
    'class' => '',
])

<button
    type="button"
    data-theme-toggle
    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white/90 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900/90 dark:text-gray-200 dark:hover:bg-gray-800 {{ $class }}"
    aria-live="polite"
>
    <span data-theme-icon aria-hidden="true"></span>
    <span class="hidden sm:inline" data-theme-label></span>
</button>
