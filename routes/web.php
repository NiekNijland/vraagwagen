<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Http\Controllers\Auth\UpdateLocaleController;
use App\Http\Controllers\ClearOpcacheController;
use App\Http\Controllers\Rdw\QueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static function (Request $request) {
    $query = $request->getQueryString();

    return redirect('/' . app()->getLocale() . ($query !== null && $query !== '' ? '?' . $query : ''));
});

Route::prefix('{locale}')
    ->whereIn('locale', array_map(
        static fn (Locale $locale): string => $locale->value,
        Locale::cases(),
    ))
    ->group(function (): void {
        Route::get('/', [QueryController::class, 'index'])->name('home');

        Route::get('/privacy', static fn () => Inertia::render('query/privacy')->withViewData([
            'meta' => [
                'title' => __('pages.privacy.title'),
                'description' => __('pages.privacy.metaDescription'),
                'canonical' => url()->current(),
                'ogTitle' => __('pages.privacy.title'),
                'ogDescription' => __('pages.privacy.metaDescription'),
                'ogType' => 'website',
                'ogUrl' => url()->current(),
                'ogImage' => url('/apple-touch-icon.png'),
                'twitterCard' => 'summary_large_image',
                'twitterTitle' => __('pages.privacy.title'),
                'twitterDescription' => __('pages.privacy.metaDescription'),
                'twitterImage' => url('/apple-touch-icon.png'),
            ],
        ]))->name('privacy');

        Route::get('/{slug}', [QueryController::class, 'index'])
            ->where('slug', '[A-Za-z0-9]+')
            ->name('rdw.query.shared');
    });

Route::get('sitemap.xml', static function () {
    $urls = [];

    foreach (Locale::cases() as $locale) {
        $urls[] = route('home', ['locale' => $locale->value], absolute: true);
        $urls[] = route('privacy', ['locale' => $locale->value], absolute: true);
    }

    return Response::make(
        view('sitemap', ['urls' => $urls])->render(),
        200,
        ['Content-Type' => 'application/xml; charset=UTF-8'],
    );
})->name('sitemap');

Route::post('/api/query', [QueryController::class, 'run'])
    ->middleware('throttle:rdw-query')
    ->name('rdw.query.run');

Route::post('/api/query/{slug}/feedback', [QueryController::class, 'feedback'])
    ->where('slug', '[A-Za-z0-9]+')
    ->middleware('throttle:rdw-feedback')
    ->name('rdw.query.feedback');

Route::post('locale', UpdateLocaleController::class)->name('locale.update');

Route::get('deploy/clear-opcache', ClearOpcacheController::class)
    ->middleware('throttle:5,1')
    ->name('deploy.clear-opcache');

Route::middleware(['auth'])->group(function (): void {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__ . '/settings.php';
