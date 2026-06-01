import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithI18n } from '@/test/render';

import type { Plan } from '../types';
import { PlanRationaleBody } from './plan-rationale';

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
        explanation: '',
        ...overrides,
    };
}

describe('PlanRationaleBody', () => {
    it('renders an `in` clause as its resolved match set, never the step-reference token', () => {
        renderWithI18n(
            <PlanRationaleBody
                plan={plan({
                    where: [
                        {
                            field: 'LicensePlate',
                            op: 'in',
                            value: '{{q1.LicensePlate}}',
                            values: ['AA-001-A', 'BB-002-B'],
                        },
                    ],
                })}
                locale="en"
            />,
        );

        expect(screen.getByText(/AA-001-A, BB-002-B/)).toBeInTheDocument();
        expect(screen.queryByText(/\{\{q1/)).not.toBeInTheDocument();
    });

    it('collapses a long `in` list into a "+N more" tail', () => {
        const values = Array.from({ length: 9 }, (_, i) => `K-${i}`);
        renderWithI18n(
            <PlanRationaleBody
                plan={plan({
                    where: [
                        { field: 'LicensePlate', op: 'in', value: '', values },
                    ],
                })}
                locale="en"
            />,
        );

        // Six shown, the remaining three collapsed.
        expect(screen.getByText(/\+3 more/)).toBeInTheDocument();
        expect(screen.queryByText(/K-8/)).not.toBeInTheDocument();
    });

    it('translates an orderBy column instead of printing the raw identifier', () => {
        renderWithI18n(
            <PlanRationaleBody
                plan={plan({
                    orderBy: [{ expr: 'RegistrationDate', direction: 'desc' }],
                })}
                locale="en"
            />,
        );

        // translateColumn falls back to a humanised label when no translation key exists.
        expect(screen.queryByText(/RegistrationDate/)).not.toBeInTheDocument();
    });
});
