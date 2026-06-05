<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\QueryController;
use App\Http\Controllers\Admin\RateLimitController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function (): void {
    Route::redirect('/', '/admin/stats');

    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

    Route::get('queries', [QueryController::class, 'index'])->name('queries.index');
    Route::get('queries/export', [QueryController::class, 'export'])->name('queries.export');
    Route::get('queries/{id}', [QueryController::class, 'show'])->name('queries.show');

    Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/export', [FeedbackController::class, 'export'])->name('feedback.export');

    Route::get('users', [UserController::class, 'index'])->name('users.index');

    Route::get('rate-limits', [RateLimitController::class, 'index'])->name('rate-limits.index');
    Route::patch('rate-limits', [RateLimitController::class, 'update'])->name('rate-limits.update');
    Route::post('rate-limits/reset', [RateLimitController::class, 'reset'])->name('rate-limits.reset');
});
