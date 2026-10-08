<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Test\Fixture\Json;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlConsultation;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlLegislativeTerm;
use SP\OparlClient\V1\Objects\OparlLocation;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlMembership;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlOrganization;
use SP\OparlClient\V1\Objects\OparlPaper;
use SP\OparlClient\V1\Objects\OparlPerson;
use SP\OparlClient\V1\Objects\OparlSystem;
use SP\OparlClient\V1\Objects\OparlTypes;

#[CoversClass(OparlObjectV1::class)]
#[CoversClass(OparlSystem::class)]
#[CoversClass(OparlBody::class)]
#[CoversClass(OparlLegislativeTerm::class)]
#[CoversClass(OparlOrganization::class)]
#[CoversClass(OparlPerson::class)]
#[CoversClass(OparlMembership::class)]
#[CoversClass(OparlMeeting::class)]
#[CoversClass(OparlAgendaItem::class)]
#[CoversClass(OparlPaper::class)]
#[CoversClass(OparlConsultation::class)]
#[CoversClass(OparlFile::class)]
#[CoversClass(OparlLocation::class)]
final class ObjectTypesTest extends TestCase
{
    /**
     * @return class-string<OparlObjectV1>
     */
    private static function classOf(string $type): string
    {
        $class = OparlTypes::classOf(OparlTypes::NAMESPACE . $type);
        self::assertNotNull($class);
        return $class;
    }

    #[DataProviderExternal(Schema::class, 'types')]
    public function testReadsCommonProperties(string $type): void
    {
        $object = Json::map(
            '{"id":"https://oparl.example.org/x/1","type":"https://schema.oparl.org/1.1/' . $type . '",'
            . '"keyword":["haushalt","finanzen"],"license":"Nutzung frei, Quelle: Stadt Beispiel",'
            . '"created":"2024-01-01T09:00:00+01:00","modified":"2024-01-02T09:00:00+01:00",'
            . '"web":"https://ris.example.org/x/1","deleted":true}',
            self::classOf($type),
        );

        $this->assertSame('https://oparl.example.org/x/1', $object->getId());
        $this->assertSame('https://schema.oparl.org/1.1/' . $type, $object->getType());
        $this->assertSame(['haushalt', 'finanzen'], $object->getKeyword());
        $this->assertSame('Nutzung frei, Quelle: Stadt Beispiel', $object->getLicense());
        $this->assertSame('2024-01-01T09:00:00+01:00', $object->getCreated()?->format(DATE_ATOM));
        $this->assertSame('2024-01-02T09:00:00+01:00', $object->getModified()?->format(DATE_ATOM));
        $this->assertSame('https://ris.example.org/x/1', $object->getWeb());
        $this->assertTrue($object->isDeleted());
        $this->assertSame([], $object->getAdditionalProperties());
    }

    #[DataProviderExternal(Schema::class, 'types')]
    public function testIsNotDeletedAndEmptyWithoutProperties(string $type): void
    {
        $object = Json::map('{}', self::classOf($type));

        $this->assertFalse($object->isDeleted());
        $this->assertNull($object->getId());
        $this->assertSame('{}', json_encode($object));
    }

    public function testWritesDeletedOnlyIfTrue(): void
    {
        $this->assertSame(
            '{"id":"https://oparl.example.org/paper/1","deleted":true}',
            json_encode(new OparlPaper(id: 'https://oparl.example.org/paper/1', deleted: true), JSON_UNESCAPED_SLASHES),
        );
        $this->assertSame('{"name":"A"}', json_encode(Json::map('{"name":"A","deleted":false}', OparlPaper::class)));
    }

    public function testReadsObjectOfUnknownTypeAsBaseClass(): void
    {
        $object = Json::map(
            '{"id":"https://oparl.example.org/x/1","type":"https://example.org/Hersteller/Fraktionskasse",'
            . '"name":"Kasse","created":"2024-01-01T09:00:00+01:00"}',
            OparlObjectV1::class,
        );

        $this->assertSame('https://oparl.example.org/x/1', $object->getId());
        $this->assertSame('https://example.org/Hersteller/Fraktionskasse', $object->getType());
        $this->assertSame('2024-01-01T09:00:00+01:00', $object->getCreated()?->format(DATE_ATOM));
        $this->assertSame(['name' => 'Kasse'], $object->getAdditionalProperties());
        $this->assertSame(
            [
                'id' => 'https://oparl.example.org/x/1',
                'type' => 'https://example.org/Hersteller/Fraktionskasse',
                'created' => '2024-01-01T09:00:00+01:00',
                'name' => 'Kasse',
            ],
            Json::written($object),
        );
    }

