/* AGILE front-end behavior. Vanilla JS, no libraries. Public API: window.AGILE. */
(function () {
    'use strict';

    const SPRITE = '/assets/img/icons.svg';
    const SIDEBAR_FIXED_FROM = '(min-width: 1200px)';

    function createIcon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        svg.setAttribute('class', 'icon');
        svg.setAttribute('aria-hidden', 'true');
        use.setAttribute('href', SPRITE + '#' + name);
        svg.append(use);

        return svg;
    }

    /* ---------- Fetch helper ---------- */

    function request(url, options = {}) {
        const headers = new Headers(options.headers || {});
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        if (token) {
            headers.set('X-CSRF-Token', token);
        }
        headers.set('X-Requested-With', 'XMLHttpRequest');

        return fetch(url, { credentials: 'same-origin', ...options, headers });
    }

    /* ---------- Saved choices: storage can be blocked, and the page still works ---------- */

    function remember(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (error) {
            // The choice lasts until the next page.
        }
    }

    /* ---------- Mobile navigation ---------- */

    function setExpanded(toggle, target, open) {
        toggle.setAttribute('aria-expanded', String(open));
        target.classList.toggle('is-open', open);
    }

    function initMobileNav() {
        const toggle = document.querySelector('[data-nav-toggle]');
        const nav = toggle && document.getElementById(toggle.getAttribute('aria-controls'));

        if (!nav) {
            return;
        }

        toggle.addEventListener('click', () => {
            setExpanded(toggle, nav, toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                setExpanded(toggle, nav, false);
                toggle.focus();
            }
        });
    }

    /* ---------- Dashboard sidebar ---------- */

    function initSidebar() {
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');
        const content = document.querySelector('.app-content');

        if (!toggle || !sidebar) {
            return;
        }

        const setOpen = (open, returnFocus) => {
            setExpanded(toggle, sidebar, open);
            backdrop.hidden = !open;
            // Keeps Tab inside the open sidebar, since it covers the page on small screens.
            content.inert = open;
            document.querySelector('.skip-link').inert = open;

            if (open) {
                sidebar.querySelector('a, button')?.focus();
            } else if (returnFocus) {
                toggle.focus();
            }
        };

        const isFixed = () => window.matchMedia(SIDEBAR_FIXED_FROM).matches;
        const showRailState = () => {
            const rail = document.documentElement.getAttribute('data-sidebar') === 'rail';

            toggle.setAttribute('aria-label', isFixed() ? (rail ? 'Expand sidebar' : 'Collapse sidebar') : 'Open menu');
            toggle.setAttribute('aria-expanded', String(isFixed() ? !rail : false));
        };

        toggle.addEventListener('click', () => {
            if (!isFixed()) {
                setOpen(true, false);

                return;
            }

            const rail = document.documentElement.getAttribute('data-sidebar') !== 'rail';

            if (rail) {
                document.documentElement.setAttribute('data-sidebar', 'rail');
            } else {
                document.documentElement.removeAttribute('data-sidebar');
            }

            remember('agile-sidebar', rail ? 'rail' : 'full');
            showRailState();
        });
        showRailState();
        backdrop.addEventListener('click', () => setOpen(false, true));
        sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setOpen(false, true));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                setOpen(false, true);
            }
        });

        window.matchMedia(SIDEBAR_FIXED_FROM).addEventListener('change', () => {
            setOpen(false, false);
            showRailState();
        });
    }

    /* ---------- Dropdowns: notifications and user menu ---------- */

    function closeDropdown(toggle, returnFocus) {
        toggle.setAttribute('aria-expanded', 'false');
        document.getElementById(toggle.getAttribute('aria-controls')).hidden = true;

        if (returnFocus) {
            toggle.focus();
        }
    }

    function openDropdowns(except) {
        return [...document.querySelectorAll('[data-dropdown][aria-expanded="true"]')]
            .filter((toggle) => toggle !== except);
    }

    function initDropdowns() {
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-dropdown]');

            openDropdowns(toggle)
                .filter((open) => !open.parentElement.contains(event.target))
                .forEach((open) => closeDropdown(open, false));

            if (toggle) {
                const open = toggle.getAttribute('aria-expanded') === 'true';

                if (open) {
                    closeDropdown(toggle, false);
                } else {
                    toggle.setAttribute('aria-expanded', 'true');
                    document.getElementById(toggle.getAttribute('aria-controls')).hidden = false;
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                openDropdowns(null).forEach((open) => closeDropdown(open, true));
            }
        });

        document.addEventListener('focusin', (event) => {
            openDropdowns(null)
                .filter((open) => !open.parentElement.contains(event.target))
                .forEach((open) => closeDropdown(open, false));
        });
    }

    /* ---------- Confirm modal ---------- */

    function confirmDialog({ title, message, actionLabel, tone }) {
        const dialog = document.getElementById('confirm-modal');

        if (!dialog) {
            return Promise.resolve(true);
        }

        const confirmButton = dialog.querySelector('[data-modal-confirm]');
        const cancelButton = dialog.querySelector('[data-modal-cancel]');

        dialog.querySelector('#confirm-modal-title').textContent = title || 'Please confirm';
        dialog.querySelector('#confirm-modal-message').textContent = message;
        confirmButton.textContent = actionLabel || 'Confirm';
        confirmButton.className = 'btn ' + (tone === 'primary' ? 'btn--primary' : 'btn--danger');

        return new Promise((resolve) => {
            let result = false;
            const controller = new AbortController();
            const { signal } = controller;

            confirmButton.addEventListener('click', () => {
                result = true;
                dialog.close();
            }, { signal });
            cancelButton.addEventListener('click', () => dialog.close(), { signal });
            // A click on the dialog element itself lands on the backdrop, because the content fills the box.
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            }, { signal });
            dialog.addEventListener('close', () => {
                controller.abort();
                resolve(result);
            }, { signal });

            dialog.showModal();
            cancelButton.focus();
        });
    }

    /* ---------- Form submit: confirm first, then the double-submit guard ---------- */

    function markSubmitting(form, submitter) {
        const button = submitter || form.querySelector('[type="submit"], button:not([type])');

        form.dataset.submitting = 'true';
        button?.classList.add('is-loading');
        button?.setAttribute('aria-busy', 'true');
        // Deferred, because a button disabled now would drop its name and value from the submitted data.
        setTimeout(() => {
            form.querySelectorAll('[type="submit"], button:not([type])').forEach((item) => {
                item.disabled = true;
            });
        }, 0);
    }

    function initForms() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            const submitter = event.submitter;
            const source = submitter?.dataset.confirm ? submitter : form;

            if (form.dataset.submitting === 'true') {
                event.preventDefault();

                return;
            }

            if (source.dataset.confirm && form.dataset.confirmed !== 'true') {
                event.preventDefault();
                confirmDialog({
                    title: source.dataset.confirmTitle,
                    message: source.dataset.confirm,
                    actionLabel: source.dataset.confirmAction,
                    tone: source.dataset.confirmTone,
                }).then((confirmed) => {
                    if (confirmed) {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit(submitter || undefined);
                    }
                });

                return;
            }

            delete form.dataset.confirmed;

            if (!event.defaultPrevented && !('noGuard' in form.dataset)) {
                markSubmitting(form, submitter);
            }
        });

        // The Back button can restore a page exactly as it was left, with its buttons still locked.
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) {
                return;
            }
            document.querySelectorAll('form[data-submitting]').forEach((form) => {
                delete form.dataset.submitting;
                form.querySelectorAll('[type="submit"], button:not([type])').forEach((button) => {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.removeAttribute('aria-busy');
                });
            });
        });
    }

    /* ---------- Flash dismiss and toasts ---------- */

    function initDismiss() {
        document.addEventListener('click', (event) => {
            event.target.closest('[data-dismiss]')?.closest('.alert, .toast')?.remove();
        });
    }

    function toast(message, tone = 'info', duration = 5000) {
        const region = document.getElementById('toast-region');

        if (!region) {
            return;
        }

        const icons = { success: 'check-circle', warning: 'alert', danger: 'x-circle', info: 'info' };
        const item = document.createElement('div');
        const text = document.createElement('p');
        const close = document.createElement('button');

        item.className = 'toast toast--' + tone;
        text.className = 'toast__text';
        text.textContent = message;
        close.type = 'button';
        close.className = 'icon-btn';
        close.dataset.dismiss = '';
        close.setAttribute('aria-label', 'Dismiss');
        close.append(createIcon('close'));
        item.append(createIcon(icons[tone] || 'info'), text, close);
        region.append(item);

        setTimeout(() => item.remove(), duration);
    }

    function initToastButtons() {
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-toast]');

            if (trigger) {
                toast(trigger.dataset.toast, trigger.dataset.toastTone);
            }
        });
    }

    /* ---------- Copy buttons ---------- */

    function initCopyButtons() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-copy]');

            if (!button) {
                return;
            }

            const target = document.getElementById(button.dataset.copy);
            const text = target.textContent.trim();
            const selectIt = () => {
                const range = document.createRange();
                range.selectNodeContents(target);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                toast('Press Ctrl+C to copy the selected code.', 'info');
            };

            // The clipboard needs HTTPS or localhost, so older setups fall back to selecting the text.
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => toast('Copied to your clipboard.', 'success')).catch(selectIt);
            } else {
                selectIt();
            }
        });
    }

    /* ---------- Tabs ---------- */

    function initTabs() {
        document.querySelectorAll('[data-tabs]').forEach((container) => {
            const tabs = [...container.querySelectorAll('[role="tab"]')];

            const select = (tab, focus) => {
                tabs.forEach((item) => {
                    const selected = item === tab;
                    item.setAttribute('aria-selected', String(selected));
                    item.tabIndex = selected ? 0 : -1;
                    document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
                });

                if (focus) {
                    tab.focus();
                }
            };

            container.addEventListener('click', (event) => {
                const tab = event.target.closest('[role="tab"]');

                if (tab) {
                    select(tab, false);
                }
            });

            container.addEventListener('keydown', (event) => {
                const index = tabs.indexOf(event.target);
                const moves = {
                    ArrowRight: (index + 1) % tabs.length,
                    ArrowLeft: (index - 1 + tabs.length) % tabs.length,
                    Home: 0,
                    End: tabs.length - 1,
                };

                if (index !== -1 && event.key in moves) {
                    event.preventDefault();
                    select(tabs[moves[event.key]], true);
                }
            });
        });
    }

    /* ---------- Notification bell: loads when opened ---------- */

    function initNotifications() {
        const toggle = document.querySelector('[data-notifications-url]');

        if (!toggle) {
            return;
        }

        const menu = document.getElementById(toggle.getAttribute('aria-controls'));
        const list = menu.querySelector('[data-notification-list]');
        const status = menu.querySelector('[data-notification-status]');
        const readAll = menu.querySelector('[data-notifications-read-all]');
        const count = toggle.querySelector('[data-notification-count]');

        const setUnread = (unread) => {
            count.textContent = String(unread);
            count.hidden = unread === 0;
            readAll.hidden = unread === 0;
            toggle.setAttribute('aria-label', unread > 0 ? 'Notifications, ' + unread + ' unread' : 'Notifications');
        };

        const markRead = (id) => request('/notifications/' + id + '/read', { method: 'POST' });

        const renderItem = (item) => {
            const row = document.createElement('li');
            const entry = document.createElement(item.link ? 'a' : 'button');
            const title = document.createElement('span');
            const time = document.createElement('span');

            entry.className = 'notification' + (item.read ? '' : ' notification--unread');
            title.className = 'notification__title';
            title.textContent = item.title;
            time.className = 'notification__time';
            time.textContent = item.time;

            if (item.link) {
                entry.href = item.link;
            } else {
                entry.type = 'button';
            }

            if (!item.read) {
                const badge = document.createElement('span');
                badge.className = 'notification__unread';
                badge.textContent = 'New';
                entry.append(badge);
            }

            entry.append(title, time);
            entry.addEventListener('click', (event) => {
                if (item.read) {
                    return;
                }

                event.preventDefault();
                // Opening the link matters more than the read mark, so a failed request still continues.
                markRead(item.id).catch(() => null).finally(() => {
                    if (item.link) {
                        window.location.href = item.link;
                    } else {
                        load();
                    }
                });
            });

            row.append(entry);

            return row;
        };

        const showStatus = (message) => {
            status.textContent = message;
            status.hidden = false;
            list.hidden = true;
        };

        const load = () => {
            showStatus('Loading...');

            return request(toggle.dataset.notificationsUrl, { headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    return response.json();
                })
                .then((data) => {
                    setUnread(data.unread);
                    list.replaceChildren(...data.items.map(renderItem));

                    if (data.items.length === 0) {
                        showStatus('No notifications yet.');
                    } else {
                        status.hidden = true;
                        list.hidden = false;
                    }
                })
                .catch(() => showStatus('We could not load your notifications. Please try again.'));
        };

        // Runs before the shared dropdown handler, so aria-expanded still shows the old state.
        toggle.addEventListener('click', () => {
            if (toggle.getAttribute('aria-expanded') === 'false') {
                load();
            }
        });

        readAll.addEventListener('click', () => {
            request('/notifications/read-all', { method: 'POST' })
                .then(load)
                // The button is now hidden, so focus moves back to the bell instead of being lost.
                .then(() => toggle.focus())
                .catch(() => toast('We could not update your notifications.', 'danger'));
        });
    }

    /* ---------- Stacked tables: keep table roles once CSS changes their display ---------- */

    function initStackedTables() {
        const roles = { TABLE: 'table', THEAD: 'rowgroup', TBODY: 'rowgroup', TR: 'row', TH: 'columnheader', TD: 'cell' };

        document.querySelectorAll('.table--stack').forEach((table) => {
            [table, ...table.querySelectorAll('thead, tbody, tr, th, td')].forEach((element) => {
                element.setAttribute('role', roles[element.tagName]);
            });
        });
    }

    /* ---------- File inputs: drop zone highlight and preview ---------- */

    function formatSize(bytes) {
        return bytes < 1048576
            ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
            : (bytes / 1048576).toFixed(1) + ' MB';
    }

    function renderFilePreview(input, list) {
        list.replaceChildren();

        [...input.files].forEach((file) => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            const size = document.createElement('span');
            const fileIcon = createIcon('file');

            item.className = 'file-preview__item';
            name.className = 'file-preview__name';
            name.textContent = file.name;
            size.className = 'file-preview__size';
            size.textContent = formatSize(file.size);
            item.append(fileIcon, name, size);
            list.append(item);

            // Drawn on a canvas because the CSP does not allow blob: image URLs.
            if (file.type.startsWith('image/')) {
                createImageBitmap(file, { resizeHeight: 96 }).then((bitmap) => {
                    const canvas = document.createElement('canvas');
                    canvas.className = 'file-preview__thumb';
                    canvas.width = bitmap.width;
                    canvas.height = bitmap.height;
                    canvas.getContext('2d').drawImage(bitmap, 0, 0);
                    fileIcon.replaceWith(canvas);
                }).catch(() => {
                    // The file icon stays when the image cannot be decoded.
                });
            }
        });
    }

    /** Mirrors FileStorageService for quick feedback. The server still makes the decision. */
    function fileProblem(file, input) {
        const allowed = (input.dataset.allowedTypes || '').split(',').filter(Boolean);
        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        const maxMb = Number(input.dataset.maxMb || 0);

        if (allowed.length > 0 && !allowed.includes(extension)) {
            const labels = allowed.filter((type) => type !== 'jpeg').join(', ').toUpperCase();

            return 'This file type is not allowed. Please upload one of: ' + labels + '.';
        }

        if (file.size === 0) {
            return 'This file is empty.';
        }

        return maxMb > 0 && file.size > maxMb * 1048576 ? 'This file is too large. The limit is ' + maxMb + ' MB.' : '';
    }

    function showFileProblem(input, message) {
        const box = document.getElementById(input.dataset.fileError);

        input.closest('.field')?.classList.toggle('field--error', message !== '');

        if (message === '') {
            input.removeAttribute('aria-invalid');
        } else {
            input.setAttribute('aria-invalid', 'true');
        }

        if (box) {
            box.querySelector('[data-file-error-text]').textContent = message;
            box.hidden = message === '';
        }
    }

    function initFileInputs() {
        document.querySelectorAll('input[type="file"][data-file-preview]').forEach((input) => {
            const list = document.getElementById(input.dataset.filePreview);
            const zone = input.closest('.dropzone');

            input.addEventListener('change', () => {
                const problem = [...input.files].map((file) => fileProblem(file, input)).find(Boolean) || '';

                if (problem !== '') {
                    input.value = '';
                }

                showFileProblem(input, problem);
                renderFilePreview(input, list);
            });

            if (zone) {
                input.addEventListener('dragenter', () => zone.classList.add('is-dragover'));
                ['dragleave', 'drop'].forEach((name) => {
                    input.addEventListener(name, () => zone.classList.remove('is-dragover'));
                });
            }
        });
    }

        initMobileNav();
    initSidebar();
    initDropdowns();
    initForms();
    initDismiss();
    initToastButtons();
    initCopyButtons();
    initTabs();
    initNotifications();
    initStackedTables();
    initFileInputs();

    window.AGILE = { request, toast, confirm: confirmDialog };
})();

