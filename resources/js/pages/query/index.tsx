import { Head } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { toast } from 'sonner';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

import { parseJson, postJson } from './api';
import {
    ComposerCard,
    MIN_PROMPT_LENGTH,
    PROMPT_MAX_LENGTH,
} from './components/composer-card';
import { DiscoverCards } from './components/discover-cards';
import { EyebrowChip } from './components/eyebrow-chip';
import { Hero } from './components/hero';
import { LoadingSkeleton } from './components/loading-skeleton';
import { RecentChips } from './components/recent-chips';
import { ResultPanel } from './components/result-panel';
import { ErrorView, ResultView } from './components/result-view';
import { StatStrip } from './components/stat-strip';
import { TopBar } from './components/top-bar';
import { pickDiscoverItems, SUGGESTIONS_EN, SUGGESTIONS_NL } from './examples';
import {
    clearRecentQueries,
    getRecentQueriesServerSnapshot,
    pushRecentQuery,
    readRecentQueries,
    subscribeToRecentQueries,
} from './recent-queries';
import { resetShareUrl, updateShareUrl } from './share-url';
import type {
    ErrorResponse,
    PlatformStats,
    QueryError,
    QueryResult,
    Rating,
    RunResponse,
    SharedRun,
} from './types';

type PageProps = {
    sharedRun: SharedRun | null;
    promptMinLength?: number;
    promptMaxLength?: number;
    /** Deferred Inertia prop: absent on first paint, filled in once the follow-up request lands. */
    platformStats?: PlatformStats;
};

// Auto-resize the composer textarea up to this many pixels (~5 lines at the composer's text size),
// then start scrolling. Keeps the box visually balanced without growing off-screen on long pastes.
const COMPOSER_MAX_HEIGHT_PX = 220;

function fallbackErrorForStatus(
    status: number,
    t: (key: string) => string,
): string {
    if (status === 429) {
        return t('pages.query.errors.rateLimited');
    }

    if (status === 422) {
        return t('pages.query.errors.rejected');
    }

    if (status === 419) {
        return t('pages.query.errors.sessionExpired');
    }

    if (status === 504) {
        return t('pages.query.errors.timeout');
    }

    if (status >= 500) {
        return t('pages.query.errors.server');
    }

    return t('pages.query.errors.failed');
}

export default function QueryPage({
    sharedRun,
    promptMinLength,
    promptMaxLength,
    platformStats,
}: PageProps) {
    return (
        <QueryPageInner
            key={sharedRun?.slug ?? 'fresh'}
            sharedRun={sharedRun}
            promptMinLength={promptMinLength}
            promptMaxLength={promptMaxLength}
            platformStats={platformStats}
        />
    );
}

