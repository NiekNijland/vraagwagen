<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class QueryProgramAgentOpenAiRequestTest extends TestCase
{
    public function test_openai_request_uses_a_provider_compatible_strict_schema(): void
    {
        Config::set('ai.providers.openai.key', 'test-key');
        Config::set('ai.providers.openai.url', 'https://api.openai.com/v1');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'model' => 'gpt-4.1-mini',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'queries' => [[
                                'id' => 'q1',
                                'dataset' => 'RegisteredVehicles',
                                'where' => [],
                                'select' => [],
                                'groupBy' => [],
                                'aggregates' => [
                                    ['fn' => 'count', 'field' => '*', 'alias' => 'n'],
                                ],
                                'orderBy' => [],
                                'limit' => null,
                                'display' => 'count',
                                'explanation' => 'Counts matching vehicles.',
                            ]],
                            'presentation' => [
                                'resultRef' => 'q1',
                                'display' => 'count',
                                'derive' => null,
                                'refusal' => null,
                                'explanation' => 'Counts matching vehicles.',
                                'followUps' => [],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 1,
                    'output_tokens' => 1,
                    'total_tokens' => 2,
                ],
            ], 200),
        ]);

        QueryProgramAgent::make(locale: Locale::Dutch)->ask('hoeveel yamaha mt-07 zijn er?');

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.openai.com/v1/responses') {
                return false;
            }

            $text = $request['text'];
            if (! is_array($text)) {
                return false;
            }

            $format = $text['format'] ?? null;
            if (! is_array($format) || ($format['type'] ?? null) !== 'json_schema' || ($format['strict'] ?? null) !== true) {
                return false;
            }

            $schema = $format['schema'] ?? null;
            if (! is_array($schema)) {
                return false;
            }

            $whereItem = $schema['properties']['queries']['items']['properties']['where']['items'] ?? null;

            return is_array($whereItem)
                && ($whereItem['required'] ?? null) === ['field', 'op', 'value', 'values']
                && ($whereItem['properties']['value']['type'] ?? null) === ['string', 'null']
                && ($whereItem['properties']['values']['type'] ?? null) === ['array', 'null']
                && ($whereItem['additionalProperties'] ?? null) === false;
        });
    }

    public function test_reasoning_model_request_carries_low_effort_and_no_temperature(): void
    {
        $this->fakeMinimalResponse('gpt-5.4-nano');
        Config::set('vraagwagen.llm_model', 'gpt-5.4-nano');

        QueryProgramAgent::make(locale: Locale::Dutch)->ask('hoeveel teslas zijn er?');

        Http::assertSent(fn (Request $request): bool => ($request['reasoning']['effort'] ?? null) === 'low'
            && ! isset($request['temperature']));
    }

    public function test_non_reasoning_model_request_carries_zero_temperature_and_no_reasoning(): void
    {
        $this->fakeMinimalResponse('gpt-4.1-mini');
        Config::set('vraagwagen.llm_model', 'gpt-4.1-mini');

        QueryProgramAgent::make(locale: Locale::Dutch)->ask('hoeveel teslas zijn er?');

        Http::assertSent(fn (Request $request): bool => isset($request['temperature'])
            && (float) $request['temperature'] === 0.0
            && ! isset($request['reasoning']));
    }

    private function fakeMinimalResponse(string $model): void
    {
        Config::set('ai.providers.openai.key', 'test-key');
        Config::set('ai.providers.openai.url', 'https://api.openai.com/v1');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'model' => $model,
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => '{}']],
                ]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
            ], 200),
        ]);
    }
}
