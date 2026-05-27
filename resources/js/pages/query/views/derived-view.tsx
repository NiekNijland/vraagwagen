import { formatNumber, formatPercent } from '../format';
import type { Derived, DeriveOp } from '../types';

// How the two operands relate, shown as muted context under the figure. Numbers
// only — no LLM text — so nothing the model typed is rendered as data.
const OPERAND_SYMBOL: Record<DeriveOp, string> = {
    groupShare: 'of',
    percentage: 'of',
    ratio: '/',
    difference: '−',
    sum: '+',
};

function isPercent(op: DeriveOp): boolean {
    return op === 'percentage' || op === 'groupShare';
}

/**
 * Renders a single deterministic figure computed by the engine (e.g.
 * "3.2% — 320 of 10,000"). The headline and context are both PHP-computed
 * numbers; the model only chose which results to combine.
 */
export function DerivedView({
    derived,
    locale,
}: {
    derived: Derived;
    locale: string;
}) {
    const headline = isPercent(derived.op)
        ? formatPercent(derived.value, locale)
        : formatNumber(derived.value, locale);

    return (
        <div className="flex flex-col items-center py-6">
            <div className="text-5xl font-semibold tracking-[-0.03em] text-[var(--rdw-orange)] tabular-nums">
                {headline}
            </div>
            <div className="mt-2 text-sm text-muted-foreground tabular-nums">
                {formatNumber(derived.numerator, locale)}{' '}
                {OPERAND_SYMBOL[derived.op]}{' '}
                {formatNumber(derived.denominator, locale)}
            </div>
        </div>
    );
}
