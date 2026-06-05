import { Head } from '@inertiajs/react';

import { useTranslation } from '@/hooks/use-translation';
import { home } from '@/routes';

import { TopBar } from './components/top-bar';

export default function QueryPrivacyPage() {
    const { t, currentLocale } = useTranslation();
    const locale = currentLocale();

    return (
        <>
            <Head title={t('pages.privacy.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('pages.privacy.metaDescription')}
                />
            </Head>

            <div className="rdw-app relative isolate flex min-h-screen flex-col overflow-x-hidden bg-background text-foreground">
                <div className="rdw-bg" aria-hidden="true" />
                <div className="rdw-grid" aria-hidden="true" />

                <TopBar />

                <main className="relative z-10 mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 pb-20 md:px-8">
                    <div className="mt-8 rounded-xl border border-border/70 bg-background/95 p-6 shadow-sm backdrop-blur sm:p-8">
                        <p className="font-mono text-[11px] font-semibold tracking-[0.18em] text-[var(--rdw-orange)] uppercase">
                            {t('pages.privacy.eyebrow')}
                        </p>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                            {t('pages.privacy.heading')}
                        </h1>
                        <p className="mt-4 text-sm leading-7 text-muted-foreground sm:text-[15px]">
                            {t('pages.privacy.intro')}
                        </p>

                        <div className="mt-8 grid gap-4 sm:grid-cols-2">
                            {[
                                {
                                    title: t(
                                        'pages.privacy.cards.questions.title',
                                    ),
                                    body: t(
                                        'pages.privacy.cards.questions.body',
                                    ),
                                },
                                {
                                    title: t(
                                        'pages.privacy.cards.accounts.title',
                                    ),
                                    body: t(
                                        'pages.privacy.cards.accounts.body',
                                    ),
                                },
                                {
                                    title: t(
                                        'pages.privacy.cards.cookies.title',
                                    ),
                                    body: t('pages.privacy.cards.cookies.body'),
                                },
                                {
                                    title: t(
                                        'pages.privacy.cards.contact.title',
                                    ),
                                    body: t('pages.privacy.cards.contact.body'),
                                },
                            ].map((card) => (
                                <section
                                    key={card.title}
                                    className="rounded-lg border border-border/70 bg-card/80 p-4"
                                >
                                    <h2 className="text-sm font-semibold text-foreground">
                                        {card.title}
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {card.body}
                                    </p>
                                </section>
                            ))}
                        </div>

                        <div className="mt-8 flex flex-wrap items-center gap-3 border-t border-border/70 pt-5 text-sm text-muted-foreground">
                            <span>{t('pages.privacy.updated')}</span>
                            <a
                                href={home.url(locale)}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                {t('pages.privacy.backToSearch')}
                            </a>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
