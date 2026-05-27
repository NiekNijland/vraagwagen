import { render } from '@testing-library/react';
import type { RenderOptions, RenderResult } from '@testing-library/react';
import { LaravelReactI18nProvider } from 'laravel-react-internationalization';
import type { ReactElement, ReactNode } from 'react';

// Load the real translation catalogs the same way the app bootstrap does, so
// components render the copy users actually see rather than raw keys.
const langFiles = import.meta.glob('/lang/*.json', { eager: true });

type RenderWithI18nOptions = Omit<RenderOptions, 'wrapper'> & {
    locale?: string;
};

/**
 * Render a component wrapped in the production i18n provider. Defaults to the
 * English catalog; pass `{ locale: 'nl' }` to exercise the Dutch copy.
 */
export function renderWithI18n(
    ui: ReactElement,
    { locale = 'en', ...options }: RenderWithI18nOptions = {},
): RenderResult {
    function Wrapper({ children }: { children: ReactNode }) {
        return (
            <LaravelReactI18nProvider
                locale={locale}
                fallbackLocale="en"
                files={langFiles}
            >
                {children}
            </LaravelReactI18nProvider>
        );
    }

    return render(ui, { wrapper: Wrapper, ...options });
}
