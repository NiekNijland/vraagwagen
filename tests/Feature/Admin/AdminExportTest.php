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

        self::assertStringContainsString('created_at,slug,locale,prompt', $csv);
        self::assertStringContainsString('first prompt', $csv);
        self::assertStringContainsString('second prompt', $csv);
        self::assertStringContainsString('down', $csv);
    }

    public function test_queries_export_respects_filters(): void
    {
        QueryRun::factory()->createOne(['prompt' => 'tesla question']);
        QueryRun::factory()->createOne(['prompt' => 'porsche question']);

        $csv = $this->get(route('admin.queries.export', ['search' => 'tesla']))->streamedContent();

        self::assertStringContainsString('tesla question', $csv);
        self::assertStringNotContainsString('porsche question', $csv);
    }

    public function test_feedback_export_only_includes_rated_runs(): void
    {
        QueryRun::factory()->ratedUp()->createOne(['prompt' => 'rated prompt', 'comment' => 'top']);
        QueryRun::factory()->createOne(['prompt' => 'unrated prompt']);

        $response = $this->get(route('admin.feedback.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();

        self::assertStringContainsString('rated prompt', $csv);
        self::assertStringContainsString('top', $csv);
        self::assertStringNotContainsString('unrated prompt', $csv);
    }
}
