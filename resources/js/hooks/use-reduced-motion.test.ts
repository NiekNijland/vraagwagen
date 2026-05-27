import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { usePrefersReducedMotion } from './use-reduced-motion';

type Listener = (event: MediaQueryListEvent) => void;

function mockMatchMedia(initial: boolean): { set: (next: boolean) => void } {
    let matches = initial;
    const listeners = new Set<Listener>();

    const mql = {
        get matches() {
            return matches;
        },
        media: '(prefers-reduced-motion: reduce)',
        addEventListener: (_: string, cb: Listener) => listeners.add(cb),
        removeEventListener: (_: string, cb: Listener) => listeners.delete(cb),
    };

    vi.stubGlobal('matchMedia', vi.fn().mockReturnValue(mql));

    return {
        set(next: boolean) {
            matches = next;
            listeners.forEach((cb) => cb({ matches } as MediaQueryListEvent));
        },
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('usePrefersReducedMotion', () => {
    it('reflects the initial media query state', () => {
        mockMatchMedia(true);

        const { result } = renderHook(() => usePrefersReducedMotion());

        expect(result.current).toBe(true);
    });

    it('updates reactively when the preference changes', () => {
        const media = mockMatchMedia(false);

        const { result } = renderHook(() => usePrefersReducedMotion());
        expect(result.current).toBe(false);

        act(() => {
            media.set(true);
        });

        expect(result.current).toBe(true);
    });
});
