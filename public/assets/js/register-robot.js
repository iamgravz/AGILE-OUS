// Registration steps with Iskobot: a show / hide button on each password field.
// Without JavaScript the fields still work; they just have no button.
(() => {
    const iconsUrl = document.querySelector('[data-icons]')?.dataset.icons ?? '';
    const svg = (name) => `<svg class="icon" aria-hidden="true" focusable="false"><use href="${iconsUrl}#${name}"></use></svg>`;

    document.querySelectorAll('.iskobot-card input[type="password"]').forEach((input) => {
        const wrap = document.createElement('span');
        wrap.className = 'iskobot-password';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const label = document.querySelector(`label[for="${input.id}"]`)?.textContent.replace('*', '').trim() || 'password';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'iskobot-reveal';
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', `Show ${label.toLowerCase()}`);
        button.innerHTML = svg('eye');
        wrap.appendChild(button);

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.innerHTML = svg(show ? 'eye-off' : 'eye');
        });
    });
})();