/* Press / or Ctrl+K to jump to the top bar search. / is ignored while typing in a field. */
(() => {
    'use strict';

    document.addEventListener('keydown', (event) => {
        const search = document.getElementById('topbar-search');
        const isSlash = event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey;
        const isCtrlK = event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey) && !event.altKey;

        if (search === null || (!isSlash && !isCtrlK)) {
            return;
        }

        if (event.target.closest('input, textarea, select, [contenteditable="true"], dialog[open]')) {
            return;
        }

        event.preventDefault();
        search.focus();
    });

    /* ---------- Glass: the bars turn to glass once content scrolls under them ---------- */

    const noGlass = document.querySelector('[data-no-glass]') !== null
        || /^\/(welfare|my-welfare-cases|ask-for-help|verify|credentials)(\/|$)/.test(window.location.pathname);

    document.documentElement.classList.toggle('no-glass', noGlass);

    const siteHeader = document.querySelector('.site-header');
    const glassBars = [
        { element: siteHeader, className: 'glass--dark', from: 24 },
        { element: document.querySelector('.topbar'), className: 'glass', from: 1 },
    ].filter((bar) => bar.element !== null);

    const updateGlass = () => {
        glassBars.forEach((bar) => bar.element.classList.toggle(bar.className, !noGlass && window.scrollY >= bar.from));
    };

    window.addEventListener('scroll', updateGlass, { passive: true });
    updateGlass();

    // Keeps the hero and docs column against the header. An open phone menu is not the header's usual height.
    if (siteHeader !== null && 'ResizeObserver' in window) {
        new ResizeObserver(() => {
            if (siteHeader.querySelector('.site-nav.is-open') === null) {
                document.documentElement.style.setProperty('--site-header-height', `${siteHeader.getBoundingClientRect().height}px`);
            }
        }).observe(siteHeader);
    }
})();
