// Login page: a show / hide button on the password field. Without JavaScript the field still works, with no button.
(() => {
    const iconsUrl = document.querySelector('[data-icons]')?.dataset.icons ?? '';
    const svg = (name) => `<svg class="icon" aria-hidden="true" focusable="false"><use href="${iconsUrl}#${name}"></use></svg>`;

    document.querySelectorAll('.login-page input[type="password"]').forEach((input) => {
        const wrap = document.createElement('span');
        wrap.className = 'login-password';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'login-reveal';
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', 'Show password');
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
