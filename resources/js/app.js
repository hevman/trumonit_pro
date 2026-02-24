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
