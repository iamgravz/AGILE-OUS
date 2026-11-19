/* Settings: the rubric's weight total, updated as people type. The server checks it again on save. */
(function () {
    'use strict';

    const total = document.querySelector('[data-rubric-total]');
    const note = document.querySelector('[data-rubric-note]');

    if (!total || !note) {
        return;
    }

    const weights = [...document.querySelectorAll('input[name^="rubric_weight"]')];

    const update = () => {
        const sum = weights.reduce((running, input) => running + (parseInt(input.value, 10) || 0), 0);

        total.textContent = String(sum);
        note.hidden = sum === 100;
    };

    weights.forEach((input) => input.addEventListener('input', update));
})();
