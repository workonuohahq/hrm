import { router } from '@inertiajs/react';

const SELECTORS = [
    '#app main > div',
    '#app main section',
    '#app section[id]',
    '#app [data-slot="card"]',
    '#app .rounded-xl.border',
    '#app .rounded-2xl.border',
    '#app form',
].join(',');

type MotionElement = HTMLElement & {
    dataset: DOMStringMap & {
        motion?: string;
        motionPrepared?: string;
        motionVisible?: string;
    };
};

export function installHrMotion() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return;
    if (document.documentElement.dataset.hrmMotionInstalled === 'true') return;

    document.documentElement.dataset.hrmMotionInstalled = 'true';

    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    let observer: IntersectionObserver | null = null;

    const setMotionMode = () => {
        const reduced = preference.matches;
        document.documentElement.classList.toggle('motion-reduced', reduced);
        document.documentElement.classList.toggle('motion-enabled', !reduced);

        if (reduced) {
            observer?.disconnect();
            observer = null;
            document.querySelectorAll<MotionElement>('[data-motion]').forEach((element) => {
                element.dataset.motionVisible = 'true';
            });
            return;
        }

        observer?.disconnect();
        observer = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries, currentObserver) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    (entry.target as MotionElement).dataset.motionVisible = 'true';
                    currentObserver.unobserve(entry.target);
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' })
            : null;

        scan(document);
    };

    const prepare = (element: MotionElement, index: number) => {
        if (element.dataset.motionPrepared) return;

        element.dataset.motion = 'rise';
        element.dataset.motionPrepared = 'true';
        element.style.setProperty('--motion-delay', `${Math.min(index % 5, 4) * 65}ms`);

        if (preference.matches || !observer) {
            element.dataset.motionVisible = 'true';
        } else {
            observer.observe(element);
        }
    };

    const scan = (root: ParentNode) => {
        if (preference.matches) return;

        const matches: Element[] = [];
        if (root instanceof Element && root.matches(SELECTORS)) matches.push(root);
        root.querySelectorAll(SELECTORS).forEach((element) => matches.push(element));

        matches.forEach((element, index) => prepare(element as MotionElement, index));
        document.querySelectorAll<HTMLElement>('#hero, .auth-layout, [data-auth-layout]').forEach((element) => {
            element.classList.add('motion-ambient');
        });
    };

    const animatePage = () => {
        const main = document.querySelector<HTMLElement>('#app main');
        if (!main || preference.matches) return;

        main.classList.remove('hrm-page-enter');
        void main.offsetWidth;
        main.classList.add('hrm-page-enter');
        scan(document);
    };

    setMotionMode();

    const mutations = new MutationObserver((records) => {
        if (preference.matches) return;
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node instanceof Element) scan(node);
            });
        });
    });

    if (document.body) {
        mutations.observe(document.body, { childList: true, subtree: true });
    }

    preference.addEventListener('change', () => {
        setMotionMode();
    });

    router.on('navigate', () => {
        animatePage();
    });

    window.addEventListener('pageshow', () => scan(document));
}

installHrMotion();
