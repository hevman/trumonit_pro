<style type="text/tailwindcss">
    @layer base {
        [x-cloak] {
            display: none !important;
        }

        :focus-visible {
            @apply outline-none ring-2 ring-indigo-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-950;
        }
    }

    @layer components {
        .app-page {
            @apply py-6;
        }

        .app-page-lg {
            @apply py-12;
        }

        .app-container {
            @apply mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8;
        }

        .app-container-5xl {
            @apply mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8;
        }

        .app-container-4xl {
            @apply mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8;
        }

        .app-container-3xl {
            @apply mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8;
        }

        .app-card {
            @apply rounded-2xl border border-gray-200/80 bg-white/95 p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/90;
        }

        .app-card-lg {
            @apply p-6;
        }

        .app-card-info {
            @apply border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800/80 dark:bg-blue-950/30 dark:text-blue-100;
        }

        .app-card-success {
            @apply border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/80 dark:bg-emerald-950/30 dark:text-emerald-100;
        }

        .app-card-warning {
            @apply border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800/80 dark:bg-amber-950/30 dark:text-amber-100;
        }

        .app-card-danger {
            @apply border-red-200 bg-red-50 text-red-900 dark:border-red-800/80 dark:bg-red-950/30 dark:text-red-100;
        }

        .app-table {
            @apply overflow-hidden rounded-2xl border border-gray-200/80 bg-white/95 shadow-sm dark:border-gray-800 dark:bg-gray-900/90;
        }

        .app-analytics-chart {
            @apply mt-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-950/60;
        }

        .app-analytics-chart-legend {
            @apply mb-2 flex items-center justify-between gap-2;
        }

        .app-analytics-chart-legend-label {
            @apply flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300;
        }

        .app-analytics-chart-legend-dot {
            @apply inline-block h-2.5 w-2.5 rounded-full;
        }

        .app-analytics-chart-legend-meta {
            @apply text-[11px] text-gray-500 dark:text-gray-400;
        }

        .app-analytics-chart-bars {
            @apply flex items-end gap-1;
        }

        .app-analytics-chart-bar-item {
            @apply relative flex-1;
        }

        .app-analytics-chart-bar-item-clickable {
            @apply cursor-pointer rounded-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-950;
        }

        .app-analytics-chart-bar {
            @apply w-full rounded-t transition;
        }

        .app-analytics-chart-tooltip {
            @apply pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-[10px] text-white;
        }

        .app-analytics-chart-bar-item:hover .app-analytics-chart-tooltip,
        .app-analytics-chart-bar-item:focus-within .app-analytics-chart-tooltip {
            @apply block;
        }

        .app-analytics-chart-labels {
            @apply mt-2 flex text-[11px] text-gray-500 dark:text-gray-400;
        }

        .app-analytics-chart-label {
            @apply min-w-0 flex-1 truncate text-center;
        }

        .app-analytics-chart-empty {
            @apply rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400;
        }

        .app-link {
            @apply text-sm font-medium text-gray-700 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100;
        }

        .app-button-primary {
            @apply inline-flex items-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-black dark:bg-indigo-500 dark:hover:bg-indigo-400;
        }

        .app-button-secondary {
            @apply inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-800 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800;
        }

        .app-button-danger {
            @apply inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60;
        }

        .app-chip {
            @apply inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200;
        }

        .app-chip-success {
            @apply inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200;
        }

        .app-chip-danger {
            @apply inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900/60 dark:text-red-200;
        }

        .app-chip-warning {
            @apply inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/60 dark:text-amber-200;
        }

        .app-label {
            @apply mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400;
        }

        .app-help {
            @apply mt-1 text-xs text-gray-500 dark:text-gray-400;
        }

        .app-input {
            @apply w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500;
        }

        .app-checkbox {
            @apply rounded border border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-indigo-500 dark:focus:ring-indigo-400;
        }

        .app-card :where(input:not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea) {
            @apply rounded-lg border border-gray-300 bg-white text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500;
        }

        .app-card :where(input[type="checkbox"], input[type="radio"]) {
            @apply rounded border border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-indigo-500 dark:focus:ring-indigo-400;
        }

        .app-card :where(table) {
            @apply min-w-full text-sm;
        }

        .app-card :where(thead) {
            @apply bg-gray-100/70 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300;
        }

        .app-card :where(th) {
            @apply px-4 py-2 text-left font-medium;
        }

        .app-card :where(td) {
            @apply px-4 py-2 align-top;
        }

        .app-card :where(tbody tr) {
            @apply border-t border-gray-200/70 dark:border-gray-800;
        }

        .app-card :where(pre) {
            @apply rounded-lg border border-gray-200 bg-gray-50 p-3 text-gray-800 dark:border-gray-700 dark:bg-gray-950/60 dark:text-gray-200;
        }

    }
</style>
