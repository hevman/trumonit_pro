// Monitor template hints for onboarding (and other pages that embed the same DOM hooks).
//
// This replaces large inline scripts in Blade templates. The Blade side provides:
// - data-monitor-template-hints-root on a container
// - a JSON payload in <script type="application/json" data-monitor-template-hints-templates>...</script>
// - standard element ids (template/url/frequency + hint boxes)

function parseJsonPayload(root) {
    const node = root.querySelector('script[type="application/json"][data-monitor-template-hints-templates]');
    if (!(node instanceof HTMLScriptElement)) {
        return [];
    }

    try {
        const raw = (node.textContent || '').trim();
        if (raw === '') return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function normalizeKey(value) {
    return typeof value === 'string' ? value.trim() : '';
}

function pickLocale(root) {
    const fromRoot = (root.getAttribute('data-locale') || '').trim().toLowerCase();
    if (fromRoot !== '') return fromRoot;

    const fromHtml = (document.documentElement.lang || '').trim().toLowerCase();
    return fromHtml || 'en';
}

function isPolish(locale) {
    return locale.startsWith('pl');
}

function safeText(value) {
    return typeof value === 'string' ? value : '';
}

function intFromAttr(root, name, fallback) {
    const raw = (root.getAttribute(name) || '').trim();
    const parsed = parseInt(raw, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function getFrequencyOptions(select) {
    if (!(select instanceof HTMLSelectElement)) {
        return [];
    }
    const values = [];
    Array.from(select.options).forEach((opt) => {
        const parsed = parseInt(String(opt.value || ''), 10);
        if (Number.isFinite(parsed)) values.push(parsed);
    });
    values.sort((a, b) => a - b);
    return values;
}

function clampFrequencyToOptions(select, desiredMinutes, minMinutes) {
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    const desired = parseInt(String(desiredMinutes), 10);
    if (!Number.isFinite(desired)) {
        return;
    }

    const options = getFrequencyOptions(select);
    if (options.length === 0) {
        return;
    }

    // Respect plan minimum first.
    const target = Math.max(minMinutes, desired);

    // Pick the first option >= target, otherwise the last option.
    let picked = options[options.length - 1];
    for (let i = 0; i < options.length; i++) {
        if (options[i] >= target) {
            picked = options[i];
            break;
        }
    }

    select.value = String(picked);
}

function guessTemplateKey(rawUrl) {
    if (typeof rawUrl !== 'string') return null;

    const value = rawUrl.trim().toLowerCase();
    if (value === '') return null;

    let hostname = '';
    try {
        hostname = new URL(value).hostname.toLowerCase();
    } catch (e) {
        hostname = '';
    }

    if (value.includes('/cron') || value.includes('/heartbeat')) {
        return 'cron_heartbeat';
    }

    if (value.includes('/checkout') || value.includes('/cart')) {
        return 'ecommerce_checkout';
    }

    if (value.includes('/tracking') || value.includes('/track')) {
        return 'logistics_tracking';
    }

    if (value.includes('/login') || value.includes('/signin')) {
        return 'login_journey';
    }

    if (value.includes('/health') || value.includes('/api/')) {
        return 'api_health';
    }

    if (value.includes('/cdn-cgi/trace')) {
        return 'cloudflare_edge';
    }

    if (hostname.startsWith('origin.') || hostname.includes('.origin.')) {
        return 'origin_bypass';
    }

    return 'website_uptime';
}

function initRoot(root) {
    const locale = pickLocale(root);
    const polish = isPolish(locale);
    const minFrequencyMinutes = Math.max(1, intFromAttr(root, 'data-min-frequency-minutes', 1));

    const templates = parseJsonPayload(root);
    const byKey = {};
    templates.forEach((tpl) => {
        if (!tpl || typeof tpl !== 'object') return;
        const key = normalizeKey(tpl.key);
        if (key !== '') byKey[key] = tpl;
    });

    const url = root.querySelector('#url');
    const templateSelect = root.querySelector('#template');
    const suggestion = root.querySelector('#template-suggestion');
    const selectedState = root.querySelector('#template-selected-state');
    const purpose = root.querySelector('#template-purpose');
    const cardsWrap = root.querySelector('#template-cards');
    const guideBox = root.querySelector('#template-guide-box');
    const guideFor = root.querySelector('#template-guide-for');
    const guideAlert = root.querySelector('#template-guide-alert');
    const frequency = root.querySelector('#frequency_minutes');

    if (!(url instanceof HTMLInputElement) || !(templateSelect instanceof HTMLSelectElement)) {
        return;
    }

    let userPickedTemplate = false;
    let userPickedFrequency = false;

    const t = {
        selectedPrefix: polish ? 'Wybrany typ: ' : 'Selected type: ',
        suggestedPrefix: polish ? 'Sugerowany typ: ' : 'Suggested: ',
        purposePrefix: polish ? 'Do czego sluzy: ' : 'Use case: ',
        guideForPrefix: polish ? 'Co monitor robi: ' : 'What it does: ',
        guideAlertPrefix: polish ? 'Kiedy alarmuje: ' : 'When it alerts: ',
        fallbackFor: polish
            ? 'Szablon ustawia domyslne parametry pod konkretny przypadek uzycia.'
            : 'Template applies defaults for a specific use case.',
        alertJourney: polish
            ? 'Alarmuje, gdy kroki scenariusza nie przejda lub zabraknie oczekiwanych elementow UI.'
            : 'Alerts when journey steps fail or expected UI elements are missing.',
        alertPort: polish
            ? 'Alarmuje, gdy wskazany port TCP nie odpowiada lub polaczenie jest odrzucane.'
            : 'Alerts when TCP port does not respond or connection is refused.',
        alertHeartbeat: polish
            ? 'Alarmuje, gdy brak sygnalu heartbeat w oczekiwanym oknie czasowym.'
            : 'Alerts when heartbeat signal is missing in expected time window.',
        alertHttp: polish
            ? 'Alarmuje przy niedostepnosci URL, timeoutach i nieoczekiwanym kodzie HTTP.'
            : 'Alerts on URL downtime, timeouts, and unexpected HTTP status.',
        alertGeneric: polish
            ? 'Alarmuje zgodnie z regulami wybranego typu monitora.'
            : 'Alerts according to selected monitor type rules.',
    };

    if (cardsWrap instanceof HTMLElement) {
        cardsWrap.classList.remove('hidden');
    }

    function setActiveCard(key) {
        if (!(cardsWrap instanceof HTMLElement)) return;
        const cards = cardsWrap.querySelectorAll('[data-template-key]');
        cards.forEach((card) => {
            if (!(card instanceof HTMLElement)) return;
            const isActive = card.getAttribute('data-template-key') === key;
            if (isActive) {
                card.classList.add('ring-2', 'ring-indigo-500', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-500/10');
            } else {
                card.classList.remove('ring-2', 'ring-indigo-500', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-500/10');
            }
        });
    }

    function updateSelectedState() {
        if (!(selectedState instanceof HTMLElement)) return;
        const opt = templateSelect.options[templateSelect.selectedIndex];
        selectedState.textContent = opt ? `${t.selectedPrefix}${opt.text}` : '';
    }

    function updatePurpose() {
        if (!(purpose instanceof HTMLElement)) return;
        const selected = byKey[templateSelect.value] || null;
        const description = selected && typeof selected.description === 'string' ? selected.description.trim() : '';
        if (description === '') {
            purpose.textContent = '';
            purpose.classList.add('hidden');
            return;
        }
        purpose.textContent = `${t.purposePrefix}${description}`;
        purpose.classList.remove('hidden');
    }

    function updateGuide() {
        if (!(guideFor instanceof HTMLElement) || !(guideAlert instanceof HTMLElement) || !(guideBox instanceof HTMLElement)) {
            return;
        }

        const selected = byKey[templateSelect.value] || null;
        let forText = selected && typeof selected.description === 'string' ? selected.description.trim() : '';
        if (forText === '') forText = t.fallbackFor;

        let alertText = '';
        const defaults = selected && selected.defaults && typeof selected.defaults === 'object' ? selected.defaults : null;
        const scanMode = defaults ? String(defaults.scan_mode || '').toLowerCase() : '';
        if (scanMode === 'journey') alertText = t.alertJourney;
        else if (scanMode === 'port') alertText = t.alertPort;
        else if (scanMode === 'heartbeat') alertText = t.alertHeartbeat;
        else if (scanMode !== '') alertText = t.alertHttp;

        if (alertText === '') alertText = t.alertGeneric;

        guideFor.textContent = `${t.guideForPrefix}${forText}`;
        guideAlert.textContent = `${t.guideAlertPrefix}${alertText}`;
        guideBox.classList.remove('hidden');
    }

    function maybeApplyTemplateDefaultsToFrequency() {
        if (!(frequency instanceof HTMLSelectElement) || userPickedFrequency) return;

        const selected = byKey[templateSelect.value] || null;
        const defaults = selected && selected.defaults && typeof selected.defaults === 'object' ? selected.defaults : null;
        if (!defaults) return;

        const desired = defaults.frequency_minutes;
        if (desired === null || typeof desired === 'undefined') return;

        clampFrequencyToOptions(frequency, desired, minFrequencyMinutes);
    }

    function clearSuggestion() {
        if (!(suggestion instanceof HTMLElement)) return;
        suggestion.textContent = '';
        suggestion.classList.add('hidden');
    }

    function showSuggestion(templateKey) {
        if (!(suggestion instanceof HTMLElement)) return;

        let matchedLabel = '';
        Array.from(templateSelect.options).forEach((opt) => {
            if (opt.value === templateKey) matchedLabel = opt.text || '';
        });
        if (matchedLabel.trim() === '') {
            clearSuggestion();
            return;
        }

        suggestion.textContent = `${t.suggestedPrefix}${matchedLabel}.`;
        suggestion.classList.remove('hidden');
    }

    function syncAll() {
        setActiveCard(templateSelect.value);
        updateSelectedState();
        updatePurpose();
        updateGuide();
        maybeApplyTemplateDefaultsToFrequency();
    }

    function maybeApplySuggestion() {
        const key = guessTemplateKey(url.value);
        if (!key) {
            clearSuggestion();
            return;
        }

        if (!userPickedTemplate) {
            const hasOption = Array.from(templateSelect.options).some((opt) => opt.value === key);
            if (hasOption) templateSelect.value = key;
        }

        syncAll();

        if (!userPickedTemplate) showSuggestion(key);
        else clearSuggestion();
    }

    if (frequency instanceof HTMLSelectElement) {
        frequency.addEventListener('change', () => {
            userPickedFrequency = true;
        });
        frequency.addEventListener('input', () => {
            userPickedFrequency = true;
        });
    }

    url.addEventListener('change', maybeApplySuggestion);
    url.addEventListener('blur', maybeApplySuggestion);
    url.addEventListener('input', () => {
        if (url.value.length >= 8) maybeApplySuggestion();
    });

    templateSelect.addEventListener('change', () => {
        userPickedTemplate = true;
        clearSuggestion();
        syncAll();
    });

    if (cardsWrap instanceof HTMLElement) {
        cardsWrap.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-template-key]') : null;
            if (!(target instanceof HTMLElement)) return;
            const key = target.getAttribute('data-template-key') || '';
            if (key.trim() === '') return;

            userPickedTemplate = true;
            templateSelect.value = key;
            clearSuggestion();
            syncAll();
        });
    }

    // Initial sync on load.
    syncAll();
    maybeApplySuggestion();
}

document.addEventListener('DOMContentLoaded', function () {
    const roots = document.querySelectorAll('[data-monitor-template-hints-root]');
    roots.forEach((root) => {
        if (root instanceof HTMLElement) initRoot(root);
    });
});

