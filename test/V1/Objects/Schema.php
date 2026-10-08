<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

/**
 * Reads the OParl 1.1 schema in `test/resources/schema/1.1`.
 */
final class Schema
{
    public const TYPES = [
        'AgendaItem',
        'Body',
        'Consultation',
        'File',
        'LegislativeTerm',
        'Location',
        'Meeting',
        'Membership',
        'Organization',
        'Paper',
        'Person',
        'System',
    ];

    /**
     * @return array<string, array<string, mixed>> the properties of the type, by name
     */
    public static function properties(string $type): array
    {
        $json = file_get_contents(__DIR__ . '/../../resources/schema/1.1/' . $type . '.json');
        $schema = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($schema) || !is_array($schema['properties'] ?? null)) {
            throw new \UnexpectedValueException('invalid schema ' . $type);
        }
        /** @var array<string, array<string, mixed>> $properties checked by the tests using them */
        $properties = $schema['properties'];
        return $properties;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function types(): iterable
    {
        foreach (self::TYPES as $type) {
            yield $type => [$type];
        }
    }
}
