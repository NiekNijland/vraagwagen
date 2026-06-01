<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use InvalidArgumentException;

final class PresentationFactory
{
    private const int MAX_SUGGESTIONS = 3;

    private const int MAX_FOLLOW_UPS = 3;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $queryIds
     */
    public function fromArray(array $data, array $queryIds): Presentation
    {
        $display = $this->parseDisplay($data['display'] ?? null);
        $explanation = (string) ($data['explanation'] ?? '');
        $resultRef = (string) ($data['resultRef'] ?? '');
        $derive = $this->parseDerive($data['derive'] ?? null, $queryIds);

        // A refusal program carries no real result to validate against the query ids: the dummy
        // query exists only to satisfy the "at least one query" rule and never hits RDW. The
        // refusal is parsed only here so a stray (and unused) `refusal` on an answerable plan
        // can't fail an otherwise-valid query with an "invalid reason" error.
        if ($display === DisplayHint::Unsupported) {
            return new Presentation(
                resultRef: $resultRef,
                display: $display,
                derive: null,
                explanation: $explanation,
                refusal: $this->parseRefusal($data['refusal'] ?? null) ?? new Refusal(RefusalReason::OutOfScope),
            );
        }

        if ($derive !== null) {
            if ($resultRef !== Presentation::DERIVED_REF) {
                throw new InvalidArgumentException(sprintf(
                    'A derived presentation must set resultRef to "%s"; got "%s".',
                    Presentation::DERIVED_REF,
                    $resultRef,
                ));
            }
        } elseif (! in_array($resultRef, $queryIds, true)) {
            throw new InvalidArgumentException(sprintf(
                'Presentation resultRef "%s" is not one of the program queries [%s].',
                $resultRef,
                implode(', ', $queryIds),
            ));
        }

        return new Presentation(
            resultRef: $resultRef,
            display: $display,
            derive: $derive,
            explanation: $explanation,
            followUps: $this->parseFollowUps($data['followUps'] ?? null),
        );
    }

    /**
     * Trimmed, de-duplicated and capped next-step questions. String-coerced defensively against a
     * non-compliant payload, mirroring how refusal suggestions are sanitised.
     *
     * @return list<string>
     */
    private function parseFollowUps(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $followUps = [];
        foreach ($raw as $followUp) {
            $text = trim((string) $followUp);
            if ($text === '' || in_array($text, $followUps, true)) {
                continue;
            }

            $followUps[] = $text;
            if (count($followUps) === self::MAX_FOLLOW_UPS) {
                break;
            }
        }

        return $followUps;
    }

    /**
     * Parsed only for an `unsupported` display, so a stray `refusal` the model may leave on an
     * answerable plan is ignored rather than able to fail it. Suggestions are capped and
     * string-coerced defensively against a non-compliant payload.
     */
    private function parseRefusal(mixed $raw): ?Refusal
    {
        if (! is_array($raw) || ($raw['reason'] ?? null) === null || $raw['reason'] === '') {
            return null;
        }

        $reason = RefusalReason::tryFrom((string) $raw['reason']);
        if ($reason === null) {
            throw new InvalidArgumentException(sprintf('Invalid refusal reason "%s".', (string) $raw['reason']));
        }

        $rawSuggestions = is_array($raw['suggestions'] ?? null) ? $raw['suggestions'] : [];
        $suggestions = [];
        foreach ($rawSuggestions as $suggestion) {
            $text = trim((string) $suggestion);
            if ($text !== '') {
                $suggestions[] = $text;
            }
            if (count($suggestions) === self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return new Refusal($reason, $suggestions);
    }

    /**
     * @param  list<string>  $queryIds
     */
    private function parseDerive(mixed $raw, array $queryIds): ?Derive
    {
        if (! is_array($raw) || ($raw['op'] ?? null) === null || $raw['op'] === '') {
            return null;
        }

        $op = DeriveOp::tryFrom((string) $raw['op']);
        if ($op === null) {
            throw new InvalidArgumentException(sprintf('Invalid derive op "%s".', (string) $raw['op']));
        }

        if ($op->isBinaryScalar()) {
            $numerator = $this->requireQueryRef($raw['numerator'] ?? null, 'derive.numerator', $queryIds);
            $denominator = $this->requireQueryRef($raw['denominator'] ?? null, 'derive.denominator', $queryIds);

            return new Derive(op: $op, numerator: $numerator, denominator: $denominator);
        }

        $source = $this->requireQueryRef($raw['source'] ?? null, 'derive.source', $queryIds);
        $selectorColumn = (string) ($raw['selectorColumn'] ?? '');
        $selectorValue = (string) ($raw['selectorValue'] ?? '');

        if ($selectorColumn === '' || $selectorValue === '') {
            throw new InvalidArgumentException('A groupShare derive requires selectorColumn and selectorValue.');
        }

        return new Derive(
            op: $op,
            source: $source,
            selectorColumn: $selectorColumn,
            selectorValue: $selectorValue,
        );
    }

    /**
     * @param  list<string>  $queryIds
     */
    private function requireQueryRef(mixed $raw, string $field, array $queryIds): string
    {
        $ref = (string) ($raw ?? '');

        // The model often references a query's column ("q1.n", "q2.electric_count"); the derive only
        // needs the query id, so strip any trailing ".field" before validating.
        $id = str_contains($ref, '.') ? substr($ref, 0, (int) strpos($ref, '.')) : $ref;

        if (! in_array($id, $queryIds, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s "%s" is not one of the program queries [%s].',
                $field,
                $ref,
                implode(', ', $queryIds),
            ));
        }

        return $id;
    }

    private function parseDisplay(mixed $raw): DisplayHint
    {
        $display = DisplayHint::tryFrom((string) ($raw ?? 'table'));
        if ($display === null) {
            throw new InvalidArgumentException(sprintf('Invalid display "%s".', (string) $raw));
        }

        return $display;
    }
}
