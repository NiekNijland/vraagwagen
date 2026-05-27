import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useTypewriterPlaceholder } from './use-typewriter-placeholder';

const CURSOR = '▍';
const strip = (value: string): string => value.replace(CURSOR, '');

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useTypewriterPlaceholder', () => {
    it('returns the fallback when inactive', () => {
        const { result } = renderHook(() =>
            useTypewriterPlaceholder(['Hello'], false, 'Ask anything'),
        );

        expect(result.current).toBe('Ask anything');
    });

    it('returns the fallback when there are no phrases', () => {
        const { result } = renderHook(() =>
            useTypewriterPlaceholder([], true, 'Ask anything'),
        );

        expect(result.current).toBe('Ask anything');
    });

    it('starts with just the cursor and types the phrase out over time', () => {
        const { result } = renderHook(() =>
            useTypewriterPlaceholder(['Hi'], true, 'Ask anything'),
        );

        // Single-phrase pool, so the shuffle is deterministic: "Hi".
        expect(result.current).toBe(CURSOR);
        expect(strip(result.current)).toBe('');

        act(() => {
            vi.advanceTimersByTime(60);
        });
        expect(strip(result.current)).toBe('H');

        act(() => {
            vi.advanceTimersByTime(60);
        });
        expect(strip(result.current)).toBe('Hi');
    });
});
