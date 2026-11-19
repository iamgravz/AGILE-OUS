/* Applies the saved theme and sidebar state before the page paints. Blocking on purpose; a file, because the CSP blocks inline scripts. */
(function () {
    'use strict';

    const root = document.documentElement;
    let theme = null;
    let sidebar = null;

    try {
        theme = localStorage.getItem('agile-theme');
        sidebar = localStorage.getItem('agile-sidebar');
    } catch (error) {
        // Storage can be blocked. The defaults below still work.
    }

    if (theme !== 'light' && theme !== 'dark') {
        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    root.setAttribute('data-theme', theme);
    root.classList.add('js');

    if (sidebar === 'rail') {
        root.setAttribute('data-sidebar', 'rail');
    }
})();
