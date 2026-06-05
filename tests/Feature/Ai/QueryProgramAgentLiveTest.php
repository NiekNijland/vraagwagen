<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\QueryProgramFactory;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class QueryProgramAgentLiveTest extends TestCase
{
    public function test_live_openai_query_program_agent_returns_a_valid_program(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        Config::set('vraagwagen.llm_model', env('VRAAGWAGEN_LIVE_AI_MODEL', 'gpt-4.1-mini'));

        $response = QueryProgramAgent::make(locale: Locale::Dutch)
            ->ask('Hoeveel Yamaha MT-07 motorfietsen zijn er geregistreerd?');

        self::assertIsArray($response->structured);
        self::assertNotSame([], $response->structured);

        $program = $this->app->make(QueryProgramFactory::class)->fromArray($response->structured);

        self::assertNotSame([], $program->queries);
        self::assertSame('q1', $program->queries[0]->id);
    }

    private function skipUnlessLiveAiIsEnabled(): void
    {
        if (! filter_var(env('RUN_LIVE_AI_TESTS', false), FILTER_VALIDATE_BOOL)) {
            self::markTestSkipped('Set RUN_LIVE_AI_TESTS=true to run live AI smoke tests.');
        }

        if (! is_string(env('OPENAI_API_KEY')) || env('OPENAI_API_KEY') === '') {
            self::markTestSkipped('OPENAI_API_KEY is required for live AI smoke tests.');
        }
    }
}
