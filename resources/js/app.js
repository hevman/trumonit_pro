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

                gscSelects.forEach((select) => populateSelect(select, gscItems));
                ga4Selects.forEach((select) => populateSelect(select, ga4Items));
            })
            .catch(() => {
                // Keep selects with placeholder when options cannot be loaded.
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
                return;
            }

            const domain = (selected.getAttribute('data-monitor-domain') || '').trim().toLowerCase();
            if (domain === '') {
                return;
            }

            if (domainInput.value.trim() === '') {
                domainInput.value = domain;
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

        monitorSelect.addEventListener('change', applySuggestedValues);
    });
});
