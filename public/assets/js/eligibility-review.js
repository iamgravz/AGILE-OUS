/* "Use this value" only fills in the form. Nothing is saved until the reviewer presses Save verification. */
(() => {
    'use strict';

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ai-use]');

        if (!button) {
            return;
        }

        const form = button.closest('form');
        const radio = form.querySelector(`input[name="${button.dataset.aiUse}"][value="${button.dataset.aiValue}"]`);

        radio.checked = true;
        radio.focus();
        form.querySelector('[data-ai-used]').value = '1';
    });
})();
