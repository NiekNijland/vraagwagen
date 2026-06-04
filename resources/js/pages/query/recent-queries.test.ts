import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    clearRecentQueries,
    getRecentQueriesServerSnapshot,
    pushRecentQuery,
    readRecentQueries,
    subscribeToRecentQueries,
} from './recent-queries';

// Implementation detail, but stable — asserting it proves we persist across
// reloads rather than only updating the in-memory cache.
const STORAGE_KEY = 'vraagwagen:recent-queries';

function stored(): unknown {
    const raw = window.localStorage.getItem(STORAGE_KEY);

    return raw === null ? null : JSON.parse(raw);
}

beforeEach(() => {
    window.localStorage.clear();
    clearRecentQueries();
});

describe('pushRecentQuery', () => {
    it('prepends the query and persists it', () => {
        pushRecentQuery('How many Teslas?');

        expect(readRecentQueries()).toEqual(['How many Teslas?']);
        expect(stored()).toEqual(['How many Teslas?']);
    });

    it('moves a repeated query to the front without duplicating it', () => {
        pushRecentQuery('a');
        pushRecentQuery('b');
        pushRecentQuery('a');

        expect(readRecentQueries()).toEqual(['a', 'b']);
    });

    it('keeps at most six entries, newest first', () => {
        for (const q of ['1', '2', '3', '4', '5', '6', '7']) {
            pushRecentQuery(q);
        }

        expect(readRecentQueries()).toEqual(['7', '6', '5', '4', '3', '2']);
    });

    it('trims surrounding whitespace', () => {
        pushRecentQuery('  spaced  ');

        expect(readRecentQueries()).toEqual(['spaced']);
    });
});

describe('clearRecentQueries', () => {
    it('empties the list and removes the persisted value', () => {
        pushRecentQuery('something');
        clearRecentQueries();

        expect(readRecentQueries()).toEqual([]);
        expect(stored()).toBeNull();
    });
});

describe('getRecentQueriesServerSnapshot', () => {
    it('is always empty so SSR and first client paint agree', () => {
        expect(getRecentQueriesServerSnapshot()).toEqual([]);
    });
});

describe('subscribeToRecentQueries', () => {
    it('notifies subscribers on change and stops after unsubscribe', () => {
        const callback = vi.fn();
        const unsubscribe = subscribeToRecentQueries(callback);

        pushRecentQuery('x');
        expect(callback).toHaveBeenCalledTimes(1);

        unsubscribe();
        pushRecentQuery('y');
        expect(callback).toHaveBeenCalledTimes(1);
    });

    it('reloads from storage when another tab writes the key', () => {
        const callback = vi.fn();
        const unsubscribe = subscribeToRecentQueries(callback);

        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(['from-other-tab']),
        );
        window.dispatchEvent(new StorageEvent('storage', { key: STORAGE_KEY }));

        expect(callback).toHaveBeenCalled();
        expect(readRecentQueries()).toEqual(['from-other-tab']);

        unsubscribe();
    });

    it('treats malformed stored JSON as an empty list', () => {
        const unsubscribe = subscribeToRecentQueries(vi.fn());

        window.localStorage.setItem(STORAGE_KEY, 'not-json');
        window.dispatchEvent(new StorageEvent('storage', { key: STORAGE_KEY }));

        expect(readRecentQueries()).toEqual([]);

        unsubscribe();
    });
});

afterEach(() => {
    window.localStorage.clear();
});
