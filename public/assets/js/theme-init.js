/* Applies the saved sidebar state before the page paints. The site is light only. Blocking on purpose; a file, because the CSP blocks inline scripts. */
(function () {
    'use strict';

    const root = document.documentElement;
    let sidebar = null;

    try {
        sidebar = localStorage.getItem('agile-sidebar');
        // Dark mode was removed: forget a choice saved before.
        localStorage.removeItem('agile-theme');
    } catch (error) {
        // Storage can be blocked. The default below still works.
    }

    root.classList.add('js');

    if (sidebar === 'rail') {
        root.setAttribute('data-sidebar', 'rail');
    }
})();
