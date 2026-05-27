import { Download, Share2, Wrench } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { downloadRows } from '@/lib/export-rows';

import { buildShareUrl } from '../share-url';
import type { QueryError, QueryResult, Rating } from '../types';
import { ResultBody } from '../views/result-body';
import { FeedbackPanel } from './feedback-panel';
import { QueryDebugPanel } from './query-debug-panel';

export function ResultView({
    result,
    locale,
    onRatingChange,
}: {
    result: QueryResult;
    locale: string;
    onRatingChange: (next: {
        rating: Rating | null;
        comment: string | null;
    }) => void;
}) {
    const isUnsupported = result.displayHint === 'unsupported';

    return (
        <div className="flex flex-col gap-4">
            <div>
                <p className="max-w-[640px] text-sm leading-relaxed text-muted-foreground">
                    <strong className="font-semibold text-foreground">
                        {result.plan.explanation}
                    </strong>
                </p>
                <UsageLine result={result} locale={locale} />
            </div>

            <ResultBody result={result} locale={locale} />

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
                <QueryDebugPanel
                    soql={result.soql}
                    url={result.url}
                    model={result.model}
                    steps={result.steps}
                />
            )}
        </div>
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
        count: new Intl.NumberFormat(locale).format(total),
    });
    const costLabel =
        result.estimatedCost === null
            ? null
            : t('pages.query.estimatedCost', {
                  amount: new Intl.NumberFormat(locale, {
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
                <p className="text-sm leading-relaxed text-[var(--rdw-orange)]">
                    {error.message}
                </p>
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
