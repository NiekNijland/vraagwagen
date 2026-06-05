import { Plus } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';

// ─── Recent chips ─────────────────────────────────────────────
export function RecentChips({
    items,
    onPick,
    onClearAll,
}: {
    items: string[];
    onPick: (q: string) => void;
    onClearAll: () => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="mt-5 flex w-full max-w-[880px] flex-col items-center gap-2">
            <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">
                    {t('pages.query.recent')}
                </span>
                <button
                    type="button"
                    onClick={onClearAll}
                    className="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                >
                    {t('pages.query.clearRecent')}
                </button>
            </div>
            <div
                data-testid="recent-chips-list"
                className="flex max-h-28 w-full flex-wrap justify-center gap-1.5 overflow-y-auto px-1 sm:max-h-32"
            >
                {items.map((q) => (
                    <RecentChip key={q} question={q} onPick={onPick} />
                ))}
            </div>
        </div>
    );
}

function RecentChip({
    question,
    onPick,
}: {
    question: string;
    onPick: (q: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onPick(question)}
            className="group inline-flex items-center gap-1.5 rounded-[3px] border bg-card/60 px-3 py-1.5 text-[12.5px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:bg-[var(--rdw-orange-faint)] hover:text-foreground"
        >
            <Plus className="h-3 w-3 text-[var(--rdw-orange)]" />
            {question}
        </button>
    );
}
