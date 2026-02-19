<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-500 focus:ring-2 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-400']) }}>
    {{ $slot }}
</button>
