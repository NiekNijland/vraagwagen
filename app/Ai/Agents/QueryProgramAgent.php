<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\Locale;
use App\Services\QueryPlan\PromptBuilder;
use App\Services\QueryPlan\QueryProgramSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

#[Strict]
#[MaxTokens(1800)]
#[Temperature(0.0)]
final class QueryProgramAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly Locale $locale = Locale::English,
    ) {
    }

    public function ask(string $question): StructuredAgentResponse
    {
        $response = $this->prompt($this->promptBuilder->userPrompt($question));

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('QueryProgramAgent did not return a structured response.');
        }

        return $response;
    }

    public function instructions(): string
    {
        return $this->promptBuilder->systemPrompt($this->locale);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return QueryProgramSchema::build($schema);
    }

    public function provider(): Lab
    {
        return Lab::OpenAI;
    }

    public function model(): string
    {
        return (string) config('vraagwagen.llm_model', 'gpt-4.1-mini');
    }
}
