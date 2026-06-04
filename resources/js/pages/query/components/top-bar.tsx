import { LanguageSwitcher } from '@/components/language-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// ─── Top bar ───────────────────────────────────────────────────
export function TopBar() {
    const { t } = useTranslation();

    return (
        <header className="relative z-20 flex items-center justify-between px-6 py-5 md:px-8">
            <a
                href="/"
                aria-label={t('pages.query.homeAriaLabel')}
                className="inline-flex items-center gap-2.5 text-foreground no-underline"
            >
                <span
                    className={cn(
                        'relative grid h-9 w-9 place-items-center overflow-hidden rounded-[4px] font-mono text-base font-bold tracking-tight',
                        'bg-[var(--rdw-orange)] text-white',
                        'shadow-[0_8px_24px_-10px_var(--rdw-orange-glow),inset_0_0_0_1px_rgba(255,255,255,0.22)]',
                    )}
                    aria-hidden="true"
                >
                    V
                    <span className="pointer-events-none absolute inset-x-[-10%] top-[38%] h-px bg-gradient-to-r from-transparent via-white/45 to-transparent" />
                    <span className="pointer-events-none absolute inset-x-[-10%] top-[62%] h-0.5 bg-gradient-to-r from-transparent via-white/85 to-transparent" />
                </span>
                <span className="leading-[1.05]">
                    <span className="block font-mono text-[16px] font-bold tracking-tight">
                        vraagwagen
                        <span className="text-[var(--rdw-orange)]">.nl</span>
                    </span>
                    <span className="block font-mono text-[11px] text-muted-foreground">
                        RDW open data
                    </span>
                </span>
            </a>
            <div className="inline-flex items-center gap-2">
                <ThemeToggle />
                <LanguageSwitcher />
            </div>
        </header>
    );
}