function QueryPageInner({
    sharedRun,
    promptMinLength = MIN_PROMPT_LENGTH,
    promptMaxLength = PROMPT_MAX_LENGTH,
    platformStats,
}: PageProps) {
    const { t, currentLocale } = useTranslation();
    const locale = currentLocale();

    const [prompt, setPrompt] = useState(sharedRun?.prompt ?? '');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<QueryResult | null>(
        sharedRun ? sharedRunToResult(sharedRun) : null,
    );
    const [error, setError] = useState<QueryError | null>(null);
    const recent = useSyncExternalStore(
        subscribeToRecentQueries,
        readRecentQueries,
        getRecentQueriesServerSnapshot,
    );
    const abortRef = useRef<AbortController | null>(null);
    const taRef = useRef<HTMLTextAreaElement>(null);

    useEffect(
        () => () => {
            abortRef.current?.abort();
        },
        [],
    );

    // Auto-resize textarea
    useEffect(() => {
        const el = taRef.current;

        if (!el) {
            return;
        }

        el.style.height = 'auto';
        el.style.height =
            Math.min(COMPOSER_MAX_HEIGHT_PX, el.scrollHeight) + 'px';
    }, [prompt]);

    const submit = async (overridePrompt?: string) => {
        const value = (overridePrompt ?? prompt).trim();

        if (value.length < promptMinLength) {
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setLoading(true);
        setResult(null);
        setError(null);

        try {
            const response = await postJson(
                '/api/query',
                { prompt: value },
                controller.signal,
            );

            const data = await parseJson(response);

            if (!response.ok) {
                const errorData =
                    data && typeof data === 'object'
                        ? (data as ErrorResponse)
                        : {};
                const errorMessage =
                    errorData.error ??
                    fallbackErrorForStatus(response.status, t);
                toast.error(errorMessage);
                setError({
                    message: errorMessage,
                    correlationId: errorData.correlationId,
                    soql: errorData.soql,
                    url: errorData.url,
                    responseBody: errorData.responseBody,
                });

                return;
            }

            const runData = data as RunResponse;

            setResult({
                ...runData,
                prompt: value,
                rating: null,
                comment: null,
            });
            pushRecentQuery(value);
            updateShareUrl(locale, runData.slug);
        } catch (e) {
            if (e instanceof DOMException && e.name === 'AbortError') {
                return;
            }

            const message =
                e instanceof Error
                    ? e.message
                    : t('pages.query.errors.network');
            toast.error(message);
            setError({ message });
        } finally {
            if (abortRef.current === controller) {
                abortRef.current = null;
                setLoading(false);
            }
        }
    };

    const clear = (): void => {
        abortRef.current?.abort();
        abortRef.current = null;
        setPrompt('');
        setResult(null);
        setError(null);
        setLoading(false);
        resetShareUrl(locale);
    };

    const canClear = prompt.length > 0 || result !== null || error !== null;
    const hasResult = result !== null || error !== null || loading;

    const updateRating = useCallback(
        (next: { rating: Rating | null; comment: string | null }): void => {
            setResult((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          rating: next.rating,
                          comment: next.comment,
                      },
            );
        },
        [],
    );

    const suggestions = locale === 'nl' ? SUGGESTIONS_NL : SUGGESTIONS_EN;
    const discoverItems = useMemo(() => pickDiscoverItems(locale), [locale]);

    // Screen readers can't see the result swap in, so announce the high-level
    // status politely. Errors already surface through the toast's own live
    // region, so they're intentionally left out here.
    const liveMessage = loading
        ? t('pages.query.thinking')
        : result !== null
          ? result.plan.explanation
          : '';

    return (
        <>
            <Head title={t('pages.query.title')} />
            <div className="rdw-app relative isolate flex min-h-screen flex-col overflow-x-hidden bg-background text-foreground">
                <div className="rdw-bg" aria-hidden="true" />
                <div className="rdw-grid" aria-hidden="true" />

                <p className="sr-only" role="status" aria-live="polite">
                    {liveMessage}
                </p>

                <TopBar />

                <main
                    className={cn(
                        'relative z-10 mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-6 pb-32',
                        hasResult ? 'pt-2' : 'pt-3',
                    )}
                >
                    <EyebrowChip compact={hasResult} />

                    <div className="flex w-full max-w-[880px] flex-col items-center text-center">
                        <Hero compact={hasResult} />

                        <ComposerCard
                            taRef={taRef}
                            value={prompt}
                            setValue={setPrompt}
                            onSubmit={() => void submit()}
                            onClear={canClear ? clear : undefined}
                            busy={loading}
                            compact={hasResult}
                            placeholderSuggestions={suggestions}
                            minLength={promptMinLength}
                            maxLength={promptMaxLength}
                        />

                        {!hasResult && (
                            <DiscoverCards
                                items={discoverItems}
                                onPick={(q) => {
                                    setPrompt(q);
                                    void submit(q);
                                }}
                            />
                        )}

                        {!hasResult && recent.length > 0 && (
                            <RecentChips
                                items={recent}
                                onPick={(q) => {
                                    setPrompt(q);
                                    void submit(q);
                                }}
                                onClearAll={() => clearRecentQueries()}
                            />
                        )}

                        {loading && (
                            <ResultPanel query={prompt}>
                                <LoadingSkeleton />
                            </ResultPanel>
                        )}

                        {!loading && result && (
                            <ResultPanel
                                query={result.prompt}
                                live
                                onEditPrompt={() => {
                                    setPrompt(result.prompt);
                                    taRef.current?.focus();
                                    taRef.current?.setSelectionRange(
                                        result.prompt.length,
                                        result.prompt.length,
                                    );
                                }}
                            >
                                <ResultView
                                    result={result}
                                    locale={locale}
                                    onRatingChange={updateRating}
                                    onPickFollowUp={(followPrompt) => {
                                        setPrompt(followPrompt);
                                        void submit(followPrompt);
                                    }}
                                />
                            </ResultPanel>
                        )}

                        {!loading && error && (
                            <ResultPanel query={prompt}>
                                <ErrorView error={error} />
                            </ResultPanel>
                        )}
                    </div>
                </main>

                <StatStrip stats={platformStats} locale={locale} />
            </div>
        </>
    );
}

// ─── Helpers ─────────────────────────────────────────────────
function sharedRunToResult(run: SharedRun): QueryResult {
    return {
        slug: run.slug,
        correlationId: run.correlationId,
        prompt: run.prompt,
        plan: run.plan,
        soql: run.soql,
        url: run.url,
        rows: run.rows,
        displayHint: run.displayHint,
        rating: run.rating,
        comment: run.comment,
        model: run.model,
        tokens: run.tokens,
        estimatedCost: run.estimatedCost,
        steps: run.steps,
        presentation: run.presentation,
    };
}
