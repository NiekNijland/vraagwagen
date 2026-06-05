<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use Tests\TestCase;

class ClearOpcacheTest extends TestCase
{
    public function test_clear_opcache_requires_a_header_token(): void
    {
        config(['app.deploy_token' => 'test-deploy-token']);

        $this->get(route('deploy.clear-opcache'))
            ->assertForbidden();
    }

    public function test_clear_opcache_rejects_query_string_tokens(): void
    {
        config(['app.deploy_token' => 'test-deploy-token']);

        $this->get(route('deploy.clear-opcache', ['token' => 'test-deploy-token']))
            ->assertForbidden();
    }

    public function test_clear_opcache_accepts_the_deploy_header_token(): void
    {
        config(['app.deploy_token' => 'test-deploy-token']);

        $this->withHeader('X-Deploy-Token', 'test-deploy-token')
            ->get(route('deploy.clear-opcache'))
            ->assertOk()
            ->assertSeeText('OPCACHE_CLEARED');
    }
}
