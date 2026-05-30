import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { renderWithI18n } from '@/test/render';

import type { RunResponse } from './types';
import QueryPage from './index';

// Inertia's <Head> bundles helmet-style side-effects we don't need here, and
// usePage() expects the shared-props bootstrap our tests don't set up. Stub
// both so child components (LanguageSwitcher, theme toggle) keep rendering.
vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    usePage: () => ({
        props: { locales: { en: 'English', nl: 'Nederlands' } },
        url: '/en',
    }),
    router: { post: vi.fn(), visit: vi.fn() },
}));

// Sonner taps into IntersectionObserver / portals that jsdom doesn't ship by
// default. The toast itself isn't under test, so a no-op stub keeps the page
// path clean.
vi.mock('sonner', () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}));

function runResponse(overrides: Partial<RunResponse> = {}): RunResponse {
    return {
        slug: 'slug-12345',
        correlationId: 'cid-67890',
        plan: {
            dataset: 'RegisteredVehicles',
            where: [],
            select: [],
            groupBy: [],
            aggregates: [{ fn: 'count', field: null, alias: 'n' }],
            orderBy: [],
            limit: null,
            display: 'count',
            explanation: 'Counts the matching vehicles.',
        },
        soql: { $where: 'merk = "TESLA"' },
        url: 'https://opendata.rdw.nl/resource/m9d7-ebf2.json',
        rows: [{ n: 5421 }],
        displayHint: 'count',
        model: 'gpt-4.1-nano',
        tokens: { prompt: 800, completion: 80, cacheRead: 0, thought: 0 },
        estimatedCost: 0.0001,
        ...overrides,
    };
}

describe('QueryPage', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        window.localStorage.clear();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it('submits the prompt, renders the result, and stores it in recent queries', async () => {
        const response = runResponse();
        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify(response), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );

        renderWithI18n(<QueryPage sharedRun={null} />);

        const composer = screen.getByRole('textbox');
        await userEvent.type(composer, 'How many Teslas?');
        await userEvent.keyboard('{Meta>}{Enter}{/Meta}');

        // Result count is rendered with locale grouping (reduced motion is on
        // under test, so the final figure renders without an animation wait).
        expect(await screen.findByText('5,421')).toBeInTheDocument();

        // The recent-queries store is hooked up to localStorage, so the submit
        // should have persisted the prompt.
        await waitFor(() => {
            const stored = window.localStorage.getItem('rdwai:recent-queries');
            expect(stored).toContain('How many Teslas?');
        });

        // Server received exactly one call with the prompt body.
        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [, init] = fetchMock.mock.calls[0]!;
        expect(JSON.parse(init.body)).toEqual({ prompt: 'How many Teslas?' });
    });

    it('surfaces the correlation id in the debug panel on a successful run', async () => {
        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify(runResponse()), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );

        renderWithI18n(<QueryPage sharedRun={null} />);

        await userEvent.type(screen.getByRole('textbox'), 'How many Teslas?');
        await userEvent.keyboard('{Meta>}{Enter}{/Meta}');

        // The panel is collapsed by default; the id only needs to be reachable,
        // not visible, so expand it before asserting.
        await userEvent.click(await screen.findByText('Show generated query'));
        expect(await screen.findByText('cid-67890')).toBeInTheDocument();
    });

    it('renders the localized error message and surfaces the correlation id on failure', async () => {
        fetchMock.mockResolvedValueOnce(
            new Response(
                JSON.stringify({
                    error: 'The generated query was rejected.',
                    correlationId: 'cid-fail-42',
                }),
                {
                    status: 422,
                    headers: { 'Content-Type': 'application/json' },
                },
            ),
        );

        renderWithI18n(<QueryPage sharedRun={null} />);

        await userEvent.type(screen.getByRole('textbox'), 'broken query');
        await userEvent.keyboard('{Meta>}{Enter}{/Meta}');

        expect(
            await screen.findByText('The generated query was rejected.'),
        ).toBeInTheDocument();
        expect(await screen.findByText(/cid-fail-42/)).toBeInTheDocument();
    });

    it('does not submit when the prompt is below the minimum length', async () => {
        renderWithI18n(<QueryPage sharedRun={null} />);

        await userEvent.type(screen.getByRole('textbox'), 'no');
        await userEvent.keyboard('{Meta>}{Enter}{/Meta}');

        // Two characters never leaves the client, so no request is made.
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('renders the shared run on first mount without firing a request', async () => {
        renderWithI18n(
            <QueryPage
                sharedRun={{
                    slug: 'shared-1',
                    prompt: 'Shared question?',
                    locale: 'en',
                    plan: runResponse().plan,
                    soql: runResponse().soql,
                    url: runResponse().url,
                    rows: [{ n: 999 }],
                    displayHint: 'count',
                    rating: null,
                    comment: null,
                    model: 'gpt-4.1-nano',
                    tokens: runResponse().tokens,
                    estimatedCost: 0,
                }}
            />,
        );

        // The shared run pre-fills the result; no fetch should be issued.
        expect(await screen.findByText('999')).toBeInTheDocument();
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
