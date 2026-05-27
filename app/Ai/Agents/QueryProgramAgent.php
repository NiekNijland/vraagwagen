<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\Locale;
use App\Services\QueryPlan\Plan;
use App\Services\QueryPlan\PromptBuilder;
use App\Services\QueryPlan\QueryProgram;
use App\Services\QueryPlan\QueryProgramSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Translates a natural-language question about the RDW vehicle registry into a
 * whole {@see QueryProgram} (an ordered list of sub-queries plus a presentation)
 * in a single completion, rather than a single {@see Plan}. Dependent steps and
 * ratios are expressed in that one structured output, so cost and latency stay
 * at the single-shot level.
 *
 * The provider is pinned to OpenAI to match {@see QueryProgramSchema}; the model
 * is read from config so it can be tuned without a code change.
 */
final class QueryProgramAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly Locale $locale = Locale::English,
    ) {}

    /**
     * Ask the model a user question and return its structured program response.
     *
     * Narrows {@see Promptable::prompt()}'s widened return to the
     * {@see StructuredAgentResponse} that {@see HasStructuredOutput} guarantees,
     * failing loudly if a provider ever breaks that contract.
     */
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
        return (string) config('rdwai.llm_model', 'gpt-4.1-mini');
    }
}
