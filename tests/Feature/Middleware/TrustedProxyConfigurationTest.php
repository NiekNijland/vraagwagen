<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyConfigurationTest extends TestCase
{
    public function test_loopback_proxy_headers_are_trusted(): void
    {
        $this->registerProxyRoute();

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'vraagwagen.test',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/__tests/proxy')
            ->assertOk()
            ->assertExactJson([
                'ip' => '203.0.113.10',
                'secure' => true,
            ]);
    }

    public function test_session_secure_cookie_defaults_to_true_in_production(): void
    {
        $originalAppEnv = getenv('APP_ENV');
        $originalSessionSecureCookie = getenv('SESSION_SECURE_COOKIE');

        try {
            $this->setEnvironmentValue('APP_ENV', 'production');
            $this->setEnvironmentValue('SESSION_SECURE_COOKIE', null);
            $this->refreshApplication();

            self::assertTrue(config('session.secure'));
        } finally {
            $this->setEnvironmentValue('APP_ENV', $originalAppEnv === false ? null : $originalAppEnv);
            $this->setEnvironmentValue('SESSION_SECURE_COOKIE', $originalSessionSecureCookie === false ? null : $originalSessionSecureCookie);
            $this->refreshApplication();
        }
    }

    private function registerProxyRoute(): void
    {
        Route::get('/__tests/proxy', static fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]));
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
