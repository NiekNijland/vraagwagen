import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

// Testing Library mounts into a shared document; tear each render down so
// queries in one test never see nodes left behind by another.
afterEach(() => {
    cleanup();
});

// jsdom ships none of the layout/observer APIs the chart and theme code touch.
// Stub them once here so component tests don't each have to. Report
// `prefers-reduced-motion: reduce` as a match so count-ups and the typewriter
// resolve to their final value synchronously — tests assert outcomes, not
// animation frames, and this keeps them off real-timer waits.
if (typeof window.matchMedia !== 'function') {
    window.matchMedia = (query: string) =>
        ({
            matches: query.includes('prefers-reduced-motion'),
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }) as unknown as MediaQueryList;
}

class ObserverStub {
    observe(): void {}

    unobserve(): void {}

    disconnect(): void {}
}

vi.stubGlobal('ResizeObserver', ObserverStub);
vi.stubGlobal('IntersectionObserver', ObserverStub);

if (typeof Element.prototype.scrollIntoView !== 'function') {
    Element.prototype.scrollIntoView = vi.fn();
}
