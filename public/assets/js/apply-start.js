// Apply page. "Applying for" fills the second dropdown with that role's open places: the 7 committees for Committee
// Head, Deputy Committee Head and Committee Member, or the posts in The Source Code. The Requirements dropdown (AGILE's
// table) shows the documents for the row chosen and shows or hides the grades upload. Without JavaScript both
// dropdowns list everything, grouped, and the server checks that the pair and the files match.
(() => {
    const applying = document.querySelector('[data-applying-select]');
    const positionField = document.querySelector('[data-position-field]');
    const position = document.querySelector('[data-position-select]');
    const positionLabel = document.querySelector('[data-position-label]');
    const requirement = document.querySelector('[data-requirement-select]');
    const docs = document.querySelector('[data-required-docs]');
    const gradesField = document.querySelector('[data-upload="grades"]');

    if (!applying || !position || !requirement || !docs || !gradesField) {
        return;
    }

    // Every group, kept aside so the dropdown can be refilled with one role's group at a time.
    const groups = [...position.querySelectorAll('optgroup')].map((group) => group.cloneNode(true));
    const placeholder = position.querySelector('option[value=""]').cloneNode(true);
    const gradesInput = gradesField.querySelector('input[type="file"]');
    const gradesLabel = gradesField.querySelector('[data-grades-label]');

    const fillPositions = () => {
        const role = applying.value;
        const kept = position.value;
        const group = groups.find((g) => g.dataset.role === role);

        position.replaceChildren(placeholder.cloneNode(true));

        if (group) {
            [...group.children].forEach((option) => position.append(option.cloneNode(true)));
        }

        position.value = [...position.options].some((o) => o.value === kept) ? kept : '';
        // One place left: choose it for them.
        if (position.value === '' && position.options.length === 2) {
            position.selectedIndex = 1;
        }

        const needsPlace = role !== '' && role !== 'general';
        positionField.hidden = !needsPlace;
        position.required = needsPlace;
        positionLabel.textContent = role === 'source_code' ? 'Post in The Source Code' : 'Committee';
    };

    const updateRequirement = () => {
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

    // A suggestion only: General Membership starts at "General members", a position at its own row. A 1st year
    // changes it to their row.
    const suggestRequirement = () => {
        const role = applying.value;
        const current = requirement.value;
        const firstYear = ['first_year_first_sem', 'first_year_second_sem'];

        if (role === 'general' && !['general', ...firstYear].includes(current)) {
            requirement.value = 'general';
        } else if (role !== '' && role !== 'general' && !firstYear.includes(current)) {
            requirement.value = ['committee_head', 'deputy', 'committee_member'].includes(role) ? 'committee' : 'applicant';
        }
    };

    applying.addEventListener('change', () => {
        fillPositions();
        suggestRequirement();
        updateRequirement();
    });
    requirement.addEventListener('change', updateRequirement);

    fillPositions();
    updateRequirement();
})();
