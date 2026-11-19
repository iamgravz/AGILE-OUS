/* The docs layout: highlights the section being read. Without JavaScript the contents links still work. */
(function () {
    'use strict';

    const links = [...document.querySelectorAll('[data-docs-toc] a')];
    const side = document.querySelector('[data-docs-side]');
    const panel = document.querySelector('[data-docs-panel]');
    const footer = document.querySelector('.site-footer');

    // A section counts as being read once its heading passes this share of the screen.
    const READING_LINE = 0.15;

    panel?.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            panel.open = false;
        }
    });

    if (links.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const headings = [...new Set(links.map((link) => decodeURIComponent(link.hash.slice(1))))]
        .map((id) => document.getElementById(id))
        .filter(Boolean);

    if (headings.length === 0) {
        return;
    }

    let current = null;
    let chosen = null;

    const keepInView = (link) => {
        if (!side || !side.contains(link)) {
            return;
        }

        const box = side.getBoundingClientRect();
        const row = link.getBoundingClientRect();

        if (row.top < box.top) {
            side.scrollTop -= box.top - row.top;
        } else if (row.bottom > box.bottom) {
            side.scrollTop += row.bottom - box.bottom;
        }
    };

    const highlight = () => {
        // At the very bottom the last sections can never reach the line, so the lowest heading on screen counts.
        const atEnd = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2;
        const line = atEnd ? window.innerHeight : window.innerHeight * READING_LINE;
        const reading = chosen || headings.filter((heading) => heading.getBoundingClientRect().top <= line).pop() || headings[0];

        if (reading === current) {
            return;
        }

        current = reading;
        links.forEach((link) => {
            if (decodeURIComponent(link.hash.slice(1)) === reading.id) {
                link.setAttribute('aria-current', 'location');
                keepInView(link);
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    const observer = new IntersectionObserver(highlight, { rootMargin: `0px 0px -${100 - READING_LINE * 100}% 0px` });

    headings.forEach((heading) => observer.observe(heading));

    if (footer !== null) {
        new IntersectionObserver(highlight, { threshold: [0, 1] }).observe(footer);
    }

    // A section someone clicks stays highlighted, even a short one near the end, until they scroll on their own.
    links.forEach((link) => link.addEventListener('click', () => {
        chosen = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        highlight();
    }));

    ['wheel', 'touchmove', 'keydown'].forEach((type) => window.addEventListener(type, () => {
        chosen = null;
    }, { passive: true }));

    highlight();
})();