    public function testReadsPublicOfAgendaItem(): void
    {
        $this->assertTrue(Json::map('{"public":true}', OparlAgendaItem::class)->getPublic());
        $this->assertFalse(Json::map('{"public":false}', OparlAgendaItem::class)->getPublic());
        $this->assertNull(Json::map('{}', OparlAgendaItem::class)->getPublic());
        $this->assertSame('{"public":false}', json_encode(new OparlAgendaItem(public: false)));
    }

    public function testReadsAndWritesGeojsonUnchanged(): void
    {
        $json = '{"geojson":{"type":"Feature","geometry":{"type":"Point","coordinates":[7.0982,50.7374]},'
            . '"properties":{"name":"Rathaus"}}}';

        $location = Json::map($json, OparlLocation::class);

        $this->assertSame('Feature', $location->getGeojson()['type'] ?? null);
        $this->assertSame(
            ['type' => 'Point', 'coordinates' => [7.0982, 50.7374]],
            $location->getGeojson()['geometry'] ?? null,
        );
        $this->assertSame($json, json_encode($location));
    }

    public function testReadsFileSizeLargerThan32Bit(): void
    {
        $file = Json::map('{"size":3000000000}', OparlFile::class);

        $this->assertSame(3000000000, $file->getSize());
        $this->assertSame('{"size":3000000000}', json_encode($file));
    }

    public function testReadsDatesAsStartOfDayInGermanTime(): void
    {
        $term = Json::map('{"startDate":"2020-11-01","endDate":"2025-10-31T23:59:59+01:00"}', OparlLegislativeTerm::class);

        $this->assertSame('2020-11-01T00:00:00+01:00', $term->getStartDate()?->format(DATE_ATOM));
        $this->assertSame('2025-10-31T00:00:00+01:00', $term->getEndDate()?->format(DATE_ATOM));
        $this->assertSame('{"startDate":"2020-11-01","endDate":"2025-10-31"}', json_encode($term));
    }

    public function testCreatesObjectWithNamedArguments(): void
    {
        $meeting = new OparlMeeting(
            id: 'https://oparl.example.org/meeting/1',
            name: 'Ratssitzung',
            start: new DateTimeImmutable('2024-01-21T18:00:00+01:00'),
            location: new OparlLocation(room: 'Ratssaal'),
            keyword: ['haushalt'],
        );

        $this->assertSame(
            '{"id":"https://oparl.example.org/meeting/1","keyword":["haushalt"],"name":"Ratssitzung",'
            . '"start":"2024-01-21T18:00:00+01:00","location":{"room":"Ratssaal"}}',
            json_encode($meeting, JSON_UNESCAPED_SLASHES),
        );
    }

    public function testRoundTripsObjectWithReferences(): void
    {
        $original = '{"id":"https://oparl.example.org/body/1","type":"https://schema.oparl.org/1.1/Body",'
            . '"system":"https://oparl.example.org/","name":"Stadt Beispiel",'
            . '"equivalent":["https://www.wikidata.org/wiki/Q1"],'
            . '"organization":"https://oparl.example.org/body/1/organization",'
            . '"location":{"id":"https://oparl.example.org/location/1","bodies":["https://oparl.example.org/body/1"]}}';
        $body = Json::map($original, OparlBody::class);

        $copy = Json::map((string) json_encode($body), OparlBody::class);

        $this->assertSame($body->getId(), $copy->getId());
        $this->assertSame('https://oparl.example.org/', $copy->getSystem()?->getUri());
        $this->assertSame('https://oparl.example.org/body/1/organization', $copy->getOrganization()?->getUri());
        $this->assertSame(['https://www.wikidata.org/wiki/Q1'], $copy->getEquivalent());
        $this->assertSame('https://oparl.example.org/body/1', ($copy->getLocation()?->getBodies()[0] ?? null)?->getUri());
        $this->assertEquals(Json::decodeObject($original), Json::written($copy));
    }
}
