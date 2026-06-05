<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\QueryRun;
use App\Models\User;
use Tests\TestCase;

final class AdminExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->createOne());
    }

    public function test_queries_export_streams_csv_with_all_runs(): void
    {
        QueryRun::factory()->createOne(['prompt' => 'first prompt']);
        QueryRun::factory()->ratedDown()->createOne(['prompt' => 'second prompt', 'comment' => 'nope']);

        $response = $this->get(route('admin.queries.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('created_at,slug,locale,prompt', $csv);
        $this->assertStringContainsString('first prompt', $csv);
        $this->assertStringContainsString('second prompt', $csv);
        $this->assertStringContainsString('down', $csv);
    }

    public function test_queries_export_respects_filters(): void
    {
        QueryRun::factory()->createOne(['prompt' => 'tesla question']);
        QueryRun::factory()->createOne(['prompt' => 'porsche question']);

        $csv = $this->get(route('admin.queries.export', ['search' => 'tesla']))->streamedContent();

        $this->assertStringContainsString('tesla question', $csv);
        $this->assertStringNotContainsString('porsche question', $csv);
    }

    public function test_feedback_export_only_includes_rated_runs(): void
    {
        QueryRun::factory()->ratedUp()->createOne(['prompt' => 'rated prompt', 'comment' => 'top']);
        QueryRun::factory()->createOne(['prompt' => 'unrated prompt']);

        $response = $this->get(route('admin.feedback.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('rated prompt', $csv);
        $this->assertStringContainsString('top', $csv);
        $this->assertStringNotContainsString('unrated prompt', $csv);
    }
}
