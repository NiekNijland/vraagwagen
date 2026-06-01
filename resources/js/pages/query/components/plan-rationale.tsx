import { useTranslation } from '@/hooks/use-translation';

import { translateColumn } from '../format';
import type {
    AggregateClause,
    GroupKey,
    OrderClause,
    Plan,
    Step,
    WhereClause,
} from '../types';

type TFn = (key: string, params?: Record<string, string | number>) => string;

/**
 * "Why this result?" — a plain-language breakdown of the structured plan so
 * users can see *why* the system answered the way it did without reading
 * SoQL. The LLM's free-form explanation already sits above this; this body
 * deconstructs the plan itself. The disclosure trigger lives in the caller so
 * it can share a row with the SoQL toggle.
 */
export function PlanRationaleBody({
    plan,
    steps,
    locale,
}: {
    plan: Plan;
    steps?: Step[];
    locale: string;
}) {
    const { t } = useTranslation();
    const isMultiStep = steps !== undefined && steps.length > 1;

    return (
        <div className="rounded-[12px] border bg-[color:color-mix(in_oklab,var(--background)_60%,transparent)] p-3.5 text-xs">
            {isMultiStep ? (
                <MultiStepRationale steps={steps} t={t} locale={locale} />
            ) : (
                <SinglePlanRationale plan={plan} t={t} locale={locale} />
            )}
        </div>
    );
}

function MultiStepRationale({
    steps,
    t,
    locale,
}: {
    steps: Step[];
    t: TFn;
    locale: string;
}) {
    return (
        <div className="space-y-4">
            {steps.map((step) => (
                <div key={step.id} className="space-y-2">
                    <div className="flex items-center gap-2">
                        <span className="rounded bg-background/80 px-1.5 py-0.5 font-mono text-[10px] font-semibold">
                            {step.id}
                        </span>
                        <span className="text-[11px] text-muted-foreground tabular-nums">
                            {t('pages.query.rationale.rowCount', {
                                count: step.rowCount,
                            })}
                        </span>
                    </div>
                    <SinglePlanRationale
                        plan={step.plan}
                        t={t}
                        locale={locale}
                    />
                </div>
            ))}
        </div>
    );
}

function SinglePlanRationale({
    plan,
    t,
    locale,
}: {
    plan: Plan;
    t: TFn;
    locale: string;
}) {
    return (
        <dl className="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-3 gap-y-2 leading-relaxed">
            <RationaleRow label={t('pages.query.rationale.dataset')}>
                {t(`pages.query.rationale.datasets.${plan.dataset}`)}
            </RationaleRow>

            {plan.where.length > 0 && (
                <RationaleRow label={t('pages.query.rationale.filters')}>
                    <ul className="space-y-0.5">
                        {plan.where.map((w, i) => (
                            <li key={`${w.field}-${w.op}-${i}`}>
                                {describeWhere(w, t)}
                            </li>
                        ))}
                    </ul>
                </RationaleRow>
            )}

            {plan.groupBy.length > 0 && (
                <RationaleRow label={t('pages.query.rationale.grouping')}>
                    {plan.groupBy.map((g) => describeGroup(g, t)).join(', ')}
                </RationaleRow>
            )}

            {plan.aggregates.length > 0 && (
                <RationaleRow label={t('pages.query.rationale.calculation')}>
                    {plan.aggregates
                        .map((a) => describeAggregate(a, t))
                        .join(', ')}
                </RationaleRow>
            )}

            {plan.orderBy.length > 0 && (
                <RationaleRow label={t('pages.query.rationale.sort')}>
                    {plan.orderBy.map((o) => describeOrder(o, t)).join(', ')}
                </RationaleRow>
            )}

            {plan.limit !== null && (
                <RationaleRow label={t('pages.query.rationale.limit')}>
                    {t('pages.query.rationale.limitValue', {
                        count: plan.limit.toLocaleString(
                            locale === 'nl' ? 'nl-NL' : 'en-US',
                        ),
                    })}
                </RationaleRow>
            )}
        </dl>
    );
}

function RationaleRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <>
            <dt className="text-[10.5px] font-semibold tracking-[0.12em] whitespace-nowrap text-[var(--rdw-orange)] uppercase">
                {label}
            </dt>
            <dd className="text-[12.5px] text-foreground">{children}</dd>
        </>
    );
}

// Show at most this many `in` members before collapsing the tail into "+N more";
// a resolved cross-dataset list can run to hundreds of plates.
const MAX_IN_VALUES = 6;

function describeWhere(w: WhereClause, t: TFn): string {
    const field = translateColumn(w.field, t);
    // An `in` clause carries its match set in `values`; `value` holds the raw `{{q1.field}}`
    // step-reference token, which must never reach the user.
    const value = w.op === 'in' ? formatInValues(w.values ?? [], t) : w.value;
    const phrase = t(`pages.query.rationale.ops.${w.op}`, { field, value });

    // If the key is missing, `t` returns the key itself — fall back to a generic phrasing.
    if (phrase.startsWith('pages.query.rationale.ops.')) {
        return `${field} ${w.op} ${value}`;
    }

    return phrase;
}

function formatInValues(values: string[], t: TFn): string {
    if (values.length <= MAX_IN_VALUES) {
        return values.join(', ');
    }

    return t('pages.query.rationale.inOverflow', {
        values: values.slice(0, MAX_IN_VALUES).join(', '),
        count: values.length - MAX_IN_VALUES,
    });
}

function describeGroup(g: GroupKey, t: TFn): string {
    const field = translateColumn(g.field, t);

    if (g.bucket === 'none') {
        return field;
    }

    return t(`pages.query.rationale.bucket.${g.bucket}`, { field });
}

function describeAggregate(a: AggregateClause, t: TFn): string {
    if (a.fn === 'count') {
        return t('pages.query.rationale.fn.count');
    }

    const field = a.field === null ? '' : translateColumn(a.field, t);

    return t(`pages.query.rationale.fn.${a.fn}`, { field });
}

function describeOrder(o: OrderClause, t: TFn): string {
    // `expr` is usually a column ("RegistrationDate"); translate it so the user sees the field
    // label rather than the raw PascalCase identifier. Aggregate aliases ("n") have no translation
    // and fall back to a humanised form, which is acceptable for this debug-ish panel.
    return t(`pages.query.rationale.direction.${o.direction}`, {
        expr: translateColumn(o.expr, t),
    });
}
