<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $locale,
            'locales' => $this->localeLabels(),
            'fallbackLocale' => config('app.fallback_locale'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function localeLabels(): array
    {
        $labels = [];
        foreach (Locale::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
