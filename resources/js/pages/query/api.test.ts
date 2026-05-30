import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { parseJson, postJson } from './api';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'content-type': 'application/json' },
    });
}

describe('postJson', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        document.head.innerHTML = '';
        fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('posts JSON with the expected headers and serialized body', async () => {
        const signal = new AbortController().signal;

        await postJson('/api/query', { prompt: 'hi' }, signal);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const call = fetchMock.mock.calls[0]!;
        const [path, options] = call;
        expect(path).toBe('/api/query');
        expect(options.method).toBe('POST');
        expect(options.body).toBe(JSON.stringify({ prompt: 'hi' }));
        expect(options.signal).toBe(signal);
        expect(options.headers['Content-Type']).toBe('application/json');
        expect(options.headers['Accept']).toBe('application/json');
    });

    it('adds the CSRF token header when the meta tag is present', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="tok-123">';

        await postJson('/api/query', {});

        const options = fetchMock.mock.calls[0]![1];
        expect(options.headers['X-CSRF-TOKEN']).toBe('tok-123');
    });

    it('omits the CSRF header when no meta tag exists', async () => {
        await postJson('/api/query', {});

        const options = fetchMock.mock.calls[0]![1];
        expect(options.headers['X-CSRF-TOKEN']).toBeUndefined();
    });
});

describe('parseJson', () => {
    it('parses a JSON response body', async () => {
        await expect(parseJson(jsonResponse({ value: 1 }))).resolves.toEqual({
            value: 1,
        });
    });

    it('returns null for a non-JSON content type', async () => {
        const response = new Response('plain', {
            headers: { 'content-type': 'text/plain' },
        });

        await expect(parseJson(response)).resolves.toBeNull();
    });

    it('returns null when the body is not valid JSON', async () => {
        const response = new Response('{ broken', {
            headers: { 'content-type': 'application/json' },
        });

        await expect(parseJson(response)).resolves.toBeNull();
    });
});
