<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GetAdminStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class StatsController extends Controller
{
    public function index(Request $request, GetAdminStats $stats): InertiaResponse
    {
        /** @var array{days: ?string} $validated */
        $validated = $request->validate([
            'days' => ['nullable', Rule::in(['7', '30', '90'])],
        ]);

        $days = (int) ($validated['days'] ?? 30);

        return Inertia::render('admin/stats/index', [
            'stats' => $stats->execute($days),
            'days' => $days,
        ]);
    }
}
