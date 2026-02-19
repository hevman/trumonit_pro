(function () {
    const storageKey = 'TruMonit_theme';
    const root = document.documentElement;

    const getStoredTheme = function () {
        try {
            return localStorage.getItem(storageKey);
        } catch (error) {
            return null;
        }
    };

    const setStoredTheme = function (theme) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            // ignore
        }
    };

    const systemTheme = function () {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    };

    const applyTheme = function (theme) {
        const normalized = theme === 'dark' ? 'dark' : 'light';

        root.classList.toggle('dark', normalized === 'dark');
        root.dataset.theme = normalized;
        root.style.colorScheme = normalized;
    };

    const refreshThemeUi = function () {
        const isDark = root.classList.contains('dark');
        const icon = isDark ? '\u2600' : '\u263D';
        const locale = (document.documentElement.lang || 'en').toLowerCase();
        const polish = locale.startsWith('pl');
        const label = isDark
            ? (polish ? 'Tryb jasny' : 'Light mode')
            : (polish ? 'Tryb ciemny' : 'Dark mode');

        document.querySelectorAll('[data-theme-icon]').forEach(function (element) {
            element.textContent = icon;
        });

        document.querySelectorAll('[data-theme-label]').forEach(function (element) {
            element.textContent = label;
        });
    };

    const toggleTheme = function () {
        const nextTheme = root.classList.contains('dark') ? 'light' : 'dark';
        applyTheme(nextTheme);
        setStoredTheme(nextTheme);
        refreshThemeUi();
    };

    const initialTheme = (function () {
        const stored = getStoredTheme();

        if (stored === 'dark' || stored === 'light') {
            return stored;
        }

        return systemTheme();
    })();

    applyTheme(initialTheme);

    window.toggleTheme = toggleTheme;
    window.refreshThemeIcon = refreshThemeUi;

    document.addEventListener('click', function (event) {
        const toggleButton = event.target && event.target.closest('[data-theme-toggle]');

        if (!toggleButton) {
            return;
        }

        event.preventDefault();
        toggleTheme();
    });

    document.addEventListener('DOMContentLoaded', refreshThemeUi);
})();
