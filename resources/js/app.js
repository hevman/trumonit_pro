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

    const optionsState = {
        gsc: {
            connected: false,
            items: [],
            error: '',
        },
        ga4: {
            connected: false,
            items: [],
            error: '',
        },
    };

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

    const stateSyncers = [];
    const syncAllFormStates = () => {
        stateSyncers.forEach((syncState) => syncState());
    };

    forms.forEach((form) => {
        const monitorSelect = form.querySelector('[data-seo-risk-monitor]');
        const domainInput = form.querySelector('[data-seo-risk-domain]');
        const nameInput = form.querySelector('[data-seo-risk-name]');
        const gscSelect = form.querySelector('[data-seo-risk-gsc-select]');
        const ga4Select = form.querySelector('[data-seo-risk-ga4-select]');
        const useGscInput = form.querySelector('input[type="checkbox"][name="use_gsc"]');
        const useGa4Input = form.querySelector('input[type="checkbox"][name="use_ga4"]');
        const submitButton = form.querySelector('[data-seo-risk-submit]');
        const formHint = form.querySelector('[data-seo-risk-form-hint]');
        let nameManuallyEdited = false;

        if (!(monitorSelect instanceof HTMLSelectElement) || !(domainInput instanceof HTMLInputElement) || !(gscSelect instanceof HTMLSelectElement)) {
            return;
        }

        if (nameInput instanceof HTMLInputElement) {
            nameInput.addEventListener('input', () => {
                nameManuallyEdited = true;
            });
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

            if (nameInput instanceof HTMLInputElement && !nameManuallyEdited && nameInput.value.trim() === '') {
                const monitorLabel = (selected.getAttribute('data-monitor-label') || '').trim();
                if (monitorLabel !== '') {
                    nameInput.value = monitorLabel;
                }
            }

            if (gscSelect.value.trim() !== '') {
                return;
            }

            const expectedValue = `sc-domain:${domain}`;
            const matchingOption = Array.from(gscSelect.options).find((option) => option.value.toLowerCase() === expectedValue.toLowerCase());
            if (matchingOption) {
                gscSelect.value = matchingOption.value;
            }
        };

        const syncCreateFormState = () => {
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-seo-risk-create-form')) {
                return;
            }

            if (!(submitButton instanceof HTMLButtonElement) || submitButton.hasAttribute('disabled')) {
                return;
            }

            const useGsc = useGscInput instanceof HTMLInputElement ? useGscInput.checked : false;
            const useGa4 = useGa4Input instanceof HTMLInputElement ? useGa4Input.checked : false;
            const gscReady = optionsState.gsc.connected && optionsState.gsc.items.length > 0;
            const ga4Ready = optionsState.ga4.connected && optionsState.ga4.items.length > 0;

            if (gscSelect instanceof HTMLSelectElement) {
                if (!useGsc) {
                    gscSelect.value = '';
                }
                gscSelect.disabled = !useGsc || !gscReady;
            }

            if (ga4Select instanceof HTMLSelectElement) {
                if (!useGa4) {
                    ga4Select.value = '';
                }
                ga4Select.disabled = !useGa4 || !ga4Ready;
            }

            let blockingMessage = '';
            if (!useGsc && !useGa4) {
                blockingMessage = isPolish
                    ? 'Wybierz przynajmniej jedno zrodlo danych (GSC lub GA4).'
                    : 'Select at least one data source (GSC or GA4).';
            } else if (useGsc && !gscReady) {
                blockingMessage = isPolish
                    ? 'GSC nie jest gotowe. Podlacz integracje i wybierz pozycje z listy.'
                    : 'GSC is not ready. Connect integration and choose item from list.';
            } else if (useGa4 && !ga4Ready) {
                blockingMessage = isPolish
                    ? 'GA4 nie jest gotowe. Podlacz integracje i wybierz pozycje z listy.'
                    : 'GA4 is not ready. Connect integration and choose item from list.';
            } else if (useGsc && gscSelect instanceof HTMLSelectElement && gscSelect.value.trim() === '') {
                blockingMessage = isPolish
                    ? 'Wybierz usluge GSC z listy.'
                    : 'Choose GSC site from list.';
            } else if (useGa4 && ga4Select instanceof HTMLSelectElement && ga4Select.value.trim() === '') {
                blockingMessage = isPolish
                    ? 'Wybierz wlasciwosc GA4 z listy.'
                    : 'Choose GA4 property from list.';
            }

            submitButton.disabled = blockingMessage !== '';
            if (formHint instanceof HTMLElement) {
                formHint.textContent = blockingMessage;
                formHint.classList.remove('text-red-600', 'dark:text-red-300');
                formHint.classList.add('text-gray-500', 'dark:text-gray-400');
                if (blockingMessage !== '') {
                    formHint.classList.remove('text-gray-500', 'dark:text-gray-400');
                    formHint.classList.add('text-red-600', 'dark:text-red-300');
                }
            }
        };

        stateSyncers.push(syncCreateFormState);

        monitorSelect.addEventListener('change', applySuggestedValues);
        monitorSelect.addEventListener('change', syncCreateFormState);
        if (useGscInput instanceof HTMLInputElement) {
            useGscInput.addEventListener('change', syncCreateFormState);
        }
        if (useGa4Input instanceof HTMLInputElement) {
            useGa4Input.addEventListener('change', syncCreateFormState);
        }
        if (gscSelect instanceof HTMLSelectElement) {
            gscSelect.addEventListener('change', syncCreateFormState);
        }
        if (ga4Select instanceof HTMLSelectElement) {
            ga4Select.addEventListener('change', syncCreateFormState);
        }

        applySuggestedValues();
        syncCreateFormState();
    });

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

                optionsState.gsc.connected = gscConnected;
                optionsState.gsc.items = gscItems;
                optionsState.gsc.error = gscError;
                optionsState.ga4.connected = ga4Connected;
                optionsState.ga4.items = ga4Items;
                optionsState.ga4.error = ga4Error;

                gscSelects.forEach((select) => populateSelect(select, gscItems));
                ga4Selects.forEach((select) => populateSelect(select, ga4Items));

                if (gscError !== '') {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Nie udalo sie pobrac listy uslug GSC. Odlacz i podlacz integracje ponownie.' : 'Could not load GSC sites list. Reconnect the integration.',
                        true
                    );
                } else if (!gscConnected) {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Aby wybrac usluge GSC, najpierw podlacz Google Search Console.' : 'Connect Google Search Console first to select a site.',
                        false
                    );
                } else if (gscItems.length === 0) {
                    updateStatus(
                        gscStatusNodes,
                        isPolish ? 'Brak dostepnych uslug GSC dla podlaczonego konta Google.' : 'No GSC sites available for the connected Google account.',
                        false
                    );
                } else {
                    updateStatus(gscStatusNodes, '', false);
                }

                if (ga4Error !== '') {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Nie udalo sie pobrac listy uslug GA4. Odlacz i podlacz integracje ponownie.' : 'Could not load GA4 properties list. Reconnect the integration.',
                        true
                    );
                } else if (!ga4Connected) {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Aby wybrac usluge GA4, najpierw podlacz Google Analytics 4.' : 'Connect Google Analytics 4 first to select a property.',
                        false
                    );
                } else if (ga4Items.length === 0) {
                    updateStatus(
                        ga4StatusNodes,
                        isPolish ? 'Brak dostepnych uslug GA4 dla podlaczonego konta Google.' : 'No GA4 properties available for the connected Google account.',
                        false
                    );
                } else {
                    updateStatus(ga4StatusNodes, '', false);
                }

                syncAllFormStates();
            })
            .catch(() => {
                updateStatus(
                    gscStatusNodes,
                    isPolish ? 'Nie udalo sie pobrac listy uslug GSC.' : 'Could not load GSC sites list.',
                    true
                );
                updateStatus(
                    ga4StatusNodes,
                    isPolish ? 'Nie udalo sie pobrac listy uslug GA4.' : 'Could not load GA4 properties list.',
                    true
                );

                syncAllFormStates();
            });
    } else {
        syncAllFormStates();
    }
});
