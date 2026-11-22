// Apply page: the Requirements line follows the position chosen. Without JavaScript the full table inside still shows.
(() => {
    const select = document.querySelector('[data-requirements-select]');
    const text = document.querySelector('[data-requirements-text]');

    if (!select || !text) {
        return;
    }

    const update = () => {
        text.textContent = select.selectedOptions[0]?.dataset.requirements ?? 'Choose what you are applying for above.';
    };

    select.addEventListener('change', update);
    update();
})();
