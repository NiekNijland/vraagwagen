<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email : The email address of the user to promote}')]
#[Description('Grant admin access to an existing user')]
final class MakeAdmin extends Command
{
    public function handle(): int
    {
        $emailArgument = $this->argument('email');

        if (! is_string($emailArgument)) {
            $this->error('The email argument must be a string.');

            return self::FAILURE;
        }

        $email = $emailArgument;

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("[{$email}] is already an admin.");

            return self::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("[{$email}] is now an admin.");

        return self::SUCCESS;
    }
}
