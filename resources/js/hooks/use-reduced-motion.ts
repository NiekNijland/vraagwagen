import { useSyncExternalStore } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

function subscribe(callback: () => void): () => void {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return () => {};
    }

    const mql = window.matchMedia(QUERY);
    mql.addEventListener('change', callback);

    return () => mql.removeEventListener('change', callback);
}

function getSnapshot(): boolean {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia(QUERY).matches;
}

function getServerSnapshot(): boolean {
    return false;
}

/**
 * Reactively tracks the user's `prefers-reduced-motion` setting. Returns false
 * during SSR and where `matchMedia` is unavailable so the first paint is stable.
 */
export function usePrefersReducedMotion(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
