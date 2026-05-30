import type { Plan, WhereClause } from './types';

export type FollowUp = {
    id: string;
    /** Short label shown on the chip. */
    label: string;
    /** Full prompt the user effectively types when picking the chip. */
    prompt: string;
};

type TFn = (key: string, params?: Record<string, string | number>) => string;

// Up to this many chips are rendered. Past three the row wraps and reads as a
// menu, which we don't want — these are meant to nudge, not list.
const MAX_FOLLOW_UPS = 3;

// PascalCase fields whose value names the subject of the user's question
// (e.g. "Tesla", "campers", "GT-486-N"). Order matters: a brand filter is a
// stronger subject than a body type, so it wins when both are present.
const SUBJECT_FIELDS: readonly string[] = [
    'Brand',
    'CommercialName',
    'BodyType',
    'VehicleType',
    'LicensePlate',
];

/**
 * Derive 3 contextual next-step prompts from the current plan shape. Rules are
 * conservative on purpose — every chip must produce a prompt the planner can
 * actually answer, otherwise the chip just trains users to ignore them.
 */
export function suggestFollowUps(plan: Plan, t: TFn): FollowUp[] {
    if (plan.display === 'unsupported') {
        return [];
    }

    const subject = extractSubject(plan);
    const hasGroupBy = plan.groupBy.length > 0;
    const isPlateRecord =
        plan.display === 'record' && hasFilterOn(plan, 'LicensePlate');
    const isHistogram = plan.display === 'histogram';
    const isTimeseries = plan.display === 'timeseries';
    const countOnly =
        plan.aggregates.length === 1 &&
        plan.aggregates[0]?.fn === 'count' &&
        plan.aggregates[0]?.field === null;

    const out: FollowUp[] = [];

    // Plate lookups get plate-specific follow-ups because every other rule is
    // shaped around aggregations, which don't apply to a single-record view.
    if (isPlateRecord) {
        const plate = findClauseValue(plan, 'LicensePlate');

        if (plate !== null) {
            out.push({
                id: 'plate-fuel',
                label: t('pages.query.followUps.plate.fuel'),
                prompt: t('pages.query.followUps.plate.fuelPrompt', { plate }),
            });
            out.push({
                id: 'plate-transfers',
                label: t('pages.query.followUps.plate.transfers'),
                prompt: t('pages.query.followUps.plate.transfersPrompt', {
                    plate,
                }),
            });
        }

        return out.slice(0, MAX_FOLLOW_UPS);
    }

    // Aggregated-without-group queries are the prime candidate for breakdowns:
    // we know what they asked about (the subject) and we know they haven't
    // sliced it yet.
    if (subject !== null && !hasGroupBy && countOnly) {
        if (!isTimeseries) {
            out.push({
                id: 'per-year',
                label: t('pages.query.followUps.perYear'),
                prompt: t('pages.query.followUps.perYearPrompt', { subject }),
            });
        }

        out.push({
            id: 'per-province',
            label: t('pages.query.followUps.perProvince'),
            prompt: t('pages.query.followUps.perProvincePrompt', { subject }),
        });

        // Suggesting "top models" only makes sense when the user *hasn't*
        // already named a model — otherwise we'd re-ask their own question.
        if (
            findClauseValue(plan, 'Brand') !== null &&
            findClauseValue(plan, 'CommercialName') === null
        ) {
            out.push({
                id: 'top-models',
                label: t('pages.query.followUps.topModels'),
                prompt: t('pages.query.followUps.topModelsPrompt', { subject }),
            });
        }
    }

    // Grouped result without a top-N cap: shrinking to top-10 keeps the chart
    // legible on long-tail categoricals (colors, models).
    if (hasGroupBy && plan.limit === null && !isHistogram && !isTimeseries) {
        out.push({
            id: 'top-10',
            label: t('pages.query.followUps.top10'),
            prompt: t('pages.query.followUps.top10Prompt', {
                subject: subject ?? '',
            }),
        });
    }

    // Vehicles-dataset count queries with a brand subject can usually pivot to
    // the fuels dataset for an emissions/power angle.
    if (
        subject !== null &&
        plan.dataset === 'RegisteredVehicles' &&
        plan.aggregates.some((a) => a.fn === 'count')
    ) {
        out.push({
            id: 'avg-emissions',
            label: t('pages.query.followUps.avgEmissions'),
            prompt: t('pages.query.followUps.avgEmissionsPrompt', { subject }),
        });
    }

    // Always-on safety net for sparse plans (no subject + no group): give the
    // user *something* to click on so the row isn't blank.
    if (out.length === 0) {
        out.push({
            id: 'top-brands',
            label: t('pages.query.followUps.topBrands'),
            prompt: t('pages.query.followUps.topBrandsPrompt'),
        });
    }

    return dedupeById(out).slice(0, MAX_FOLLOW_UPS);
}

function extractSubject(plan: Plan): string | null {
    for (const field of SUBJECT_FIELDS) {
        const value = findClauseValue(plan, field);

        if (value !== null) {
            return value;
        }
    }

    return null;
}

function hasFilterOn(plan: Plan, field: string): boolean {
    return plan.where.some((w: WhereClause) => w.field === field);
}

function findClauseValue(plan: Plan, field: string): string | null {
    const clause = plan.where.find((w: WhereClause) => w.field === field);

    if (clause === undefined) {
        return null;
    }

    const value = clause.value.trim();

    return value === '' ? null : value;
}

function dedupeById(items: FollowUp[]): FollowUp[] {
    const seen = new Set<string>();
    const out: FollowUp[] = [];

    for (const item of items) {
        if (!seen.has(item.id)) {
            seen.add(item.id);
            out.push(item);
        }
    }

    return out;
}
