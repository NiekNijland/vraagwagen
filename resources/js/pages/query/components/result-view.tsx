import {
    ChevronDown,
    Download,
    HelpCircle,
    Share2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { downloadRows } from '@/lib/export-rows';

import { localeTag } from '../format';
import { buildShareUrl } from '../share-url';
import type { QueryError, QueryResult, Rating } from '../types';
import { ResultBody } from '../views/result-body';
import { FeedbackPanel } from './feedback-panel';
import { FollowUpChips } from './follow-up-chips';
import { PlanRationaleBody } from './plan-rationale';
import {
    hasQueryDetail,
    QueryDebugBody,
    QueryDebugPanel,
} from './query-debug-panel';

export function ResultView({
    result,
    locale,
    onRatingChange,
    onPickFollowUp,
}: {
    result: QueryResult;
    locale: string;
    onRatingChange: (next: {
        rating: Rating | null;
        comment: string | null;
    }) => void;
    onPickFollowUp?: (prompt: string) => void;
}) {
    const isUnsupported = result.displayHint === 'unsupported';
    const followUps = result.presentation?.followUps ?? [];

    // The presentation carries the authoritative one-line summary (the refusal "why" for an
    // unsupported question, the answer summary otherwise); fall back to the plan's copy.
    const explanation =
        result.presentation?.explanation || result.plan.explanation;

    return (
        <div className="flex flex-col gap-4">
            <div>
                <p className="max-w-[640px] text-sm leading-relaxed text-muted-foreground">
                    <strong className="font-semibold text-foreground">
                        {explanation}
                    </strong>
                </p>
                <UsageLine result={result} locale={locale} />
            </div>

            {/* A refusal renders its model-supplied alternatives inside ResultBody, so pass the
                picker through; an answered query gets the model's follow-up chips below. */}
            <ResultBody
                result={result}
                locale={locale}
                onPickSuggestion={onPickFollowUp}
            />

            {!isUnsupported && onPickFollowUp !== undefined && (
                <FollowUpChips items={followUps} onPick={onPickFollowUp} />
            )}

            <ResultToolbar result={result} locale={locale} />

            {result.slug !== undefined && (
                <FeedbackPanel
                    key={result.slug}
                    slug={result.slug}
                    rating={result.rating}
                    comment={result.comment}
                    onChange={onRatingChange}
                />
            )}

            {!isUnsupported && (
                <ResultDisclosures result={result} locale={locale} />
            )}
        </div>
    );
}

/**
 * The "why this result?" and "show query" toggles share one row so neither
 * wastes vertical space when collapsed; the opened panels stack full-width
 * below.
 */
function ResultDisclosures({
    result,
    locale,
}: {
    result: QueryResult;
    locale: string;
}) {
    const { t } = useTranslation();
    const [showRationale, setShowRationale] = useState(false);
    const [showQuery, setShowQuery] = useState(false);
    const queryProps = {
        soql: result.soql,
        url: result.url,
        model: result.model,
        steps: result.steps,
        correlationId: result.correlationId,
    };
    const canShowQuery = hasQueryDetail(queryProps);

    return (
        <div className="flex flex-col gap-3">
            <div className="-ml-2 flex flex-wrap items-center gap-1">
                <DisclosureToggle
                    icon={<HelpCircle className="h-3 w-3" />}
                    label={t('pages.query.rationale.title')}
                    open={showRationale}
                    onToggle={() => setShowRationale((v) => !v)}
                />
                {canShowQuery && (
                    <DisclosureToggle
                        label={t('pages.query.showQuery')}
                        open={showQuery}
                        onToggle={() => setShowQuery((v) => !v)}
                    />
                )}
            </div>

            {showRationale && (
                <PlanRationaleBody
                    plan={result.plan}
                    steps={result.steps}
                    locale={locale}
                />
            )}
            {showQuery && canShowQuery && <QueryDebugBody {...queryProps} />}
        </div>
    );
}

function DisclosureToggle({
    icon,
    label,
    open,
    onToggle,
}: {
    icon?: React.ReactNode;
    label: string;
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-expanded={open}
            className="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[12.5px] text-muted-foreground transition hover:text-foreground"
        >
            {icon}
            <span>{label}</span>
            <ChevronDown
                className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`}
            />
        </button>
    );
}

function UsageLine({
    result,
    locale,
}: {
    result: QueryResult;
    locale: string;
}) {
    const { t } = useTranslation();
    const total =
        result.tokens.prompt +
        result.tokens.completion +
        result.tokens.cacheRead +
        result.tokens.thought;

    if (total === 0 && result.estimatedCost === null) {
        return null;
    }

    const tokensLabel = t('pages.query.tokensCount', {
        count: new Intl.NumberFormat(localeTag(locale)).format(total),
    });
    const costLabel =
        result.estimatedCost === null
            ? null
            : t('pages.query.estimatedCost', {
                  amount: new Intl.NumberFormat(localeTag(locale), {
                      style: 'currency',
                      currency: 'USD',
                      minimumFractionDigits: 4,
                      maximumFractionDigits: 6,
                  }).format(result.estimatedCost),
              });

    const segments = [tokensLabel, costLabel].filter((s): s is string =>
        Boolean(s),
    );

    return (
        <p className="mt-1 text-[11.5px] tracking-wide text-muted-foreground/80">
            {segments.join(' · ')}
        </p>
    );
}

// Toolbar (share, csv, json).
function ResultToolbar({
    result,
    locale,
}: {
    result: QueryResult;
    locale: string;
}) {
    const { t } = useTranslation();
    const [, copy] = useClipboard();
    const hasRows = result.rows.length > 0;
    const shareUrl =
        result.slug !== undefined ? buildShareUrl(locale, result.slug) : null;

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-3">
            {shareUrl && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={async () => {
                        const ok = await copy(shareUrl);

                        if (ok) {
                            toast.success(t('pages.query.shareCopied'));
                        } else {
                            toast.error(t('pages.query.shareFailed'));
                        }
                    }}
                >
                    <Share2 className="h-3 w-3" />
                    {t('pages.query.share')}
                </Button>
            )}

            {hasRows && (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            downloadRows(result.rows, 'csv', result.prompt)
                        }
                    >
                        <Download className="h-3 w-3" />
                        {t('pages.query.exportCsv')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            downloadRows(result.rows, 'json', result.prompt)
                        }
                    >
                        <Download className="h-3 w-3" />
                        {t('pages.query.exportJson')}
                    </Button>
                </>
            )}
        </div>
    );
}

export function ErrorView({ error }: { error: QueryError }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-start gap-3">
                <span className="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-[var(--rdw-orange-faint)] text-[var(--rdw-orange)]">
                    <Wrench className="h-4 w-4" />
                </span>
                <div className="flex flex-col gap-1">
                    <p className="text-sm leading-relaxed text-[var(--rdw-orange)]">
                        {error.message}
                    </p>
                    {error.correlationId && (
                        <p className="font-mono text-[11px] text-muted-foreground">
                            ID: {error.correlationId}
                        </p>
                    )}
                </div>
            </div>
            <QueryDebugPanel
                soql={error.soql}
                url={error.url}
                responseBody={error.responseBody}
                defaultOpen
            />
        </div>
    );
}
