<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Tests\TestCase;

final class MakeAdminCommandTest extends TestCase
{
    public function test_promotes_an_existing_user_to_admin(): void
    {
        $user = User::factory()->createOne(['email' => 'niek@example.com']);

        $this->artisan('app:make-admin', ['email' => 'niek@example.com'])
            ->expectsOutputToContain('is now an admin')
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_is_idempotent_for_existing_admins(): void
    {
        User::factory()->admin()->createOne(['email' => 'niek@example.com']);

        $this->artisan('app:make-admin', ['email' => 'niek@example.com'])
            ->expectsOutputToContain('is already an admin')
            ->assertExitCode(0);
    }

    public function test_fails_for_unknown_email(): void
    {
        $this->artisan('app:make-admin', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('No user found')
            ->assertExitCode(1);
    }
}
