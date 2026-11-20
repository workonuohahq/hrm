type MotionWindow = Window & { refreshHrMotion?: () => void };

const SELECTORS = [
    '#app main > div',
    '#app main section',
    '#app section[id]',
    '#app [data-slot="card"]',
    '#app .rounded-xl.border',
    '#app .rounded-2xl.border',
    '#app form',
].join(',');

export function installHrMotion() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    const motionWindow = window as MotionWindow;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (reducedMotion.matches) {
        document.documentElement.classList.add('motion-reduced');
        return;
    }

    document.documentElement.classList.add('motion-enabled');

    const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                (entry.target as HTMLElement).dataset.motionVisible = 'true';
                currentObserver.unobserve(entry.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' })
        : null;

    let frame = 0;

    const refresh = () => {
        if (frame) window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(() => {
            frame = 0;
            document.querySelectorAll<HTMLElement>(SELECTORS).forEach((element, index) => {
                if (element.dataset.motionPrepared) return;
                if (element.closest('[data-motion="rise"]')) return;

                element.dataset.motion = 'rise';
                element.dataset.motionPrepared = 'true';
                element.style.setProperty('--motion-delay', `${Math.min(index % 5, 4) * 65}ms`);

                if (observer) {
                    observer.observe(element);
                } else {
                    element.dataset.motionVisible = 'true';
                }
            });

            document.querySelectorAll<HTMLElement>('#hero, .auth-layout, [data-auth-layout]').forEach((element) => {
                element.classList.add('motion-ambient');
            });
        });
    };

    motionWindow.refreshHrMotion = refresh;
    refresh();

    const mutations = new MutationObserver((records) => {
        if (records.some((record) => record.addedNodes.length > 0)) refresh();
    });

    mutations.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('inertia:navigate', refresh);
    window.addEventListener('pageshow', refresh);

    reducedMotion.addEventListener('change', (event) => {
        if (event.matches) {
            observer?.disconnect();
            document.documentElement.classList.add('motion-reduced');
            document.querySelectorAll<HTMLElement>('[data-motion]').forEach((element) => {
                element.dataset.motionVisible = 'true';
            });
        } else {
            document.documentElement.classList.remove('motion-reduced');
            refresh();
        }
    });
}

installHrMotion();
