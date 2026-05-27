<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Priority: route locale -> user -> session -> cookie -> Accept-Language -> app default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeLocale = $request->route('locale');
        $user = $request->user();

        // Tolerate a stale / legacy DB value by falling back to null.
        $userLocale = $user instanceof User
            ? $user->locale?->value
            : null;

        $defaultLocale = (string) config('app.locale');

        $hasRouteLocale = is_string($routeLocale);
        $sessionLocale = $request->session()->get('locale');
        $cookieLocale = $request->cookie('locale');

        $locale = ($hasRouteLocale ? $routeLocale : null)
            ?? $userLocale
            ?? $sessionLocale
            ?? $cookieLocale
            ?? $this->detectFromAcceptLanguage($request)
            ?? $defaultLocale;

        $resolvedLocaleEnum = Locale::tryFrom((string) $locale);
        $resolvedLocale = $resolvedLocaleEnum !== null
            ? $resolvedLocaleEnum->value
            : $defaultLocale;

        $shouldPersist = $this->shouldPersistLocale(
            sessionLocale: is_string($sessionLocale) ? $sessionLocale : null,
            cookieLocale: is_string($cookieLocale) ? $cookieLocale : null,
            hasRouteLocale: $hasRouteLocale,
            user: $user,
            resolvedLocale: $resolvedLocale,
        );

        app()->setLocale($resolvedLocale);
        URL::defaults(['locale' => $resolvedLocale]);

        if ($shouldPersist) {
            $request->session()->put('locale', $resolvedLocale);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($shouldPersist && $cookieLocale !== $resolvedLocale && method_exists($response, 'cookie')) {
            /** @var \Illuminate\Http\Response $response */
            $response->cookie('locale', $resolvedLocale, 60 * 24 * 365);
        }

        return $response;
    }

    /**
     * Persist only when the visitor expressed a preference (locale URL, auth, or existing stored value);
     * an anonymous Accept-Language match alone is not consent for a year-long cookie.
     */
    private function shouldPersistLocale(
        ?string $sessionLocale,
        ?string $cookieLocale,
        bool $hasRouteLocale,
        ?object $user,
        string $resolvedLocale,
    ): bool {
        if ($sessionLocale === $resolvedLocale && $cookieLocale === $resolvedLocale) {
            return false;
        }

        if ($user !== null) {
            return true;
        }

        if ($hasRouteLocale) {
            return true;
        }

        return $sessionLocale !== null || $cookieLocale !== null;
    }

    private function detectFromAcceptLanguage(Request $request): ?string
    {
        $supported = array_map(
            static fn (Locale $locale): string => $locale->value,
            Locale::cases(),
        );

        $preferred = $request->getPreferredLanguage($supported);

        return $preferred !== '' ? $preferred : null;
    }
}
