<?php

declare(strict_types=1);

namespace Tests\Feature\Bootstrap;

use Illuminate\Contracts\Debug\ExceptionHandler;
use ReflectionClass;
use ReflectionFunction;
use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    public function test_sentry_registers_a_reportable_exception_callback(): void
    {
        $handler = app(ExceptionHandler::class);
        $reflection = new ReflectionClass($handler);

        $withoutDuplicatesProperty = $reflection->getProperty('withoutDuplicates');
        $withoutDuplicatesProperty->setAccessible(true);

        self::assertTrue($withoutDuplicatesProperty->getValue($handler));

        $reportCallbacksProperty = $reflection->getProperty('reportCallbacks');
        $reportCallbacksProperty->setAccessible(true);

        $reportCallbacks = $reportCallbacksProperty->getValue($handler);

        self::assertIsArray($reportCallbacks);
        self::assertNotEmpty($reportCallbacks);

        $callbackFiles = array_map(function (object $reportCallback): string|false {
            $reportCallbackReflection = new ReflectionClass($reportCallback);
            $callbackProperty = $reportCallbackReflection->getProperty('callback');
            $callbackProperty->setAccessible(true);

            return (new ReflectionFunction($callbackProperty->getValue($reportCallback)))->getFileName();
        }, $reportCallbacks);

        $integrationPath = realpath(base_path('vendor/sentry/sentry-laravel/src/Sentry/Laravel/Integration.php'));

        self::assertIsString($integrationPath);

        self::assertContains(
            $integrationPath,
            $callbackFiles,
        );
    }
}
