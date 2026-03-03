<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg border border-transparent bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-black focus:ring-2 focus:ring-indigo-500 dark:bg-indigo-600 dark:hover:bg-indigo-500']) }}>
    {{ $slot }}
</button>
