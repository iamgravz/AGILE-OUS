// Apply page. "Applying for" fills the second dropdown with that role's open places: the 7 committees for Committee
// Head, Deputy Committee Head and Committee Member, or the posts in The Source Code. The upload fields follow the
// choice: none until it is made (and, for a position, the committee or post chosen); then the COR, plus the grades for
// a position. Without JavaScript both dropdowns list everything, grouped, every field shows, and the server checks.
(() => {
    const applying = document.querySelector('[data-applying-select]');
    const positionField = document.querySelector('[data-position-field]');
    const position = document.querySelector('[data-position-select]');
    const positionLabel = document.querySelector('[data-position-label]');
    const uploads = document.querySelector('[data-uploads]');
    const docs = document.querySelector('[data-required-docs]');
    const gradesField = document.querySelector('[data-upload="grades"]');
    const corInput = document.querySelector('[data-upload="cor"] input[type="file"]');

    if (!applying || !position || !uploads || !docs || !gradesField || !corInput) {
        return;
    }

    // Every group, kept aside so the dropdown can be refilled with one role's group at a time.
    const groups = [...position.querySelectorAll('optgroup')].map((group) => group.cloneNode(true));
    const placeholder = position.querySelector('option[value=""]').cloneNode(true);
    const gradesInput = gradesField.querySelector('input[type="file"]');

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

    const year = document.querySelector('[data-year-select]');
    const semester = document.querySelector('[data-semester]')?.dataset.semester ?? '';
    const gradesLabel = gradesField.querySelector('[data-grades-label]');

    // AGILE's requirements, as MembershipService::gradesForYear(): a position needs grades, except a 1st year in a 1st
    // semester; a 1st year in a later semester gives the 1st semester's.
    const updateUploads = () => {
        const role = applying.value;
        const forPosition = role !== '' && role !== 'general';
        const ready = role === 'general' || (forPosition && position.value !== '');
        const firstYear = year?.value === '1st Year';
        const needsGrades = forPosition && !(firstYear && semester === '1st');
        const label = firstYear && semester !== '1st' ? '1st semester copy of grades' : 'Previous semester copy of grades';

        uploads.toggleAttribute('data-pending', !ready);
        corInput.required = ready;
        gradesField.hidden = !needsGrades;
        gradesInput.required = ready && needsGrades;
        gradesLabel.textContent = label;
        docs.textContent = needsGrades ? `Current COR and ${label.charAt(0).toLowerCase()}${label.slice(1)}` : 'Current COR';

        if (!needsGrades) {
            gradesInput.value = '';
        }
    };

    applying.addEventListener('change', () => {
        fillPositions();
        updateUploads();
    });
    position.addEventListener('change', updateUploads);
    year?.addEventListener('change', updateUploads);

    fillPositions();
    updateUploads();
})();
