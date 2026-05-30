import { describe, expect, it } from 'vitest';

import { suggestFollowUps } from './follow-ups';
import type { Plan, WhereClause } from './types';

// Echoes the key + a JSON-stringified params dict so assertions can pin both
// the picked translation key and the substituted subject. Avoids spinning up
// the real i18n provider for what is otherwise a pure function test.
const t = (key: string, params?: Record<string, string | number>): string =>
    params === undefined
        ? key
        : `${key}|${Object.entries(params)
              .map(([k, v]) => `${k}=${v}`)
              .join(',')}`;

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

function where(field: string, op = 'eq', value = 'Tesla'): WhereClause {
    return { field, op, value };
}

describe('suggestFollowUps', () => {
    it('returns no suggestions for unsupported plans', () => {
        expect(
            suggestFollowUps(plan({ display: 'unsupported' }), t),
        ).toHaveLength(0);
    });

    it('offers a year + province breakdown when the subject is a brand and the query has no grouping', () => {
        const result = suggestFollowUps(
            plan({ where: [where('Brand', 'eq', 'Tesla')] }),
            t,
        );

        const ids = result.map((r) => r.id);
        expect(ids).toContain('per-year');
        expect(ids).toContain('per-province');
        // The brand subject is templated into the chip prompts.
        expect(result.find((r) => r.id === 'per-year')?.prompt).toContain(
            'subject=Tesla',
        );
    });

    it('skips the per-year chip when the query is already a timeseries', () => {
        const result = suggestFollowUps(
            plan({
                where: [where('Brand', 'eq', 'Tesla')],
                groupBy: [{ field: 'FirstAdmissionDate', bucket: 'year' }],
                display: 'timeseries',
            }),
            t,
        );

        expect(result.map((r) => r.id)).not.toContain('per-year');
    });

    it('proposes a top-models chip only when a brand is filtered but no model is', () => {
        const withBrand = suggestFollowUps(
            plan({ where: [where('Brand', 'eq', 'Tesla')] }),
            t,
        );
        expect(withBrand.map((r) => r.id)).toContain('top-models');

        const withModel = suggestFollowUps(
            plan({
                where: [
                    where('Brand', 'eq', 'Tesla'),
                    where('CommercialName', 'contains', 'Model 3'),
                ],
            }),
            t,
        );
        // The subject becomes "Tesla" but we suppress top-models because the
        // user already named a model — otherwise we'd just re-ask their query.
        expect(withModel.map((r) => r.id)).not.toContain('top-models');
    });

    it('suggests a top-10 cap for grouped results that lack a limit', () => {
        const result = suggestFollowUps(
            plan({
                groupBy: [{ field: 'Color', bucket: 'none' }],
                display: 'bars',
                limit: null,
            }),
            t,
        );

        expect(result.map((r) => r.id)).toContain('top-10');
    });

    it('does not suggest top-10 when a limit is already set', () => {
        const result = suggestFollowUps(
            plan({
                groupBy: [{ field: 'Color', bucket: 'none' }],
                display: 'bars',
                limit: 5,
            }),
            t,
        );

        expect(result.map((r) => r.id)).not.toContain('top-10');
    });

    it('emits plate-specific follow-ups for record views and bails out of the generic rules', () => {
        const result = suggestFollowUps(
            plan({
                where: [where('LicensePlate', 'eq', 'GT-486-N')],
                display: 'record',
            }),
            t,
        );

        const ids = result.map((r) => r.id);
        expect(ids).toContain('plate-fuel');
        expect(ids).toContain('plate-transfers');
        // Generic chips would dilute the plate-focused UX.
        expect(ids).not.toContain('per-year');
        expect(ids).not.toContain('per-province');
    });

    it('caps the output at three chips', () => {
        // Brand subject, vehicles dataset, count aggregate, no group, no model
        // filter — fires per-year + per-province + top-models + avg-emissions.
        const result = suggestFollowUps(
            plan({ where: [where('Brand', 'eq', 'Tesla')] }),
            t,
        );

        expect(result.length).toBeLessThanOrEqual(3);
    });

    it('falls back to a top-brands suggestion when no other rule fires', () => {
        // Sum aggregate (no count), no subject, no group, vehicles dataset —
        // skips every rule until the safety net.
        const result = suggestFollowUps(
            plan({
                aggregates: [
                    { fn: 'sum', field: 'EmptyMass', alias: 'total_mass' },
                ],
            }),
            t,
        );

        expect(result).toHaveLength(1);
        expect(result[0]?.id).toBe('top-brands');
    });
});
