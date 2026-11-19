(() => {
    'use strict';

    document.querySelectorAll('[data-char-count]').forEach((field) => {
        const output = document.getElementById(field.dataset.charCount);
        const max = field.getAttribute('maxlength');
        const update = () => {
            output.textContent = `${field.value.length} of ${max} characters`;
        };

        field.addEventListener('input', update);
        update();
    });
})();
