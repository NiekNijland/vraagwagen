<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class MakeAdminCommandTest extends TestCase
{
    public function test_promotes_an_existing_user_to_admin(): void
    {
        $user = User::factory()->createOne(['email' => 'niek@example.com']);

        /** @var PendingCommand $command */
        $command = $this->artisan('app:make-admin', ['email' => 'niek@example.com']);

        $command
            ->expectsOutputToContain('is now an admin')
            ->assertExitCode(0)
            ->execute();

        $refreshedUser = User::query()->where('email', $user->email)->first();
        assert($refreshedUser instanceof User);

        self::assertTrue($refreshedUser->is_admin);
    }

    public function test_is_idempotent_for_existing_admins(): void
    {
        User::factory()->admin()->createOne(['email' => 'niek@example.com']);

        /** @var PendingCommand $command */
        $command = $this->artisan('app:make-admin', ['email' => 'niek@example.com']);

        $command
            ->expectsOutputToContain('is already an admin')
            ->assertExitCode(0)
            ->execute();
    }

    public function test_fails_for_unknown_email(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('app:make-admin', ['email' => 'missing@example.com']);

        $command
            ->expectsOutputToContain('No user found')
            ->assertExitCode(1)
            ->execute();
    }
}
