<svg
    viewBox="0 0 64 64"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ config('app.name', 'TruMonit') }}"
    {{ $attributes }}
>
    <!-- Background disc (subtle) -->
    <circle cx="32" cy="32" r="28" fill="currentColor" opacity="0.14" />

    <!-- Outer ring -->
    <circle cx="32" cy="32" r="20" fill="none" stroke="currentColor" stroke-width="4" opacity="0.9" />

    <!-- Pulse line -->
    <path
        d="M16 34h10l3-8 6 16 4-10h11"
        fill="none"
        stroke="currentColor"
        stroke-width="4"
        stroke-linecap="round"
        stroke-linejoin="round"
    />

    <!-- Center dot -->
    <circle cx="32" cy="34" r="3" fill="currentColor" />
</svg>

