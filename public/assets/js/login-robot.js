/* Volt, the login robot. Decorative: the form works the same without this file. */
(function () {
    'use strict';

    const robot = document.querySelector('[data-robot]');

    if (!robot) {
        return;
    }

    const MESSAGES = {
        idle: "Hi. I'm Volt. I guard this form.",
        email: 'Beep. Which email are you?',
        shy: 'A secret? Say no more. *turns around*',
        peek: "It's showing, but I'm still not looking.",
        ready: 'Go on. Press the button.',
        sad: "Hmm. That didn't work. Try again?",
    };

    const bubble = robot.querySelector('[data-robot-bubble]');
    const eyes = robot.querySelector('[data-robot-eyes]');
    const email = robot.querySelector('#email');
    const password = robot.querySelector('#password');
    const toggle = robot.querySelector('[data-password-toggle]');
    const form = email.form;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const failedLogin = document.querySelector('.alert--danger') !== null;

    const setMood = (mood, message) => {
        robot.dataset.mood = mood;
        bubble.textContent = MESSAGES[message];
    };

    const lookAt = (x, y) => {
        eyes.style.setProperty('--eye-x', x + 'px');
        eyes.style.setProperty('--eye-y', y + 'px');
    };

    /* ---------- Reacting to the form ---------- */

    if (failedLogin) {
        setMood('sad', 'sad');
    }

    email.addEventListener('focus', () => setMood('idle', 'email'));
    password.addEventListener('focus', () => setMood('shy', password.type === 'text' ? 'peek' : 'shy'));

    form.addEventListener('focusout', (event) => {
        if (!form.contains(event.relatedTarget)) {
            setMood('idle', email.value !== '' && password.value !== '' ? 'ready' : 'idle');
        }
    });

    // The eyes follow the end of what is being typed.
    email.addEventListener('input', () => {
        if (!reducedMotion.matches) {
            lookAt(Math.min(email.value.length, 30) / 30 * 10 - 5, 2);
        }
    });

    /* ---------- Show or hide the password ---------- */

    const setVisible = (visible) => {
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        toggle.querySelector('[data-eye-open]').hidden = visible;
        toggle.querySelector('[data-eye-closed]').hidden = !visible;
    };

    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';

        setVisible(visible);
        setMood('shy', visible ? 'peek' : 'shy');
    });

    // Keeps browsers from saving the password as plain form text.
    form.addEventListener('submit', () => setVisible(false));

    /* ---------- The eyes follow the pointer ---------- */

    document.addEventListener('pointermove', (event) => {
        if (reducedMotion.matches || robot.dataset.mood === 'shy') {
            return;
        }

        const box = eyes.getBoundingClientRect();
        const limit = (value) => Math.max(-1, Math.min(1, value / 200));

        lookAt(limit(event.clientX - box.left - box.width / 2) * 5, limit(event.clientY - box.top - box.height / 2) * 3);
    });
})();
