import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTranslation } from '@/hooks/use-translation';

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
                <span aria-hidden="true">
                    <AppLogoIcon className="h-10 w-auto drop-shadow-[0_8px_24px_var(--rdw-orange-glow)]" />
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
