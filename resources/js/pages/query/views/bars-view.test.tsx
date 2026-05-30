import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithI18n } from '@/test/render';

import type { Plan } from '../types';
import { BarsView } from './bars-view';

function barsPlan(): Plan {
    return {
        dataset: 'RegisteredVehicles',
        where: [],
        select: [],
        groupBy: [{ field: 'kleur', bucket: 'none' }],
        aggregates: [{ fn: 'count', field: null, alias: 'n' }],
        orderBy: [],
        limit: null,
        display: 'bars',
        explanation: '',
    };
}

describe('BarsView', () => {
    it('renders a single category as a headline figure, not an empty chart', async () => {
        renderWithI18n(
            <BarsView
                rows={[{ kleur: 'DIVERSEN', n: 646 }]}
                plan={barsPlan()}
                locale="en"
                fallback={<div>fallback</div>}
            />,
        );

        // The lone value reads as a stat with its label, not chart scaffolding.
        expect(await screen.findByText('646')).toBeInTheDocument();
        expect(screen.getByText('DIVERSEN')).toBeInTheDocument();
    });

    it('formats the single value with locale grouping', async () => {
        renderWithI18n(
            <BarsView
                rows={[{ kleur: 'WIT', n: 12345 }]}
                plan={barsPlan()}
                locale="nl"
                fallback={<div>fallback</div>}
            />,
            { locale: 'nl' },
        );

        expect(await screen.findByText('12.345')).toBeInTheDocument();
    });
});
