import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const confirmMessage = form.getAttribute('data-confirm-submit');
    if (typeof confirmMessage !== 'string' || confirmMessage.trim() === '') {
        return;
    }

    if (!window.confirm(confirmMessage)) {
        event.preventDefault();
    }
});

document.addEventListener('click', function (event) {
    const target = event.target instanceof Element ? event.target.closest('[data-confirm-click]') : null;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    const confirmMessage = target.getAttribute('data-confirm-click');
    if (typeof confirmMessage !== 'string' || confirmMessage.trim() === '') {
        return;
    }

    if (!window.confirm(confirmMessage)) {
        event.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[data-seo-risk-autofill="true"]');
    if (forms.length === 0) {
        return;
    }

    const firstForm = forms[0];
    const optionsUrl = firstForm instanceof HTMLFormElement
        ? (firstForm.getAttribute('data-seo-risk-options-url') || '').trim()
        : '';

    const gscSelects = document.querySelectorAll('select[data-seo-risk-gsc-select]');
    const ga4Selects = document.querySelectorAll('select[data-seo-risk-ga4-select]');
    const gscStatusNodes = document.querySelectorAll('[data-seo-risk-gsc-status]');
    const ga4StatusNodes = document.querySelectorAll('[data-seo-risk-ga4-status]');
    const isPolish = (document.documentElement.lang || '').toLowerCase().startsWith('pl');

    const populateSelect = (select, items) => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const firstOption = select.options[0];
        const placeholderLabel = firstOption ? firstOption.textContent || '' : '';
        const currentValue = (select.getAttribute('data-current-value') || '').trim();

        while (select.options.length > 0) {
            select.remove(0);
        }

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = placeholderLabel;
        select.appendChild(placeholder);

        items.forEach((item) => {
            if (!item || typeof item !== 'object') {
                return;
            }

            const value = typeof item.value === 'string' ? item.value.trim() : '';
            if (value === '') {
                return;
            }

            const option = document.createElement('option');
            option.value = value;
            option.textContent = typeof item.label === 'string' && item.label.trim() !== ''
                ? item.label
                : value;
            select.appendChild(option);
        });

        if (currentValue !== '') {
            select.value = currentValue;
        }
    };

    const updateStatus = (nodes, text, isError) => {
        nodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            node.textContent = text;
            node.classList.remove('text-red-600', 'dark:text-red-300');
            node.classList.add('text-gray-500', 'dark:text-gray-400');
            if (isError) {
                node.classList.remove('text-gray-500', 'dark:text-gray-400');
                node.classList.add('text-red-600', 'dark:text-red-300');
            }
        });
    };

    if (optionsUrl !== '') {
        fetch(optionsUrl, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`SEO options request failed (${response.status}).`);
                }

                return response.json();
            })
            .then((payload) => {
                const gscItems = Array.isArray(payload?.gsc?.items) ? payload.gsc.items : [];
                const ga4Items = Array.isArray(payload?.ga4?.items) ? payload.ga4.items : [];
                const gscConnected = payload?.gsc?.connected === true;
                const ga4Connected = payload?.ga4?.connected === true;
                const gscError = typeof payload?.gsc?.error === 'string' ? payload.gsc.error.trim() : '';
                const ga4Error = typeof payload?.ga4?.error === 'string' ? payload.ga4.error.trim() : '';

                gscSelects.forEach((select) => populateSelect(select, gscItems));
                ga4Selects.forEach((select) => populateSelect(select, ga4Items));

                if (gscError !== '') {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Nie udało się pobrać listy usług GSC. Odłącz i podłącz integrację ponownie.' : 'Could not load GSC sites list. Reconnect the integration.',
                        true
                    );
                } else if (!gscConnected) {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Aby wybrać usługę GSC, najpierw podłącz Google Search Console.' : 'Connect Google Search Console first to select a site.',
                        false
                    );
                } else if (gscItems.length === 0) {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Brak dostępnych usług GSC dla podłączonego konta Google.' : 'No GSC sites available for the connected Google account.',
                        false
                    );
                } else {
                    updateStatus(gscStatusNodes, '', false);
                }

                if (ga4Error !== '') {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Nie udało się pobrać listy usług GA4. Odłącz i podłącz integrację ponownie.' : 'Could not load GA4 properties list. Reconnect the integration.',
                        true
                    );
                } else if (!ga4Connected) {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Aby wybrać usługę GA4, najpierw podłącz Google Analytics 4.' : 'Connect Google Analytics 4 first to select a property.',
                        false
                    );
                } else if (ga4Items.length === 0) {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Brak dostępnych usług GA4 dla podłączonego konta Google.' : 'No GA4 properties available for the connected Google account.',
                        false
                    );
                } else {
                    updateStatus(ga4StatusNodes, '', false);
                }
            })
            .catch(() => {
                updateStatus(
                    gscStatusNodes,
                    isPolish ? 'Nie udało się pobrać listy usług GSC.' : 'Could not load GSC sites list.',
                    true
                );
                updateStatus(
                    ga4StatusNodes,
                    isPolish ? 'Nie udało się pobrać listy usług GA4.' : 'Could not load GA4 properties list.',
                    true
                );
            });
    }

    forms.forEach((form) => {
        const monitorSelect = form.querySelector('[data-seo-risk-monitor]');
        const domainInput = form.querySelector('[data-seo-risk-domain]');
        const gscSelect = form.querySelector('[data-seo-risk-gsc-select]');

        if (!(monitorSelect instanceof HTMLSelectElement) || !(domainInput instanceof HTMLInputElement) || !(gscSelect instanceof HTMLSelectElement)) {
            return;
        }

        const applySuggestedValues = () => {
            const selected = monitorSelect.options[monitorSelect.selectedIndex];
            if (!selected) {
                domainInput.value = '';
                return;
            }

            const domain = (selected.getAttribute('data-monitor-domain') || '').trim().toLowerCase();
            if (domain === '') {
                domainInput.value = '';
                return;
            }

            domainInput.value = domain;

            if (gscSelect.value.trim() !== '') {
                return;
            }

            const expectedValue = `sc-domain:${domain}`;
            const matchingOption = Array.from(gscSelect.options).find((option) => option.value.toLowerCase() === expectedValue.toLowerCase());
            if (matchingOption) {
                gscSelect.value = matchingOption.value;
            }
        };

        monitorSelect.addEventListener('change', applySuggestedValues);
        applySuggestedValues();
    });
});
