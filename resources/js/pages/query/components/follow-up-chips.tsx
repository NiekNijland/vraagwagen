import { Sparkles } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';

/**
 * Renders the model's suggested follow-up questions as a row of clickable
 * chips beneath the current result. Each chip's text is the full prompt the
 * user effectively "types" — the parent re-runs the composer pipeline with it.
 */
export function FollowUpChips({
    items,
    onPick,
}: {
    items: string[];
    onPick: (prompt: string) => void;
}) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t pt-3">
            <span className="font-mono text-[10.5px] font-semibold tracking-[0.12em] text-[var(--rdw-orange)] uppercase">
                {t('pages.query.followUps.title')}
            </span>
            <div className="flex flex-wrap gap-1.5">
                {items.map((prompt) => (
                    <button
                        key={prompt}
                        type="button"
                        onClick={() => onPick(prompt)}
                        title={prompt}
                        className="group inline-flex items-center gap-1.5 rounded-[3px] border bg-card/60 px-3 py-1.5 text-[12.5px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:bg-[var(--rdw-orange-faint)] hover:text-foreground"
                    >
                        <Sparkles className="h-3 w-3 text-[var(--rdw-orange)]" />
                        {prompt}
                    </button>
                ))}
            </div>
        </div>
    );
}
