import { ChevronDown, Copy, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';

import { formatResponseBody, SoQLHighlight } from '../soql-highlight';
import type { Step } from '../types';

function soqlToString(soql: Record<string, string>): string {
    return Object.entries(soql)
        .map(([k, v]) => `$${k}: ${v}`)
        .join('\n');
}

type QueryDebugProps = {
    soql?: Record<string, string>;
    url?: string;
    model?: string;
    responseBody?: string | null;
    steps?: Step[];
    correlationId?: string;
};

/**
 * Returns whether there is any query detail worth disclosing, so callers can
 * decide whether to render the toggle at all.
 */
export function hasQueryDetail({
    soql,
    url,
    model,
    responseBody,
    steps,
    correlationId,
}: QueryDebugProps): boolean {
    return (
        soql !== undefined ||
        url !== undefined ||
        (responseBody !== undefined && responseBody !== null) ||
        (model !== undefined && model !== '') ||
        (steps !== undefined && steps.length > 0) ||
        (correlationId !== undefined && correlationId !== '')
    );
}

/**
 * The disclosure trigger lives in the caller (see ResultView) so it can share a
 * row with the rationale toggle; ErrorView still uses the self-contained
 * {@link QueryDebugPanel} below.
 */
export function QueryDebugBody(props: QueryDebugProps) {
    const { soql, url, model, responseBody, steps, correlationId } = props;
    const { t } = useTranslation();
    const [, copy] = useClipboard();
    const hasResponseBody = responseBody !== undefined && responseBody !== null;
    const hasModel = model !== undefined && model !== '';
    const hasCorrelationId =
        correlationId !== undefined && correlationId !== '';
    // Show a per-step breakdown only when the program ran more than one query;
    // a single query is already covered by the SoQL section below.
    const multiStep = steps !== undefined && steps.length > 1;

    if (!hasQueryDetail(props)) {
        return null;
    }

    const soqlString = soql === undefined ? '' : soqlToString(soql);

    return (
        <div className="space-y-3 rounded-[4px] border bg-[color:color-mix(in_oklab,var(--background)_60%,transparent)] p-3.5 text-xs">
            {hasModel && (
                <DebugSection label={t('pages.query.model')}>
                    <code className="block rounded bg-background/80 p-2 font-mono text-[11px]">
                        {model}
                    </code>
                </DebugSection>
            )}
            {multiStep && (
                <DebugSection label={t('pages.query.steps')}>
                    <div className="space-y-3">
                        {steps.map((step) => (
                            <div key={step.id} className="space-y-1.5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded bg-background/80 px-1.5 py-0.5 font-mono text-[10px] font-semibold">
                                        {step.id}
                                    </span>
                                    <span className="text-[11px] text-muted-foreground tabular-nums">
                                        {step.rowCount}
                                    </span>
                                    <GetBadge />
                                    <span className="ml-auto inline-flex items-center gap-1.5">
                                        <UrlActions
                                            url={step.url}
                                            copy={copy}
                                        />
                                    </span>
                                </div>
                                <pre className="overflow-x-auto rounded bg-background/80 p-2.5 font-mono text-[11.5px] leading-relaxed whitespace-pre-wrap">
                                    <SoQLHighlight
                                        value={soqlToString(step.soql)}
                                    />
                                </pre>
                                <UrlBox url={step.url} />
                            </div>
                        ))}
                    </div>
                </DebugSection>
            )}
            {soql && !multiStep && (
                <DebugSection
                    label={t('pages.query.soql')}
                    actions={
                        <CopyChip
                            onCopy={() => copy(soqlString)}
                            label="Copy"
                        />
                    }
                >
                    <pre className="overflow-x-auto rounded bg-background/80 p-2.5 font-mono text-[11.5px] leading-relaxed whitespace-pre-wrap">
                        <SoQLHighlight value={soqlString} />
                    </pre>
                </DebugSection>
            )}
            {url !== undefined && !multiStep && (
                <DebugSection
                    label={t('pages.query.url')}
                    meta={<GetBadge />}
                    actions={<UrlActions url={url} copy={copy} />}
                >
                    <UrlBox url={url} />
                </DebugSection>
            )}
            {hasResponseBody && (
                <DebugSection label={t('pages.query.rdwResponse')}>
                    <pre className="overflow-x-auto rounded bg-background/80 p-2 font-mono text-[11px] leading-relaxed whitespace-pre-wrap">
                        {formatResponseBody(responseBody)}
                    </pre>
                </DebugSection>
            )}
            {hasCorrelationId && (
                <DebugSection
                    label={t('pages.query.referenceId')}
                    actions={
                        <CopyChip
                            onCopy={() => copy(correlationId ?? '')}
                            label="Copy"
                        />
                    }
                >
                    <code className="block rounded bg-background/80 p-2 font-mono text-[11px]">
                        {correlationId}
                    </code>
                </DebugSection>
            )}
        </div>
    );
}

/**
 * Self-contained disclosure used by ErrorView: trigger plus body in one
 * Collapsible. ResultView instead drives {@link QueryDebugBody} from a shared
 * trigger row.
 */
export function QueryDebugPanel({
    defaultOpen = false,
    ...props
}: QueryDebugProps & { defaultOpen?: boolean }) {
    const { t } = useTranslation();

    if (!hasQueryDetail(props)) {
        return null;
    }

    return (
        <Collapsible defaultOpen={defaultOpen}>
            <CollapsibleTrigger className="group -ml-2 inline-flex w-fit items-center gap-1.5 rounded-md px-2 py-1.5 text-[12.5px] text-muted-foreground transition hover:text-foreground">
                <span>{t('pages.query.showQuery')}</span>
                <ChevronDown className="h-3 w-3 transition-transform group-data-[state=open]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-3">
                <QueryDebugBody {...props} />
            </CollapsibleContent>
        </Collapsible>
    );
}

function GetBadge() {
    return (
        <span className="rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[10px] font-semibold tracking-wide text-emerald-500">
            GET
        </span>
    );
}

function UrlActions({
    url,
    copy,
}: {
    url: string;
    copy: (text: string) => Promise<boolean> | boolean;
}) {
    return (
        <>
            <CopyChip onCopy={() => copy(url)} label="Copy" />
            <a
                href={url}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 rounded border bg-card px-2 py-1 text-[11px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:text-foreground"
            >
                <ExternalLink className="h-3 w-3" />
                Open
            </a>
        </>
    );
}

function UrlBox({ url }: { url: string }) {
    return (
        <div className="block overflow-x-auto rounded bg-background/80 p-2.5 font-mono text-[11px] leading-relaxed break-all whitespace-pre-wrap text-muted-foreground">
            <span className="mr-1 text-muted-foreground/60">↳</span>
            {url}
        </div>
    );
}

function DebugSection({
    label,
    meta,
    actions,
    children,
}: {
    label: string;
    meta?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-3">
                <span className="font-mono text-[10.5px] font-semibold tracking-[0.12em] whitespace-nowrap text-[var(--rdw-orange)] uppercase">
                    {label}
                </span>
                {meta}
                {actions !== undefined && (
                    <span className="ml-auto inline-flex items-center gap-1.5">
                        {actions}
                    </span>
                )}
            </div>
            {children}
        </div>
    );
}

function CopyChip({
    onCopy,
    label,
}: {
    onCopy: () => Promise<boolean> | boolean;
    label: string;
}) {
    const [copied, setCopied] = useState(false);

    return (
        <button
            type="button"
            onClick={async () => {
                const ok = await onCopy();

                if (ok) {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                }
            }}
            className="inline-flex items-center gap-1 rounded border bg-card px-2 py-1 text-[11px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:text-foreground"
        >
            <Copy className="h-3 w-3" />
            {copied ? '✓' : label}
        </button>
    );
}
