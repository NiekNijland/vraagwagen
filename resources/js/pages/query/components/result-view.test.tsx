import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { renderWithI18n } from '@/test/render';

import type { Plan, Presentation, QueryResult } from '../types';
import { ResultView } from './result-view';

// The toolbar copies share links through sonner; the toast itself isn't under
// test, so a no-op stub keeps jsdom (no portals/IntersectionObserver) clean.
vi.mock('sonner', () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}));

function plan(overrides: Partial<Plan> = {}): Plan {
    return {
        dataset: 'RegisteredVehicles',
        where: [],
        select: [],
        groupBy: [],
        aggregates: [{ fn: 'count', field: null, alias: 'n' }],
        orderBy: [],
        limit: null,
        display: 'count',
        explanation: 'Counts the matching vehicles.',
        ...overrides,
    };
}

function presentation(overrides: Partial<Presentation> = {}): Presentation {
    return {
        resultRef: 'q1',
        display: 'count',
        derive: null,
        derived: null,
        explanation: 'Counts the matching vehicles.',
        followUps: [],
        ...overrides,
    };
}

function result(overrides: Partial<QueryResult> = {}): QueryResult {
    return {
        slug: 'slug-12345',
        correlationId: 'cid-67890',
        prompt: 'How many Teslas are registered?',
        plan: plan(),
        soql: { $where: 'merk = "TESLA"' },
        url: 'https://opendata.rdw.nl/resource/m9d7-ebf2.json',
        rows: [{ n: 5421 }],
        displayHint: 'count',
        rating: null,
        comment: null,
        model: 'gpt-4.1-nano',
        tokens: { prompt: 800, completion: 80, cacheRead: 0, thought: 0 },
        estimatedCost: 0.0001,
        presentation: presentation(),
        ...overrides,
    };
}

describe('ResultView follow-up chips', () => {
    it('renders the model-supplied follow-ups and re-runs the picked prompt', async () => {
        const onPickFollowUp = vi.fn();
        renderWithI18n(
            <ResultView
                result={result({
                    presentation: presentation({
                        followUps: [
                            'Tesla registrations per year',
                            'Average engine power of the Tesla',
                        ],
                    }),
                })}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={onPickFollowUp}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: /Tesla registrations per year/,
            }),
        );

        // The chip's text IS the prompt the parent re-runs.
        expect(onPickFollowUp).toHaveBeenCalledWith(
            'Tesla registrations per year',
        );
    });

    it('hides the follow-up row when the presentation carries none', () => {
        renderWithI18n(
            <ResultView
                result={result({
                    presentation: presentation({ followUps: [] }),
                })}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={vi.fn()}
            />,
        );

        expect(screen.queryByText('Next steps')).not.toBeInTheDocument();
    });

    it('hides the follow-up row when no picker is wired up', () => {
        renderWithI18n(
            <ResultView
                result={result({
                    presentation: presentation({
                        followUps: ['Tesla registrations per year'],
                    }),
                })}
                locale="en"
                onRatingChange={vi.fn()}
            />,
        );

        expect(screen.queryByText('Next steps')).not.toBeInTheDocument();
    });
});

describe('ResultView disclosures', () => {
    it('opens the rationale and query panels independently', async () => {
        renderWithI18n(
            <ResultView
                result={result()}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={vi.fn()}
            />,
        );

        const rationaleToggle = screen.getByRole('button', {
            name: /Why this result\?/,
        });
        const queryToggle = screen.getByRole('button', {
            name: /Show generated query/,
        });

        // Both panels start collapsed.
        expect(rationaleToggle).toHaveAttribute('aria-expanded', 'false');
        expect(queryToggle).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByText('Dataset')).not.toBeInTheDocument();
        expect(screen.queryByText('gpt-4.1-nano')).not.toBeInTheDocument();

        // Opening the rationale leaves the query panel untouched.
        await userEvent.click(rationaleToggle);
        expect(rationaleToggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByText('Dataset')).toBeInTheDocument();
        expect(queryToggle).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByText('gpt-4.1-nano')).not.toBeInTheDocument();

        // Opening the query panel keeps the rationale open too.
        await userEvent.click(queryToggle);
        expect(queryToggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByText('gpt-4.1-nano')).toBeInTheDocument();
        expect(rationaleToggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByText('Dataset')).toBeInTheDocument();
    });

    it('omits the query toggle when no query detail exists, keeping the rationale', () => {
        renderWithI18n(
            <ResultView
                result={result({
                    soql: undefined as never,
                    url: undefined as never,
                    model: '',
                    correlationId: undefined,
                    steps: undefined,
                })}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: /Why this result\?/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Show generated query/ }),
        ).not.toBeInTheDocument();
    });

    it('hides every disclosure for an unsupported result', () => {
        renderWithI18n(
            <ResultView
                result={result({ displayHint: 'unsupported' })}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={vi.fn()}
            />,
        );

        expect(
            screen.queryByRole('button', { name: /Why this result\?/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Show generated query/ }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Next steps')).not.toBeInTheDocument();
    });
});

describe('ResultView explanation', () => {
    it('prefers the presentation explanation over the plan copy', () => {
        const { container } = renderWithI18n(
            <ResultView
                result={result({
                    plan: plan({ explanation: 'Plan-level copy.' }),
                    presentation: presentation({
                        explanation: 'Presentation-level copy.',
                    }),
                })}
                locale="en"
                onRatingChange={vi.fn()}
                onPickFollowUp={vi.fn()}
            />,
        );

        expect(
            within(container).getByText('Presentation-level copy.'),
        ).toBeInTheDocument();
        expect(
            within(container).queryByText('Plan-level copy.'),
        ).not.toBeInTheDocument();
    });
});
