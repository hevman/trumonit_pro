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

    const gscDatalist = document.getElementById('seo-risk-gsc-sites');
    const ga4Datalist = document.getElementById('seo-risk-ga4-properties');

    const populateDatalist = (datalist, items) => {
        if (!(datalist instanceof HTMLDataListElement)) {
            return;
        }

        while (datalist.firstChild) {
            datalist.removeChild(datalist.firstChild);
        }

        items.forEach((item) => {
            if (!item || typeof item !== 'object') {
                return;
            }

            const option = document.createElement('option');
            const value = typeof item.value === 'string' ? item.value : '';
            if (value.trim() === '') {
                return;
            }

            option.value = value;
            const label = typeof item.label === 'string' ? item.label : '';
            if (label.trim() !== '') {
                option.label = label;
            }

            datalist.appendChild(option);
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

                populateDatalist(gscDatalist, gscItems);
                populateDatalist(ga4Datalist, ga4Items);
            })
            .catch(() => {
                // Leave manual entry available if Google suggestions cannot be loaded.
            });
    }

    forms.forEach((form) => {
        const monitorSelect = form.querySelector('[data-seo-risk-monitor]');
        const domainInput = form.querySelector('[data-seo-risk-domain]');
        const gscInput = form.querySelector('[data-seo-risk-gsc]');

        if (!(monitorSelect instanceof HTMLSelectElement) || !(domainInput instanceof HTMLInputElement) || !(gscInput instanceof HTMLInputElement)) {
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

            if (gscInput.value.trim() === '') {
                gscInput.value = `sc-domain:${domain}`;
            }
        };

        monitorSelect.addEventListener('change', applySuggestedValues);
    });
});
