<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep backend assertions independent of the built frontend manifest.
        $this->withoutVite();
    }

    /**
     * @return list<string>
     */
    private static function adminPageRoutes(): array
    {
        return [
            'admin.stats.index',
            'admin.queries.index',
            'admin.queries.export',
            'admin.feedback.index',
            'admin.feedback.export',
            'admin.users.index',
            'admin.rate-limits.index',
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        foreach (self::adminPageRoutes() as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_non_admin_users_get_403(): void
    {
        $this->actingAs(User::factory()->createOne());

        foreach (self::adminPageRoutes() as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_admin_users_can_access_all_admin_pages(): void
    {
        $this->actingAs(User::factory()->admin()->createOne());

        foreach (self::adminPageRoutes() as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_admin_root_redirects_to_stats(): void
    {
        $this->actingAs(User::factory()->admin()->createOne());

        $this->get('/admin')->assertRedirect('/admin/stats');
    }

    public function test_non_admin_users_cannot_mutate_rate_limits(): void
    {
        $this->actingAs(User::factory()->createOne());

        $this->patch(route('admin.rate-limits.update'), [
            'per_minute' => 1,
            'per_day_ip' => 1,
            'per_day_global' => 1,
            'feedback_per_minute' => 1,
        ])->assertForbidden();

        $this->post(route('admin.rate-limits.reset'), ['scope' => 'global'])->assertForbidden();
    }
}
