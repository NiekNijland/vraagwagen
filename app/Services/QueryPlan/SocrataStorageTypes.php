<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use Composer\InstalledVersions;
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Schema\CastType;
use NiekNijland\RDW\Schema\SchemaRegistry;
use RuntimeException;

/**
 * Reads Socrata's authoritative `dataTypeName` from the metadata JSON bundled with the RDW
 * package. Socrata stores plenty of numeric columns as `text`, which forces lexicographic
 * comparisons unless the SoQL casts the column with `::number`. The cast is only valid when the
 * underlying storage really is text, so we drive the decision from the source-of-truth metadata
 * rather than from a hand-maintained allowlist that silently drifts when RDW adds fields.
 */
final class SocrataStorageTypes
{
    private const string PACKAGE = 'nieknijland/rdw-opendata-php';

    /** @var array<string, array<string, string>> dataset id → rdwKey → dataTypeName */
    private array $byDataset = [];

    public function __construct(private readonly SchemaRegistry $schemas) {}

    /**
     * True when a SoQL comparison/order/aggregate on this field needs a `::number` cast because
     * Socrata stores it as text but our schema treats it numerically.
     */
    public function needsNumericWrap(TargetDataset $dataset, string $rdwKey): bool
    {
        $descriptor = $this->schemas->get($dataset->datasetId())->byRdwKey[$rdwKey] ?? null;
        if ($descriptor === null) {
            return false;
        }

        if ($descriptor->cast !== CastType::Decimal && $descriptor->cast !== CastType::Integer) {
            return false;
        }

        return $this->storageTypeFor($dataset->datasetId(), $rdwKey) === 'text';
    }

    /**
     * Every rdwKey on the dataset whose Socrata storage is `text` AND whose package cast is
     * numeric. Exposed for drift-detection tests.
     *
     * @return list<string>
     */
    public function textStoredNumericKeys(TargetDataset $dataset): array
    {
        $schema = $this->schemas->get($dataset->datasetId());
        $out = [];
        foreach ($schema->byRdwKey as $rdwKey => $descriptor) {
            if ($this->needsNumericWrap($dataset, $rdwKey)) {
                $out[] = $rdwKey;
            }
        }

        return $out;
    }

    private function storageTypeFor(DatasetId $datasetId, string $rdwKey): ?string
    {
        $map = $this->byDataset[$datasetId->value] ??= $this->loadMetadata($datasetId);

        return $map[$rdwKey] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function loadMetadata(DatasetId $datasetId): array
    {
        $path = InstalledVersions::getInstallPath(self::PACKAGE);
        if ($path === null) {
            throw new RuntimeException(sprintf('Package "%s" is not installed.', self::PACKAGE));
        }

        $file = $path.'/metadata/'.$datasetId->value.'.json';
        if (! is_file($file)) {
            throw new RuntimeException(sprintf('Socrata metadata missing for dataset "%s" at %s.', $datasetId->value, $file));
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Failed to read Socrata metadata at %s.', $file));
        }

        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! isset($decoded['columns']) || ! is_array($decoded['columns'])) {
            throw new RuntimeException(sprintf('Unexpected Socrata metadata shape at %s.', $file));
        }

        $out = [];
        foreach ($decoded['columns'] as $column) {
            if (! is_array($column)) {
                continue;
            }
            $fieldName = $column['fieldName'] ?? null;
            $dataTypeName = $column['dataTypeName'] ?? null;
            if (is_string($fieldName) && is_string($dataTypeName)) {
                $out[$fieldName] = $dataTypeName;
            }
        }

        return $out;
    }
}
