<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Internal;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\Internal\PropertyReader;
use SP\OparlClient\Test\Fixture\RecordingLogger;
use SP\OparlClient\Test\Fixture\TestObject;

#[CoversClass(PropertyReader::class)]
#[CoversClass(ObjectMapper::class)]
final class PropertyReaderTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    private function read(string $json, ?ObjectMapper $mapper = null): TestObject
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        return ($mapper ?? new ObjectMapper($this->logger))->map($data, TestObject::class);
    }

    public function testReadsValidValues(): void
    {
        $object = $this->read(<<<'JSON'
            {
              "name": "Rat", "count": 3, "active": true, "url": "https://oparl.example.org/a",
              "tags": ["a", "b"], "links": ["https://oparl.example.org/l"],
              "start": "2024-01-21T18:00:00+01:00", "day": "2024-01-21",
              "geo": {"type": "Feature", "geometry": null},
              "ref": "https://oparl.example.org/ref", "refs": ["https://oparl.example.org/r1"],
              "child": {"name": "Kind"}, "children": [{"name": "K1"}, {"name": "K2"}]
            }
            JSON);

        $this->assertSame('Rat', $object->name);
        $this->assertSame(3, $object->count);
        $this->assertTrue($object->active);
        $this->assertSame('https://oparl.example.org/a', $object->url);
        $this->assertSame(['a', 'b'], $object->tags);
        $this->assertSame(['https://oparl.example.org/l'], $object->links);
        $this->assertSame('2024-01-21T18:00:00+01:00', $object->start?->format(DATE_ATOM));
        $this->assertSame('2024-01-21T00:00:00+01:00', $object->day?->format(DATE_ATOM));
        $this->assertSame(['type' => 'Feature', 'geometry' => null], $object->geo);
        $this->assertSame('https://oparl.example.org/ref', $object->ref?->getUri());
        $this->assertSame('https://oparl.example.org/r1', ($object->refs[0] ?? null)?->getUri());
        $this->assertSame('Kind', $object->child?->name);
        $this->assertSame(['K1', 'K2'], array_map(static fn(TestObject $c): ?string => $c->name, $object->children ?? []));
        $this->assertSame([], $object->getAdditionalProperties());
        $this->assertSame([], $this->logger->records);
    }

    public function testReadsMissingAndNullValuesAsNullWithoutWarning(): void
    {
        $object = $this->read('{"name": null, "tags": null, "child": null, "ref": null}');

        $this->assertNull($object->name);
        $this->assertNull($object->tags);
        $this->assertNull($object->child);
        $this->assertNull($object->ref);
        $this->assertNull($object->count);
        $this->assertSame([], $object->getAdditionalProperties());
        $this->assertSame([], $this->logger->records);
    }

    public function testKeepsUnmappedPropertiesInOrder(): void
    {
        $object = $this->read(
            '{"Hersteller:fax": "0123", "name": "Rat", "Hersteller:office": {"room": "2.13", "floor": 2}, "7": "x"}',
        );

        $this->assertSame(
            ['Hersteller:fax' => '0123', 'Hersteller:office' => ['room' => '2.13', 'floor' => 2], '7' => 'x'],
            $object->getAdditionalProperties(),
        );
        $this->assertSame('0123', $object->getAdditionalProperty('Hersteller:fax'));
        $this->assertNull($object->getAdditionalProperty('name'));
        $this->assertFalse($object->hasAdditionalProperty('name'));
    }

    public function testKeepsAdditionalPropertyWithValueNull(): void
    {
        $object = $this->read('{"Hersteller:fax": null}');

        $this->assertTrue($object->hasAdditionalProperty('Hersteller:fax'));
        $this->assertNull($object->getAdditionalProperty('Hersteller:fax'));
        $this->assertFalse($object->hasAdditionalProperty('Hersteller:phone'));
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function invalidSingleValues(): iterable
    {
        yield 'string as object' => ['name', '{"name": {"de": "Rat"}}', ['de' => 'Rat']];
        yield 'text as integer' => ['count', '{"count": "drei"}', 'drei'];
        yield 'fraction as integer' => ['count', '{"count": 3.5}', 3.5];
        yield 'integer out of range' => ['count', '{"count": 1e20}', 1e20];
        yield 'boolean as integer' => ['count', '{"count": true}', true];
        yield 'text as boolean' => ['active', '{"active": "ja"}', 'ja'];
        yield 'number as URL' => ['url', '{"url": 42}', 42];
        yield 'unrepairable URL' => ['url', '{"url": "http://[broken"}', 'http://[broken'];
        yield 'invalid date-time' => ['start', '{"start": "unbekannt"}', 'unbekannt'];
        yield 'number as date-time' => ['start', '{"start": 20240121}', 20240121];
        yield 'zero date' => ['day', '{"day": "0000-00-00"}', '0000-00-00'];
        yield 'text as JSON' => ['geo', '{"geo": "POINT(7 51)"}', 'POINT(7 51)'];
        yield 'number as reference' => ['ref', '{"ref": 42}', 42];
        yield 'object without id as reference' => ['ref', '{"ref": {"name": "x"}}', ['name' => 'x']];
        yield 'list as reference' => ['ref', '{"ref": ["https://oparl.example.org/r"]}', ['https://oparl.example.org/r']];
        yield 'URL as embedded object' => ['child', '{"child": "https://oparl.example.org/c"}', 'https://oparl.example.org/c'];
        yield 'list as embedded object' => ['child', '{"child": [{"name": "x"}]}', [['name' => 'x']]];
    }

    #[DataProvider('invalidSingleValues')]
    public function testReadsInvalidSingleValueAsNullAndKeepsIt(string $property, string $json, mixed $original): void
    {
        $object = $this->read($json);

        $this->assertNull($object->{$property});
        $this->assertSame([$property => $original], $object->getAdditionalProperties());
        $this->assertCount(1, $this->logger->messages('warning'));
        $this->assertStringContainsString(
            'Ignoring invalid value of "' . $property . '" in TestObject, kept as additional property',
            $this->logger->messages('warning')[0],
        );
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function coercedValues(): iterable
    {
        yield 'number as string' => ['name', '{"name": 42}', '42'];
        yield 'boolean as string' => ['name', '{"name": false}', 'false'];
        yield 'integral float as integer' => ['count', '{"count": 3.0}', 3];
        yield 'numeric string as integer' => ['count', '{"count": "-3"}', -3];
        yield 'string as boolean' => ['active', '{"active": "false"}', false];
        yield 'number as boolean' => ['active', '{"active": 1}', true];
        yield 'embedded object as reference' => ['ref', '{"ref": {"id": "https://oparl.example.org/r", "name": "x"}}', 'https://oparl.example.org/r'];
        yield 'repaired URL' => ['url', '{"url": "https://oparl.example.org/a b"}', 'https://oparl.example.org/a%20b'];
        yield 'empty object as embedded object' => ['geo', '{"geo": {}}', []];
    }

    #[DataProvider('coercedValues')]
    public function testCoercesCompatibleValues(string $property, string $json, mixed $expected): void
    {
        $object = $this->read($json);

        $value = $object->{$property};
        $this->assertSame($expected, $value instanceof \SP\OparlClient\Core\OparlReference ? $value->getUri() : $value);
        $this->assertSame([], $object->getAdditionalProperties());
        $this->assertSame([], $this->logger->records);
    }

    public function testReadsSingleValueAsList(): void
    {
        $object = $this->read(
            '{"tags": "a", "refs": "https://oparl.example.org/r", "children": {"name": "K"}}',
        );

        $this->assertSame(['a'], $object->tags);
        $this->assertSame('https://oparl.example.org/r', ($object->refs[0] ?? null)?->getUri());
        $this->assertSame('K', ($object->children[0] ?? null)?->name);
        $this->assertSame([], $object->getAdditionalProperties());
    }

    public function testKeepsEmptyList(): void
    {
        $object = $this->read('{"tags": [], "children": []}');

        $this->assertSame([], $object->tags);
        $this->assertSame([], $object->children);
        $this->assertSame([], $object->getAdditionalProperties());
    }

    public function testLeavesOutInvalidElementsOfList(): void
    {
        $object = $this->read(
            '{"children": ["https://oparl.example.org/c/1", {"name": "K2"}],'
            . ' "refs": [42, {"name": "x"}, "http://[broken", null, "https://oparl.example.org/r/1"]}',
        );

        $this->assertSame(['K2'], array_map(static fn(TestObject $c): ?string => $c->name, $object->children ?? []));
        $this->assertSame(['https://oparl.example.org/r/1'], array_map(
            static fn($r): string => $r->getUri(),
            $object->refs ?? [],
        ));
        $this->assertSame(
            [
                'children' => ['https://oparl.example.org/c/1', ['name' => 'K2']],
                'refs' => [42, ['name' => 'x'], 'http://[broken', null, 'https://oparl.example.org/r/1'],
            ],
            $object->getAdditionalProperties(),
        );
        $this->assertCount(2, $this->logger->messages('warning'));
    }

    public function testReadsListAsNullIfNoElementIsValid(): void
    {
        $object = $this->read('{"children": ["https://oparl.example.org/c/1"]}');

        $this->assertNull($object->children);
        $this->assertSame(['children' => ['https://oparl.example.org/c/1']], $object->getAdditionalProperties());
    }

    public function testToleratesInvalidValuesInsideEmbeddedObjects(): void
    {
        $object = $this->read('{"name": "Rat", "child": {"name": "Kind", "count": "viele"}}');

        $this->assertSame('Rat', $object->name);
        $this->assertSame('Kind', $object->child?->name);
        $this->assertNull($object->child->count);
        $this->assertSame(['count' => 'viele'], $object->child->getAdditionalProperties());
        $this->assertSame([], $object->getAdditionalProperties());
    }

    public function testResolvesReferencesThroughLoaderWithTheirClass(): void
    {
        $requested = [];
        $mapper = new ObjectMapper(
            $this->logger,
            static function (string $url, string $class) use (&$requested): OparlObject {
                $requested[] = [$url, $class];
                return new TestObject(name: 'geladen');
            },
        );

        $object = $this->read('{"ref": "https://oparl.example.org/ref", "refs": ["https://oparl.example.org/r1"]}', $mapper);

        $this->assertSame([], $requested, 'no request before get()');
        $this->assertSame('geladen', $object->ref?->get()->name);
        $this->assertSame('geladen', ($object->refs[0] ?? null)?->get()->name);
        $this->assertSame(
            [['https://oparl.example.org/ref', TestObject::class], ['https://oparl.example.org/r1', TestObject::class]],
            $requested,
        );
    }

    public function testCreatesReferencesWithoutLoaderIfMappedWithoutClient(): void
    {
        $object = $this->read('{"ref": "https://oparl.example.org/ref"}');

        $this->expectException(LogicException::class);
        $object->ref?->get();
    }

    public function testReadsReferenceToList(): void
    {
        $mapper = new ObjectMapper(
            $this->logger,
            listLoader: static fn(string $url, string $class): OparlList => new OparlList(
                [new TestObject(name: $class . ' ' . $url)],
            ),
        );
        $reader = new PropertyReader(['papers' => 'https://oparl.example.org/papers'], TestObject::class, $mapper);

        $reference = $reader->listReference('papers', TestObject::class);

        $this->assertSame(
            TestObject::class . ' https://oparl.example.org/papers',
            ($reference?->get()->getData()[0] ?? null)?->name,
        );
        $this->assertSame([], $reader->additionalProperties());
    }

    public function testKeepsInvalidReferenceToList(): void
    {
        $reader = new PropertyReader(['papers' => ['x']], TestObject::class, new ObjectMapper($this->logger));

        $this->assertNull($reader->listReference('papers', TestObject::class));
        $this->assertSame(['papers' => ['x']], $reader->additionalProperties());
        $this->assertCount(1, $this->logger->messages('warning'));
    }

    public function testCreatesReferenceToListWithoutLoaderIfMappedWithoutClient(): void
    {
        $reader = new PropertyReader(['papers' => 'https://oparl.example.org/papers'], TestObject::class, new ObjectMapper());
        $reference = $reader->listReference('papers', TestObject::class);

        $this->expectException(LogicException::class);
        $reference?->get();
    }

    public function testReadsObjectWithOwnReadFunction(): void
    {
        $reader = new PropertyReader(
            ['child' => ['name' => 'Kind'], 'other' => 'https://oparl.example.org/x'],
            TestObject::class,
            new ObjectMapper($this->logger),
        );
        $read = static fn(PropertyReader $r): TestObject => new TestObject(name: 'eigen ' . $r->string('name'));

        $this->assertSame('eigen Kind', $reader->objectWith('child', TestObject::class, $read)?->name);
        $this->assertNull($reader->objectWith('other', TestObject::class, $read));
        $this->assertSame(['other' => 'https://oparl.example.org/x'], $reader->additionalProperties());
    }

    public function testProvidesLoggerOfMapper(): void
    {
        $reader = new PropertyReader([], TestObject::class, new ObjectMapper($this->logger));

        $this->assertSame($this->logger, $reader->logger());
    }

    public function testShortensAndSanitizesLoggedValue(): void
    {
        $this->read((string) json_encode(['name' => ['x' => "a\nb" . str_repeat('ü', 300)]]));

        $message = $this->logger->messages('warning')[0];
        $this->assertStringNotContainsString("\n", $message);
        $this->assertStringContainsString('got {"x":"a\nb', $message);
        $this->assertStringEndsWith('ü…', $message);
        $value = substr($message, (int) strpos($message, 'got ') + 4);
        $this->assertSame(201, preg_match_all('/./su', $value), 'value shortened to 200 characters');
    }
}
