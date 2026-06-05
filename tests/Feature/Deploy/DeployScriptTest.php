<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use Tests\TestCase;

final class DeployScriptTest extends TestCase
{
    public function test_deploy_script_prepares_writable_shared_storage_before_the_first_artisan_boot(): void
    {
        $script = file_get_contents(base_path('scripts/deploy.sh'));

        self::assertIsString($script);
        self::assertStringContainsString('storage/framework/cache/data', $script);
        self::assertStringContainsString('storage/framework/sessions', $script);
        self::assertStringContainsString('storage/framework/testing', $script);
        self::assertStringContainsString('storage/framework/views', $script);
        self::assertStringContainsString('storage/logs', $script);
        self::assertStringContainsString('touch storage/logs/laravel.log', $script);
        self::assertStringContainsString('chmod -R ug+rwX storage/framework storage/logs', $script);

        $linkStoragePosition = strpos($script, "run_step 'Link shared storage'");
        $prepareStoragePosition = strpos($script, "run_step 'Prepare writable shared storage'");
        $maintenanceModePosition = strpos($script, "run_step 'Enable maintenance mode'");

        self::assertIsInt($linkStoragePosition);
        self::assertIsInt($prepareStoragePosition);
        self::assertIsInt($maintenanceModePosition);
        self::assertGreaterThan($linkStoragePosition, $prepareStoragePosition);
        self::assertGreaterThan($prepareStoragePosition, $maintenanceModePosition);
    }
}
