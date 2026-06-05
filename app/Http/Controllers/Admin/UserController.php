<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueryRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use MongoDB\BSON\UTCDateTime;

final class UserController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(): InertiaResponse
    {
        $activity = $this->activityByUser();

        $users = User::query()
            ->orderBy('email')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static function (User $user) use ($activity): array {
                $userActivity = $activity[(string) $user->id] ?? null;

                return [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // Most users predate the flag; a missing attribute casts to null, not false.
                    'isAdmin' => (bool) $user->is_admin,
                    'verified' => $user->email_verified_at !== null,
                    'queryCount' => $userActivity['queries'] ?? 0,
                    'lastQueryAt' => $userActivity['lastAt'] ?? null,
                    'createdAt' => $user->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'anonymousQueryCount' => QueryRun::query()->whereNull('user_id')->count(),
        ]);
    }

    /**
     * One pass over query_runs instead of a count query per listed user; anonymous runs
     * (user_id null) are excluded and shown as a single aggregate figure.
     *
     * @return array<string, array{queries: int, lastAt: string|null}>
     */
    private function activityByUser(): array
    {
        // Materialise inside the closure with an array typeMap: a returned cursor would be
        // hydrated into QueryRun models by Eloquent's raw(), mangling the aggregation shape.
        /** @var list<array<string, mixed>> $documents */
        $documents = QueryRun::raw(static fn ($collection) => iterator_to_array($collection->aggregate([
            ['$match' => ['user_id' => ['$ne' => null]]],
            ['$group' => [
                '_id' => '$user_id',
                'queries' => ['$sum' => 1],
                'lastAt' => ['$max' => '$created_at'],
            ]],
        ], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])));

        $activity = [];

        foreach ($documents as $bucket) {
            $lastAt = $bucket['lastAt'] ?? null;

            $activity[(string) $bucket['_id']] = [
                'queries' => (int) $bucket['queries'],
                'lastAt' => $lastAt instanceof UTCDateTime
                    ? CarbonImmutable::createFromMutable($lastAt->toDateTime())->toIso8601String()
                    : null,
            ];
        }

        return $activity;
    }
}
