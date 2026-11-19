/* Email editor. Without JavaScript the plain HTML box still works, and the server cleans it either way. */
(function () {
    'use strict';

    const form = document.querySelector('[data-email-form]');
    const holder = form?.querySelector('[data-email-editor]');
    const textarea = form?.querySelector('[data-email-body]');
    const tools = form?.querySelector('[data-email-tools]');
    const toggle = form?.querySelector('[data-email-toggle]');
    const preview = document.querySelector('[data-email-preview]');

    // The preview frame stays out of the way until there is something to show.
    if (preview) {
        preview.hidden = true;
        form?.querySelector('[formtarget="email-preview"]')?.addEventListener('click', () => {
            preview.hidden = false;
        });
    }

    if (!holder || !textarea || typeof window.Quill === 'undefined') {
        return;
    }

    holder.hidden = false;
    tools.hidden = false;
    textarea.hidden = true;

    // Quill uses Tab to indent, which would trap keyboard users inside the editor. Returning true leaves it to the browser.
    const leaveEditor = () => true;
    const indentFormats = ['indent', 'list'];

    const quill = new window.Quill(holder, {
        theme: 'snow',
        // Mirrors the server's allow-list, so people see what will actually be saved.
        formats: ['bold', 'italic', 'list', 'link'],
        modules: {
            keyboard: {
                bindings: {
                    tab: { key: 'Tab', handler: leaveEditor },
                    indent: { key: 'Tab', format: indentFormats, handler: leaveEditor },
                    outdent: { key: 'Tab', shiftKey: true, format: indentFormats, handler: leaveEditor },
                },
            },
            toolbar: [['bold', 'italic'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }]],
        },
    });
    const toolbar = holder.previousElementSibling;
    let edited = false;
    let usingHtml = false;

    const loadFromTextarea = () => {
        // Not dangerouslyPasteHTML, which also moves keyboard focus into the editor as the page loads.
        quill.setContents(quill.clipboard.convert({ html: textarea.value }), 'silent');
        quill.history.clear();
        edited = false;
    };

    loadFromTextarea();
    quill.root.setAttribute('aria-label', 'Email text');
    holder.querySelector('.ql-tooltip input')?.setAttribute('aria-label', 'Link address');

    // Quill's toolbar buttons have no names of their own for screen readers.
    const names = { 'ql-bold': 'Bold', 'ql-italic': 'Italic', 'ql-link': 'Link' };

    toolbar.querySelectorAll('button').forEach((button) => {
        const name = Object.keys(names).find((className) => button.classList.contains(className));

        if (name) {
            button.setAttribute('aria-label', names[name]);
        } else if (button.classList.contains('ql-list')) {
            button.setAttribute('aria-label', button.value === 'ordered' ? 'Numbered list' : 'Bulleted list');
        }
    });

    quill.on('text-change', (delta, old, source) => {
        edited = edited || source === 'user';
    });

    // Only text changed in the editor is taken from it, so simply saving keeps the details block and the button.
    const syncToTextarea = () => {
        if (!usingHtml && edited) {
            // Quill writes every space as &nbsp; here, which would stop long lines from wrapping.
            textarea.value = quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
        }
    };

    form.addEventListener('submit', syncToTextarea, true);

    toggle.addEventListener('click', () => {
        syncToTextarea();
        usingHtml = !usingHtml;

        if (!usingHtml) {
            loadFromTextarea();
        }

        holder.hidden = usingHtml;
        toolbar.hidden = usingHtml;
        textarea.hidden = !usingHtml;
        toggle.textContent = usingHtml ? 'Use the rich text editor' : 'Edit HTML';
    });
})();
