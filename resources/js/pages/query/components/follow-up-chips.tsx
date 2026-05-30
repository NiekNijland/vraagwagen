import { Sparkles } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';

import type { FollowUp } from '../follow-ups';

/**
 * Renders the suggested follow-up prompts as a row of clickable chips beneath
 * the current result. Each chip's `prompt` is what the user effectively
 * "types" — the parent re-runs the composer pipeline with it.
 */
export function FollowUpChips({
    items,
    onPick,
}: {
    items: FollowUp[];
    onPick: (prompt: string) => void;
}) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t pt-3">
            <span className="text-[10.5px] font-semibold tracking-[0.12em] text-[var(--rdw-orange)] uppercase">
                {t('pages.query.followUps.title')}
            </span>
            <div className="flex flex-wrap gap-1.5">
                {items.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => onPick(item.prompt)}
                        title={item.prompt}
                        className="group inline-flex items-center gap-1.5 rounded-full border bg-card/60 px-3 py-1.5 text-[12.5px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:bg-[var(--rdw-orange-faint)] hover:text-foreground"
                    >
                        <Sparkles className="h-3 w-3 text-[var(--rdw-orange)]" />
                        {item.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
