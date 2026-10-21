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
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;

#[Strict]
#[MaxTokens(1800)]
final class QueryProgramAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly Locale $locale = Locale::English,
    ) {
    }

    public function ask(string $question): StructuredAgentResponse
    {
        return $this->prompt($this->promptBuilder->userPrompt($question));
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
        return (string) config('vraagwagen.llm_model', 'gpt-5.4-nano');
    }

    public function temperature(): ?float
    {
        return $this->isReasoningModel() ? null : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (! $this->isReasoningModel()) {
            return [];
        }

        return ['reasoning' => ['effort' => 'low']];
    }

    private function isReasoningModel(): bool
    {
        return str_starts_with($this->model(), 'gpt-5');
    }
}
