<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use SP\OparlClient\V1\Objects\OparlTypes;

/**
 * Checks the object classes against the OParl 1.1 schema in `test/resources/schema/1.1`: every
 * property of the schema must have a getter with a matching type, including the generic type of
 * lists and references.
 */
#[CoversNothing]
final class SchemaCoverageTest extends TestCase
{
    public function testCoversAllObjectTypes(): void
    {
        foreach (Schema::TYPES as $type) {
            $this->assertNotNull(OparlTypes::classOf(OparlTypes::NAMESPACE . $type), 'no class for type ' . $type);
        }
    }

    #[DataProviderExternal(Schema::class, 'types')]
    public function testMapsEveryPropertyOfTheSchema(string $type): void
    {
        $class = OparlTypes::classOf(OparlTypes::NAMESPACE . $type);
        $this->assertNotNull($class);
        $problems = [];
        foreach (Schema::properties($type) as $name => $property) {
            $getter = ($name === 'deleted' ? 'is' : 'get') . ucfirst($name);
            if (!method_exists($class, $getter)) {
                $problems[] = $name . ': no getter ' . $getter . '()';
                continue;
            }
            [$expectedType, $expectedDoc] = self::expected($name, $property);
            $actualType = self::returnType(new ReflectionMethod($class, $getter));
            $actualDoc = self::returnDoc(new ReflectionMethod($class, $getter));
            if ($actualType !== $expectedType || $actualDoc !== $expectedDoc) {
                $problems[] = sprintf(
                    '%s: expected %s%s, got %s%s',
                    $name,
                    $expectedType,
                    $expectedDoc !== null ? ' (' . $expectedDoc . ')' : '',
                    $actualType,
                    $actualDoc !== null ? ' (' . $actualDoc . ')' : '',
                );
            }
        }
        $this->assertSame([], $problems, $class);
    }

    /**
     * Returns the expected native return type and the expected `@return` type, if any.
     *
     * @param array<string, mixed> $property
     * @return array{string, ?string}
     */
    private static function expected(string $name, array $property): array
    {
        if ($name === 'deleted') {
            return ['bool', null];
        }
        return match ($property['type'] ?? null) {
            'boolean' => ['?bool', null],
            'integer' => ['?int', null],
            'string' => self::expectedString($name, $property),
            'array' => ['?array', 'list<' . self::elementType($property) . '>|null'],
            'object' => isset($property['schema'])
                ? ['?' . self::classOfSchema($property['schema']), null]
                : ['?array', 'array<mixed>|null'],
            default => ['unknown ' . json_encode($property), null],
        };
    }

    /**
     * @param array<string, mixed> $property
     * @return array{string, ?string}
     */
    private static function expectedString(string $name, array $property): array
    {
        $references = $property['references'] ?? null;
        return match ($property['format'] ?? null) {
            'date', 'date-time' => ['?DateTimeImmutable', null],
            'url' => match (true) {
                $references === 'externalList' => [
                    '?SP\OparlClient\Core\OparlReference',
                    'OparlReference<OparlList<' . self::classOfSchema(self::items($property)['schema'] ?? null, true) . '>>|null',
                ],
                is_string($references) => [
                    '?SP\OparlClient\Core\OparlReference',
                    'OparlReference<Oparl' . $references . '>|null',
                ],
                // license is a URL only for Body and System; it is read as string for all types
                default => ['?string', null],
            },
            default => ['?string', null],
        } + [1 => null];
    }

    /**
     * The `@return` type of the elements of an array property.
     *
     * @param array<string, mixed> $property
     */
    private static function elementType(array $property): string
    {
        $items = self::items($property);
        if (($items['type'] ?? null) === 'object') {
            return self::classOfSchema($items['schema'] ?? null, true);
        }
        // the schema declares the referenced type of the elements on the items or on the array
        $references = $items['references'] ?? $property['references'] ?? null;
        return is_string($references) ? 'OparlReference<Oparl' . $references . '>' : 'string';
    }

    /**
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    private static function items(array $property): array
    {
        $items = [];
        foreach (is_array($property['items'] ?? null) ? $property['items'] : [] as $key => $value) {
            $items[(string) $key] = $value;
        }
        return $items;
    }

    private static function classOfSchema(mixed $schema, bool $short = false): string
    {
        $type = is_string($schema) ? preg_replace('/\.json$/', '', $schema) : null;
        $class = OparlTypes::classOf(OparlTypes::NAMESPACE . $type) ?? 'unknown schema ' . json_encode($schema);
        return $short ? substr($class, (int) strrpos($class, '\\') + 1) : $class;
    }

    private static function returnType(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();
        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }
        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }

    private static function returnDoc(ReflectionMethod $method): ?string
    {
        return preg_match('/@return\s+(\S+)/', (string) $method->getDocComment(), $match) === 1 ? $match[1] : null;
    }
}
