<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait AssertsStrictJsonSchemaObjects
{
    /**
     * @param array<string, mixed> $node
     */
    protected static function assertStrictObjectNode(array $node, ?string $path = null): void
    {
        self::assertIsArray($node['properties'] ?? null, sprintf(
            'Schema node%s must declare properties.',
            $path === null ? '' : " [{$path}]",
        ));

        /** @var array<string, mixed> $properties */
        $properties = $node['properties'];
        $expectedRequired = array_keys($properties);

        self::assertSame(
            $expectedRequired,
            $node['required'] ?? null,
            sprintf(
                'Strict object schema%s must require every property key in declaration order.',
                $path === null ? '' : " [{$path}]",
            ),
        );

        self::assertFalse(
            $node['additionalProperties'] ?? null,
            sprintf(
                'Strict object schema%s must disable additionalProperties.',
                $path === null ? '' : " [{$path}]",
            ),
        );
    }
}
