/* CMS editor. Without JavaScript the plain body textarea still works, and the server purifies it either way. */
(function () {
    'use strict';

    const form = document.querySelector('[data-cms-form]');

    if (!form) {
        return;
    }

    /* ---------- Event fields only for events ---------- */

    const typeSelect = form.querySelector('[data-cms-type]');
    const eventFields = form.querySelector('[data-cms-event]');
    const syncEvent = () => {
        eventFields.hidden = typeSelect.value !== 'event';
    };

    typeSelect.addEventListener('change', syncEvent);
    syncEvent();

    /* ---------- Link name follows the title until someone edits it ---------- */

    const title = form.querySelector('#title');
    const slug = form.querySelector('#slug');
    const makeSlug = (text) => text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 200);
    let slugTouched = slug.value !== '' && slug.value !== makeSlug(title.value);

    slug.addEventListener('input', () => {
        slugTouched = slug.value !== '';
    });
    title.addEventListener('input', () => {
        if (!slugTouched) {
            slug.value = makeSlug(title.value);
        }
    });

    /* ---------- Rich text body ---------- */

    const holder = form.querySelector('[data-cms-editor]');
    const textarea = form.querySelector('[data-cms-body]');
    const editorError = form.querySelector('[data-cms-editor-error]');

    if (typeof window.Quill === 'undefined') {
        return;
    }

    const showEditorError = (message) => {
        editorError.textContent = message;
        editorError.hidden = message === '';
    };

    const uploadImage = (quill) => {
        const picker = document.createElement('input');
        picker.type = 'file';
        picker.accept = '.jpg,.jpeg,.png';

        picker.addEventListener('change', () => {
            const file = picker.files[0];

            if (!file) {
                return;
            }

            const data = new FormData();
            data.append('image', file);
            showEditorError('');

            window.AGILE.request('/cms/media', { method: 'POST', body: data })
                .then((response) => response.json())
                .then((result) => {
                    if (!result.url) {
                        throw new Error(result.error || 'The image could not be uploaded.');
                    }

                    const at = (quill.getSelection(true) || { index: quill.getLength() }).index;
                    quill.insertEmbed(at, 'image', result.url, 'user');
                })
                .catch((error) => showEditorError(error.message));
        });

        picker.click();
    };

    holder.hidden = false;
    textarea.hidden = true;

    // Quill uses Tab to indent, which would trap keyboard users inside the editor. Returning true leaves it to the browser.
    const leaveEditor = () => true;
    const indentFormats = ['blockquote', 'indent', 'list'];

    const quill = new window.Quill(holder, {
        theme: 'snow',
        // Mirrors the server's allow-list, so people see what will actually be saved.
        formats: ['header', 'bold', 'italic', 'list', 'link', 'blockquote', 'image'],
        modules: {
            keyboard: {
                bindings: {
                    tab: { key: 'Tab', handler: leaveEditor },
                    indent: { key: 'Tab', format: indentFormats, handler: leaveEditor },
                    outdent: { key: 'Tab', shiftKey: true, format: indentFormats, handler: leaveEditor },
                },
            },
            toolbar: {
                container: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'link', 'image'],
                ],
                handlers: {
                    image() {
                        uploadImage(this.quill);
                    },
                },
            },
        },
    });

    // Quill would otherwise keep the code inside these tags as visible text.
    const Delta = window.Quill.import('delta');
    ['SCRIPT', 'STYLE'].forEach((tag) => quill.clipboard.addMatcher(tag, () => new Delta()));
    // Same rule as the server: only our own images. Others would show here, then vanish on save.
    quill.clipboard.addMatcher('IMG', (node, delta) => (/^\/media\/[a-f0-9]{32}$/.test(node.getAttribute('src') || '') ? delta : new Delta()));

    quill.root.setAttribute('aria-label', 'Body');
    // Not dangerouslyPasteHTML, which also moves keyboard focus into the editor as the page loads.
    quill.setContents(quill.clipboard.convert({ html: textarea.value }), 'silent');
    quill.history.clear();

    // Quill's toolbar buttons have no names of their own for screen readers.
    const names = {
        'ql-bold': 'Bold',
        'ql-italic': 'Italic',
        'ql-blockquote': 'Quote',
        'ql-link': 'Link',
        'ql-image': 'Insert image',
    };

    form.querySelectorAll('.ql-toolbar button').forEach((button) => {
        const name = Object.keys(names).find((className) => button.classList.contains(className));

        if (name) {
            button.setAttribute('aria-label', names[name]);
        } else if (button.classList.contains('ql-list')) {
            button.setAttribute('aria-label', button.value === 'ordered' ? 'Numbered list' : 'Bulleted list');
        }
    });

    form.querySelectorAll('.ql-toolbar .ql-picker-label').forEach((label) => {
        label.setAttribute('aria-label', 'Heading level');
    });

    form.addEventListener('submit', () => {
        // Quill writes every space as &nbsp; here, which would stop long lines from wrapping.
        textarea.value = quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    }, true);
})();
