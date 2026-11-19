/* Applications list. On a desktop, a row opens a side panel. On smaller screens, the links open the full page. */
(function () {
    'use strict';

    const drawer = document.getElementById('application-drawer');

    if (!drawer) {
        return;
    }

    const body = drawer.querySelector('[data-drawer-body]');
    const isDesktop = window.matchMedia('(min-width: 1200px)');

    const openDrawer = (link) => {
        window.AGILE.request(link.dataset.drawerUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.text();
            })
            .then((html) => {
                // Rendered and escaped by the server, from this site only.
                body.innerHTML = html;
                drawer.showModal();
            })
            // The full page still works if the panel cannot load.
            .catch(() => {
                window.location.href = link.href;
            });
    };

    document.addEventListener('click', (event) => {
        const row = event.target.closest('.application-row');

        if (!row || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
            return;
        }

        const link = row.querySelector('[data-drawer-url]');
        const onOtherControl = event.target.closest('a, button') && !event.target.closest('[data-drawer-url]');

        if (onOtherControl) {
            return;
        }

        event.preventDefault();

        if (isDesktop.matches) {
            openDrawer(link);
        } else {
            window.location.href = link.href;
        }
    });

    drawer.querySelector('[data-drawer-close]').addEventListener('click', () => drawer.close());
    drawer.addEventListener('click', (event) => {
        if (event.target === drawer) {
            drawer.close();
        }
    });
})();
