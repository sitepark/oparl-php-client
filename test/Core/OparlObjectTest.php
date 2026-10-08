<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\Test\Fixture\TestObject;

#[CoversClass(OparlObject::class)]
final class OparlObjectTest extends TestCase
{
    private static function readAndWrite(string $json): string
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $object = (new ObjectMapper())->map($data, TestObject::class);
        return (string) json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function testWritesPropertiesInFormatOfSpecificationWithoutNullValues(): void
    {
        /** @var OparlReference<TestObject> $ref */
        $ref = new OparlReference('https://oparl.example.org/ref');
        $object = new TestObject(
            name: 'Rat',
            start: new DateTimeImmutable('2024-01-21T18:00:00.5+01:00'),
            day: new DateTimeImmutable('2024-01-21T00:00:00+01:00'),
            ref: $ref,
            child: new TestObject(count: 2),
            additionalProperties: ['Hersteller:fax' => '0123'],
        );

        $this->assertSame(
            '{"name":"Rat","start":"2024-01-21T18:00:00+01:00","day":"2024-01-21",'
            . '"ref":"https://oparl.example.org/ref","child":{"count":2},"Hersteller:fax":"0123"}',
            json_encode($object, JSON_UNESCAPED_SLASHES),
        );
    }

    public function testReturnsAdditionalProperties(): void
    {
        $object = new TestObject(additionalProperties: ['Hersteller:fax' => '0123', 'Hersteller:mobil' => null]);

        $this->assertSame(['Hersteller:fax' => '0123', 'Hersteller:mobil' => null], $object->getAdditionalProperties());
        $this->assertSame('0123', $object->getAdditionalProperty('Hersteller:fax'));
        $this->assertTrue($object->hasAdditionalProperty('Hersteller:fax'));
        $this->assertNull($object->getAdditionalProperty('Hersteller:mobil'));
        $this->assertTrue($object->hasAdditionalProperty('Hersteller:mobil'));
        $this->assertNull($object->getAdditionalProperty('Hersteller:phone'));
        $this->assertFalse($object->hasAdditionalProperty('Hersteller:phone'));
    }

    public function testWritesEmptyObjectAsJsonObject(): void
    {
        $this->assertSame('{}', json_encode(new TestObject()));
    }

    public function testWritesReadObjectBackUnchanged(): void
    {
        $json = '{"name":"Rat","tags":["a"],"refs":["https://oparl.example.org/r"],'
            . '"children":[{"name":"K"}],"Hersteller:office":{"room":"2.13","floor":2}}';

        $this->assertSame($json, self::readAndWrite($json));
    }

    public function testWritesOriginalValueBackInsteadOfInvalidOne(): void
    {
        $this->assertSame(
            '{"name":"Rat","day":"0000-00-00"}',
            self::readAndWrite('{"name":"Rat","day":"0000-00-00"}'),
        );
    }

    public function testWritesWholeOriginalListBackIfElementsWereLeftOut(): void
    {
        $this->assertSame(
            '{"children":["https://oparl.example.org/c/1",{"name":"K2"}]}',
            self::readAndWrite('{"children":["https://oparl.example.org/c/1",{"name":"K2"}]}'),
        );
    }
}
