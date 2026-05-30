import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

// Layout effects warn during SSR; fall back to a passive effect on the server
// so the count-up stays client-only without noise.
const useIsomorphicLayoutEffect =
    typeof window !== 'undefined' ? useLayoutEffect : useEffect;

function subscribeReducedMotion(callback: () => void): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const mql = window.matchMedia(REDUCED_MOTION);
    mql.addEventListener('change', callback);

    return () => mql.removeEventListener('change', callback);
}

function getReducedMotionSnapshot(): boolean {
    return window.matchMedia(REDUCED_MOTION).matches;
}

function getReducedMotionServerSnapshot(): boolean {
    return false;
}

// Tweens to `target` whenever it changes. Renders `target` directly (no
// animation) when reduced motion is on or the value isn't finite. The first
// (pre-effect) render returns `target`, so the hydration render matches the
// SSR markup and React never warns about a mismatch.
//
// With `animateOnMount`, a layout effect then resets the value to zero and
// tweens up — so the first *painted* frame is zero, not `target`. That happens
// before the browser paints (no flash of the final number) and after hydration
// has already reconciled, so it stays mismatch-safe. The whole tween lives in a
// single layout effect so a StrictMode mount/unmount/remount simply restarts it
// rather than leaving the value stranded mid-animation.
export function useCountUp(
    target: number,
    durationMs = 900,
    animateOnMount = false,
): number {
    const reducedMotion = useSyncExternalStore(
        subscribeReducedMotion,
        getReducedMotionSnapshot,
        getReducedMotionServerSnapshot,
    );
    const [value, setValue] = useState(target);
    // The last target we've fully settled on. Null until the first tween
    // completes, which marks "this is still the mount animation".
    const settledRef = useRef<number | null>(null);
    const rafRef = useRef<number | null>(null);

    useIsomorphicLayoutEffect(() => {
        if (!Number.isFinite(target) || reducedMotion) {
            settledRef.current = target;
            setValue(target);

            return;
        }

        const isMount = settledRef.current === null;
        const from = isMount
            ? animateOnMount
                ? 0
                : target
            : (settledRef.current ?? target);

        if (from === target) {
            settledRef.current = target;
            setValue(target);

            return;
        }

        setValue(from);

        let startedAt: number | null = null;

        const tick = (timestamp: number) => {
            if (startedAt === null) {
                startedAt = timestamp;
            }

            const elapsed = timestamp - startedAt;
            const t = Math.min(1, elapsed / durationMs);
            // easeOutCubic
            const eased = 1 - Math.pow(1 - t, 3);

            setValue(from + (target - from) * eased);

            if (t < 1) {
                rafRef.current = window.requestAnimationFrame(tick);
            } else {
                // Only record completion — a cancelled run keeps settledRef as
                // it was so StrictMode's remount re-runs the same animation.
                settledRef.current = target;
            }
        };

        rafRef.current = window.requestAnimationFrame(tick);

        return () => {
            if (rafRef.current !== null) {
                window.cancelAnimationFrame(rafRef.current);
                rafRef.current = null;
            }
        };
    }, [target, durationMs, reducedMotion, animateOnMount]);

    if (!Number.isFinite(target) || reducedMotion) {
        return target;
    }

    return value;
}
