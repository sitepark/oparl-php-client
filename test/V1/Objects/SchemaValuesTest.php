<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\Test\Fixture\RecordingLogger;
use SP\OparlClient\V1\Objects\OparlTypes;

/**
 * Reads an object of every type in which each property of the OParl 1.1 schema is set, and checks
 * that every value arrives at its getter, that references resolve to the class of the schema, and
 * that the object is written back unchanged. Complements {@see SchemaCoverageTest}, which only
 * compares the declared types.
 */
final class SchemaValuesTest extends TestCase
{
    #[DataProviderExternal(Schema::class, 'types')]
    public function testPassesEveryPropertyToItsGetter(string $type): void
    {
        $properties = Schema::properties($type);
        $json = [];
        foreach ($properties as $name => $property) {
            $json[$name] = self::sample($property);
        }
        $class = OparlTypes::classOf(OparlTypes::NAMESPACE . $type);
        $this->assertNotNull($class);
        $logger = new RecordingLogger();
        $mapper = new ObjectMapper(
            $logger,
            static fn(string $url, string $class): OparlObject => new $class(id: $url),
            static fn(string $url, string $class): OparlList => new OparlList([new $class(id: $url)]),
        );

        $object = $mapper->map($json, $class);

        $problems = [];
        foreach ($properties as $name => $property) {
            $value = $object->{($name === 'deleted' ? 'is' : 'get') . ucfirst($name)}();
            if ($value === null || $value === [] || $value === false) {
                $problems[] = $name . ': no value';
                continue;
            }
            $expected = self::referencedClass($property);
            if ($expected !== null) {
                $resolved = self::resolve($value);
                if (!$resolved instanceof $expected) {
                    $problems[] = $name . ': resolves to ' . get_debug_type($resolved) . ' instead of ' . $expected;
                }
            }
        }
        $this->assertSame([], $problems, $class);
        $this->assertSame([], $object->getAdditionalProperties());
        $this->assertSame([], $logger->records);
        $this->assertEquals($json, json_decode((string) json_encode($object), true), 'written back unchanged');
    }

    /**
     * A valid value for a property of the schema.
     *
     * @param array<string, mixed> $property
     */
    private static function sample(array $property): mixed
    {
        $items = $property['items'] ?? [];
        return match ($property['type'] ?? null) {
            'boolean' => true,
            'integer' => 1,
            'array' => [self::sample(is_array($items) ? $items : [])],
            'object' => isset($property['schema'])
                ? ['id' => 'https://oparl.example.org/embedded/1']
                : ['type' => 'Point', 'coordinates' => [7.0982, 50.7374]],
            default => match ($property['format'] ?? null) {
                'date-time' => '2024-01-02T03:04:05+01:00',
                'date' => '2024-01-02',
                'url' => 'https://oparl.example.org/1',
                default => 'value',
            },
        };
    }

    /**
     * The class a reference, a list of references or an external list resolves to, as declared by
     * the schema; `null` for other properties.
     *
     * @param array<string, mixed> $property
     * @return class-string|null
     */
    private static function referencedClass(array $property): ?string
    {
        $items = is_array($property['items'] ?? null) ? $property['items'] : [];
        $references = $property['references'] ?? $items['references'] ?? null;
        if ($references === 'externalList') {
            $references = preg_replace('/\.json$/', '', is_string($items['schema'] ?? null) ? $items['schema'] : '');
        }
        return is_string($references) ? OparlTypes::classOf(OparlTypes::NAMESPACE . $references) : null;
    }

    /**
     * Resolves a reference, the first of a list of references, or the first element of a list.
     */
    private static function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if (!$value instanceof OparlReference) {
            return $value;
        }
        $resolved = $value->get();
        return $resolved instanceof OparlList ? ($resolved->getData()[0] ?? null) : $resolved;
    }
}
