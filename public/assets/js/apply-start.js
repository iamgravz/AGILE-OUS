// Apply page: the Requirements dropdown (AGILE's table) shows the documents for the row chosen and shows or hides the
// grades upload. Choosing what to apply for suggests a row. Without JavaScript both upload fields show, and the server
// still checks the row and the files.
(() => {
    const applying = document.querySelector('[data-applying-select]');
    const requirement = document.querySelector('[data-requirement-select]');
    const docs = document.querySelector('[data-required-docs]');
    const gradesField = document.querySelector('[data-upload="grades"]');

    if (!applying || !requirement || !docs || !gradesField) {
        return;
    }

    const gradesInput = gradesField.querySelector('input[type="file"]');
    const gradesLabel = gradesField.querySelector('[data-grades-label]');

    const update = () => {
        const option = requirement.selectedOptions[0];
        const chosen = option && option.value !== '';
        const needsGrades = chosen && option.dataset.grades === '1';

        docs.textContent = chosen ? option.dataset.documents : 'Choose the row that fits you.';
        gradesField.hidden = !needsGrades;
        gradesInput.required = needsGrades;

        if (needsGrades && option.dataset.gradesLabel) {
            gradesLabel.textContent = option.dataset.gradesLabel;
        }

        if (!needsGrades) {
            gradesInput.value = '';
        }
    };

    // A suggestion only: General Membership starts at "General members", a position at "Applicants for any officer or
    // committee position". A 1st year changes it to their row.
    applying.addEventListener('change', () => {
        const kind = applying.selectedOptions[0]?.dataset.kind;
        const current = requirement.value;

        if (kind === 'general' && !['general', 'first_year_first_sem', 'first_year_second_sem'].includes(current)) {
            requirement.value = 'general';
        } else if (kind === 'position' && (current === '' || current === 'general')) {
            requirement.value = 'applicant';
        }

        update();
    });

    requirement.addEventListener('change', update);
    update();
})();
