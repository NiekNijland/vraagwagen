<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ToastType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetRateLimitRequest;
use App\Http\Requests\Admin\UpdateRateLimitRequest;
use App\Services\RateLimit\RateLimitInspector;
use App\Services\RateLimit\RateLimitSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class RateLimitController extends Controller
{
    public function index(
        Request $request,
        RateLimitSettings $settings,
        RateLimitInspector $inspector,
    ): InertiaResponse {
        /** @var array{ip: ?string} $validated */
        $validated = $request->validate([
            'ip' => ['nullable', 'ip'],
        ]);

        $ip = $validated['ip'] ?? null;

        return Inertia::render('admin/rate-limits/index', [
            'limits' => $settings->all(),
            'globalUsage' => $inspector->globalUsage(),
            'ip' => $ip,
            'ipUsage' => $ip !== null ? $inspector->ipUsage($ip) : null,
        ]);
    }

    public function update(UpdateRateLimitRequest $request, RateLimitSettings $settings): RedirectResponse
    {
        /** @var array<string, int> $values */
        $values = array_map(intval(...), $request->validated());

        $settings->update($values);

        Inertia::flash('toast', ['type' => ToastType::Success->value, 'message' => __('Rate limits updated.')]);

        return to_route('admin.rate-limits.index');
    }

    public function reset(ResetRateLimitRequest $request, RateLimitInspector $inspector): RedirectResponse
    {
        $scope = $request->validated('scope');

        if ($scope === 'global') {
            $inspector->resetGlobal();
        } else {
            $inspector->resetIp((string) $request->validated('ip'));
        }

        Inertia::flash('toast', ['type' => ToastType::Success->value, 'message' => __('Rate limit counters reset.')]);

        return redirect()->back(fallback: route('admin.rate-limits.index'));
    }
}
