import { beforeEach, describe, expect, it } from 'vitest';

import { buildShareUrl, resetShareUrl, updateShareUrl } from './share-url';

beforeEach(() => {
    window.history.replaceState({}, '', '/');
});

describe('buildShareUrl', () => {
    it('builds a /{locale}/{slug} path', () => {
        const url = new URL(buildShareUrl('en', 'abc123'));

        expect(url.pathname).toBe('/en/abc123');
        expect(url.search).toBe('');
    });

    it('drops any pre-existing query string', () => {
        window.history.replaceState({}, '', '/nl?foo=bar');

        const url = new URL(buildShareUrl('nl', 'slug'));

        expect(url.pathname).toBe('/nl/slug');
        expect(url.search).toBe('');
    });

    it('percent-encodes the slug into the path', () => {
        const url = new URL(buildShareUrl('en', 'a b'));

        expect(url.pathname).toBe('/en/a%20b');
    });
});

describe('updateShareUrl', () => {
    it('rewrites the address bar to the slug path without navigating', () => {
        updateShareUrl('en', 'xyz');

        expect(window.location.pathname).toBe('/en/xyz');
        expect(window.location.search).toBe('');
    });
});

describe('resetShareUrl', () => {
    it('clears the slug back to the bare locale path', () => {
        updateShareUrl('en', 'xyz');
        resetShareUrl('en');

        expect(window.location.pathname).toBe('/en');
        expect(window.location.search).toBe('');
    });
});
