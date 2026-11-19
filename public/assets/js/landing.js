/* The public home page. The page is complete without this file. Everything here only adds to what is already visible. */
(function () {
    'use strict';

    const root = document.documentElement;
    const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
    const PAUSE_KEY = 'agile-landing-paused';

    /* ---------- The Pause animation button (WCAG 2.2.2). The grid is the only thing that loops ---------- */

    const motionToggle = document.querySelector('[data-motion-toggle]');
    const hero = document.querySelector('[data-hero]');

    if (motionToggle !== null && hero !== null) {
        let paused = false;

        try {
            paused = localStorage.getItem(PAUSE_KEY) === '1';
        } catch (error) {
            // Storage can be blocked. Motion then simply starts each time.
        }

        const show = () => {
            root.classList.toggle('motion-paused', paused);
            motionToggle.textContent = paused ? 'Play animation' : 'Pause animation';
        };

        motionToggle.hidden = calm.matches;
        show();

        motionToggle.addEventListener('click', () => {
            paused = !paused;
            show();

            try {
                localStorage.setItem(PAUSE_KEY, paused ? '1' : '0');
            } catch (error) {
                // Only remembering the choice failed.
            }
        });

        calm.addEventListener('change', (event) => {
            motionToggle.hidden = event.matches;
        });

        new IntersectionObserver((entries) => {
            hero.classList.toggle('is-offscreen', !entries[0].isIntersecting);
        }).observe(hero);
    }

    /* ---------- Committee spotlight ---------- */

    document.querySelectorAll('[data-spotlight]').forEach((tile) => {
        let frame = null;

        tile.addEventListener('pointermove', (event) => {
            if (frame !== null) {
                return;
            }

            frame = window.requestAnimationFrame(() => {
                const box = tile.getBoundingClientRect();

                tile.style.setProperty('--spot-x', `${event.clientX - box.left}px`);
                tile.style.setProperty('--spot-y', `${event.clientY - box.top}px`);
                frame = null;
            });
        }, { passive: true });
    });

    if (!calm.matches) {
        /* ---------- One entrance at a time: each effect waits for the one before it to finish ---------- */

        let nextFree = performance.now() + (hero !== null ? 700 : 0);

        const takeTurn = (effect, length) => {
            const now = performance.now();
            const wait = Math.max(0, nextFree - now);

            nextFree = now + wait + length;
            window.setTimeout(effect, wait);
        };

        const once = (element, length, effect, threshold = 0.1) => {
            new IntersectionObserver((entries, observer) => {
                if (entries[0].isIntersecting) {
                    observer.disconnect();
                    takeTurn(effect, length);
                }
            }, { threshold }).observe(element);
        };

        /* ---------- Count-ups. Zero stays plain, and the final number is what the HTML already shows ---------- */

        const numbers = document.querySelector('[data-numbers]');

        if (numbers !== null) {
            const counters = Array.from(numbers.querySelectorAll('[data-count]')).filter((counter) => Number(counter.dataset.count) > 0);
            const DURATION = 1200;

            once(numbers, DURATION, () => {
                const start = performance.now();

                const tick = (time) => {
                    const progress = Math.min((time - start) / DURATION, 1);
                    const eased = 1 - (1 - progress) ** 3;

                    counters.forEach((counter) => {
                        counter.textContent = String(Math.round(Number(counter.dataset.count) * eased));
                    });

                    if (progress < 1) {
                        window.requestAnimationFrame(tick);
                    }
                };

                window.requestAnimationFrame(tick);
            }, 0.5);
        }

        /* ---------- Section reveals. Only what is still below the fold is ever hidden ---------- */

        const belowFold = (element) => element.getBoundingClientRect().top > window.innerHeight;

        document.querySelectorAll('[data-reveal]').forEach((section) => {
            if (belowFold(section)) {
                section.classList.add('reveal-pending');

                once(section, 500, () => {
                    section.classList.add('reveal-active');
                    section.classList.remove('reveal-pending');
                    window.setTimeout(() => section.classList.remove('reveal-active'), 600);
                });
            }
        });

        /* ---------- The How to join line draws once ---------- */

        const join = document.querySelector('[data-join]');

        if (join !== null && belowFold(join)) {
            join.classList.add('join-pending');

            once(join, 1200, () => {
                join.classList.add('is-drawn');
                join.classList.remove('join-pending');
            }, 0.2);
        }
    }

    /* ---------- Gallery lightbox. Without this, each photo is a plain link to the image ---------- */

    const gallery = document.querySelector('[data-gallery]');

    if (gallery !== null) {
        const links = Array.from(gallery.querySelectorAll('a'));
        const dialog = document.createElement('dialog');
        const image = document.createElement('img');
        const caption = document.createElement('p');
        const controls = document.createElement('div');
        let index = 0;

        const control = (text, action) => {
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'btn btn--on-dark btn--sm';
            button.textContent = text;
            button.addEventListener('click', action);
            controls.append(button);
        };

        const show = (value) => {
            index = (value + links.length) % links.length;
            image.src = links[index].href;
            image.alt = links[index].querySelector('img').alt;
            caption.textContent = links[index].dataset.caption;
        };

        dialog.className = 'landing-lightbox';
        dialog.setAttribute('aria-label', 'Photo viewer');
        controls.className = 'cluster';
        control('Previous', () => show(index - 1));
        control('Next', () => show(index + 1));
        control('Close', () => dialog.close());
        dialog.append(image, caption, controls);
        document.body.append(dialog);

        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                show(index - 1);
            } else if (event.key === 'ArrowRight') {
                show(index + 1);
            }
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        links.forEach((link, position) => {
            link.addEventListener('click', (event) => {
                if (event.ctrlKey || event.metaKey || event.shiftKey) {
                    return;
                }

                event.preventDefault();
                show(position);
                dialog.showModal();
            });
        });
    }

    /* ---------- Scroll progress under the header ---------- */

    const header = document.querySelector('.site-header');

    if (header !== null) {
        const bar = document.createElement('div');
        let queued = false;

        const paint = () => {
            const range = document.documentElement.scrollHeight - window.innerHeight;

            bar.style.transform = `scaleX(${range > 0 ? Math.min(window.scrollY / range, 1) : 0})`;
            queued = false;
        };

        const queue = () => {
            if (!queued) {
                queued = true;
                window.requestAnimationFrame(paint);
            }
        };

        bar.className = 'landing-progress';
        bar.setAttribute('aria-hidden', 'true');
        header.append(bar);
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue, { passive: true });
        paint();
    }
})();
