<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use InvalidArgumentException;

/**
 * Builds a typed {@see QueryProgram} from the loose array the model emits.
 *
 * Each query becomes a {@see Plan} via {@see PlanFactory} (so all of its repair
 * and validation still applies); on top of that this factory enforces the
 * program-level invariants: unique well-formed ids, a query cap, and — the new
 * piece — that every dependent-step {@see StepReference} points *backward* to an
 * already-defined query and names a real field. The {@see Presentation} is
 * validated against the same ids. Every failure is an
 * {@see InvalidArgumentException} (mapped to 422), matching {@see PlanFactory}.
 */
final class QueryProgramFactory
{
    private const int MAX_QUERIES = 4;

    private const string ID_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function __construct(
        private readonly PlanFactory $planFactory,
        private readonly PresentationFactory $presentationFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromArray(array $data): QueryProgram
    {
        $rawQueries = $this->arrayOrEmpty($data, 'queries');
        if ($rawQueries === []) {
            throw new InvalidArgumentException('A query program must contain at least one query.');
        }
        if (count($rawQueries) > self::MAX_QUERIES) {
            throw new InvalidArgumentException(sprintf(
                'A query program may contain at most %d queries; got %d.',
                self::MAX_QUERIES,
                count($rawQueries),
            ));
        }

        /** @var list<ProgramQuery> $queries */
        $queries = [];
        /** @var list<string> $seenIds ids defined *before* the current query */
        $seenIds = [];

        foreach ($rawQueries as $rawQuery) {
            if (! is_array($rawQuery)) {
                throw new InvalidArgumentException('Each program query must be an object.');
            }

            $id = $this->parseId($rawQuery['id'] ?? null, $seenIds);
            $plan = $this->planFactory->fromArray($rawQuery);

            $this->assertReferencesPointBackward($plan, $seenIds, $id);

            $queries[] = new ProgramQuery($id, $plan);
            $seenIds[] = $id;
        }

        $presentation = $this->presentationFactory->fromArray(
            is_array($data['presentation'] ?? null) ? $data['presentation'] : [],
            $seenIds,
        );

        return new QueryProgram($queries, $presentation);
    }

    /**
     * @param  list<string>  $seenIds
     */
    private function parseId(mixed $raw, array $seenIds): string
    {
        $id = (string) ($raw ?? '');
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid query id "%s".', $id));
        }
        if (in_array($id, $seenIds, true)) {
            throw new InvalidArgumentException(sprintf('Duplicate query id "%s".', $id));
        }

        return $id;
    }

    /**
     * @param  list<string>  $earlierIds  ids defined before this query
     */
    private function assertReferencesPointBackward(Plan $plan, array $earlierIds, string $selfId): void
    {
        foreach ($plan->where as $clause) {
            $reference = StepReference::tryParse($clause->value);
            if ($reference === null) {
                continue;
            }

            if ($reference->queryId === $selfId) {
                throw new InvalidArgumentException(sprintf('Query "%s" references itself.', $selfId));
            }
            if (! in_array($reference->queryId, $earlierIds, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Query "%s" references "%s", which is not an earlier query.',
                    $selfId,
                    $reference->queryId,
                ));
            }
            if (RegisteredVehicleFieldLookup::tryGet($reference->field) === null) {
                throw new InvalidArgumentException(sprintf(
                    'Reference "%s" names an unknown field "%s".',
                    $reference->token(),
                    $reference->field,
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function arrayOrEmpty(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
