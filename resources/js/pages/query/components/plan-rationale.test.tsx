import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { renderWithI18n } from '@/test/render';

import type { Plan } from '../types';
import { PlanRationale } from './plan-rationale';

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

// Radix Collapsible unmounts its content while closed, so every assertion has
// to open the panel first.
async function open() {
    await userEvent.click(
        screen.getByRole('button', { name: /why this result/i }),
    );
}

describe('PlanRationale', () => {
    it('renders an `in` clause as its resolved match set, never the step-reference token', async () => {
        renderWithI18n(
            <PlanRationale
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
        await open();

        expect(screen.getByText(/AA-001-A, BB-002-B/)).toBeInTheDocument();
        expect(screen.queryByText(/\{\{q1/)).not.toBeInTheDocument();
    });

    it('collapses a long `in` list into a "+N more" tail', async () => {
        const values = Array.from({ length: 9 }, (_, i) => `K-${i}`);
        renderWithI18n(
            <PlanRationale
                plan={plan({
                    where: [
                        { field: 'LicensePlate', op: 'in', value: '', values },
                    ],
                })}
                locale="en"
            />,
        );
        await open();

        // Six shown, the remaining three collapsed.
        expect(screen.getByText(/\+3 more/)).toBeInTheDocument();
        expect(screen.queryByText(/K-8/)).not.toBeInTheDocument();
    });

    it('translates an orderBy column instead of printing the raw identifier', async () => {
        renderWithI18n(
            <PlanRationale
                plan={plan({
                    orderBy: [{ expr: 'RegistrationDate', direction: 'desc' }],
                })}
                locale="en"
            />,
        );
        await open();

        // translateColumn falls back to a humanised label when no translation key exists.
        expect(screen.queryByText(/RegistrationDate/)).not.toBeInTheDocument();
    });
});
