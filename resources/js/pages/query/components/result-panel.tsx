import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

// ─── Result panel wrapper ─────────────────────────────────────
export function ResultPanel({
    query,
    live,
    onEditPrompt,
    children,
}: {
    query: string;
    live?: boolean;
    onEditPrompt?: () => void;
    children: React.ReactNode;
}) {
    const { t } = useTranslation();
    const trimmed = query.trim();

    return (
        <div className="rdw-result-mount mt-7 flex w-full max-w-[880px] flex-col text-left">
            {trimmed !== '' && (
                <div className="flex items-baseline gap-2.5 px-1.5 pb-3 text-sm text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className="translate-y-0.5 text-lg leading-none font-bold text-[var(--rdw-orange)]"
                    >
                        ↳
                    </span>
                    <span className="text-[10.5px] font-semibold tracking-[0.14em] whitespace-nowrap text-muted-foreground/70 uppercase">
                        {t('pages.query.youAsked')}
                    </span>
                    {onEditPrompt !== undefined ? (
                        <button
                            type="button"
                            onClick={onEditPrompt}
                            className="flex-1 cursor-text rounded-md text-left text-foreground italic decoration-[var(--rdw-orange)]/50 decoration-dotted underline-offset-[3px] transition-colors hover:text-[var(--rdw-orange)] hover:underline focus-visible:ring-2 focus-visible:ring-[var(--rdw-orange)]/40 focus-visible:outline-none"
                        >
                            "{trimmed}"
                        </button>
                    ) : (
                        <span className="flex-1 text-foreground italic">
                            "{trimmed}"
                        </span>
                    )}
                    {live === true && (
                        <Badge className="rounded-full border-emerald-500/30 bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-500 hover:bg-emerald-500/15">
                            <span className="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {t('pages.query.liveData')}
                        </Badge>
                    )}
                </div>
            )}
            <div className="relative overflow-hidden rounded-[22px] border bg-card px-6 py-5 text-left text-card-foreground shadow-[0_20px_50px_-20px_rgba(0,0,0,0.4)]">
                <span className="rdw-accent-line" aria-hidden="true" />
                {children}
            </div>
        </div>
    );
}
