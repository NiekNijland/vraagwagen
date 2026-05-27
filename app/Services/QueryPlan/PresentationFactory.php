<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use InvalidArgumentException;

final class PresentationFactory
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $queryIds
     */
    public function fromArray(array $data, array $queryIds): Presentation
    {
        $display = $this->parseDisplay($data['display'] ?? null);
        $explanation = (string) ($data['explanation'] ?? '');
        $resultRef = (string) ($data['resultRef'] ?? '');
        $derive = $this->parseDerive($data['derive'] ?? null, $queryIds);

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
        );
    }

    /**
     * @param list<string> $queryIds
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
     * @param list<string> $queryIds
     */
    private function requireQueryRef(mixed $raw, string $field, array $queryIds): string
    {
        $ref = (string) ($raw ?? '');
        if (! in_array($ref, $queryIds, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s "%s" is not one of the program queries [%s].',
                $field,
                $ref,
                implode(', ', $queryIds),
            ));
        }

        return $ref;
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
