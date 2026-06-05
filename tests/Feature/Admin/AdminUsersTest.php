<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\QueryRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminUsersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_lists_users_with_query_counts_and_last_activity(): void
    {
        $admin = User::factory()->admin()->createOne(['email' => 'admin@example.com']);
        $user = User::factory()->createOne(['email' => 'user@example.com']);

        QueryRun::factory()->count(2)->create(['user_id' => (string) $user->id]);
        QueryRun::factory()->createOne(['user_id' => null]);

        $this->actingAs($admin);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('users.total', 2)
                ->where('users.data.0.email', 'admin@example.com')
                ->where('users.data.0.isAdmin', true)
                ->where('users.data.0.queryCount', 0)
                ->where('users.data.1.email', 'user@example.com')
                ->where('users.data.1.isAdmin', false)
                ->where('users.data.1.queryCount', 2)
                ->whereNot('users.data.1.lastQueryAt', null)
                ->where('anonymousQueryCount', 1));
    }
}
