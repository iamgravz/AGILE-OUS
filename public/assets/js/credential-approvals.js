/* Keeps the "Approve selected" dialog's count in step with the checkboxes. The server rechecks everything. */
(() => {
    'use strict';

    const form = document.getElementById('bulk-credentials');

    if (form === null) {
        return;
    }

    const update = () => {
        const count = document.querySelectorAll('[data-credential-select]:checked').length;
        form.dataset.confirm = count === 0
            ? 'Nothing is selected yet. Tick the ones you checked, or cancel.'
            : `${count} selected will be approved, and each member is emailed once.`;
        form.dataset.confirmTitle = `Approve ${count} selected?`;
    };

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-credential-select]')) {
            update();
        }
    });
    update();
})();
