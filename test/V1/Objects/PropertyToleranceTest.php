<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\Test\Fixture\Json;
use SP\OparlClient\Test\Fixture\RecordingLogger;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlConsultation;
use SP\OparlClient\V1\Objects\OparlLegislativeTerm;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlMembership;
use SP\OparlClient\V1\Objects\OparlPerson;

/**
 * Servers that send values which do not match the type required by the specification, with the
 * OParl object types.
 */
final class PropertyToleranceTest extends TestCase
{
    private const MEMBERSHIP_URL = 'https://oparl.example.org/membership/1';

    /**
     * @param list<OparlReference<object>>|null $references
     * @return list<string>
     */
    private static function uris(?array $references): array
    {
        return array_map(static fn(OparlReference $r): string => $r->getUri(), $references ?? []);
    }

    public function testLeavesOutInvalidElementsOfList(): void
    {
        // München Transparent sends memberships as URLs instead of embedded objects
        $membership = '["' . self::MEMBERSHIP_URL . '",{"id":"https://oparl.example.org/membership/2"}]';
        $person = Json::map('{"membership":' . $membership . '}', OparlPerson::class);

        $this->assertSame(
            ['https://oparl.example.org/membership/2'],
            array_map(static fn(OparlMembership $m): ?string => $m->getId(), $person->getMembership() ?? []),
        );
        $this->assertSame(Json::decodeObject($membership), $person->getAdditionalProperty('membership'));
    }

    public function testLeavesListNullIfNoElementIsValid(): void
    {
        $person = Json::map('{"membership":["' . self::MEMBERSHIP_URL . '"]}', OparlPerson::class);

        $this->assertNull($person->getMembership());
        $this->assertSame([self::MEMBERSHIP_URL], $person->getAdditionalProperty('membership'));
    }

    public function testLeavesSingleObjectNullIfInvalid(): void
    {
        $meeting = Json::map('{"location":"https://oparl.example.org/location/1"}', OparlMeeting::class);

        $this->assertNull($meeting->getLocation());
        $this->assertSame('https://oparl.example.org/location/1', $meeting->getAdditionalProperty('location'));
    }

    public function testKeepsValidAndNullValuesUntouched(): void
    {
        $person = Json::map('{"membership":[{"id":"' . self::MEMBERSHIP_URL . '"}],"location":null}', OparlPerson::class);

        $this->assertCount(1, $person->getMembership() ?? []);
        $this->assertSame([], $person->getAdditionalProperties());
    }

    public function testReadsListPageDespiteInvalidEmbeddedObjects(): void
    {
        $page = (new ObjectMapper())->mapList(
            Json::decodeObject('{"data":[{"name":"A","membership":["' . self::MEMBERSHIP_URL . '"]},{"name":"B"}]}'),
            OparlPerson::class,
        );

        $this->assertSame(['A', 'B'], array_map(static fn(OparlPerson $p): ?string => $p->getName(), $page->getData()));
    }

    public function testWritesOriginalValueBack(): void
    {
        $person = Json::map('{"name":"A","membership":["' . self::MEMBERSHIP_URL . '"]}', OparlPerson::class);

        $this->assertSame(['name' => 'A', 'membership' => [self::MEMBERSHIP_URL]], Json::written($person));
    }

    public function testLeavesOutUnparsableUrlsOfReferenceList(): void
    {
        $meeting = Json::map('{"participant":["http://[broken","https://oparl.example.org/p/1"]}', OparlMeeting::class);

        $this->assertSame(['https://oparl.example.org/p/1'], self::uris($meeting->getParticipant()));
        $this->assertSame(['http://[broken', 'https://oparl.example.org/p/1'], $meeting->getAdditionalProperty('participant'));
    }

    public function testLeavesOutReferencesThatAreNoUrl(): void
    {
        $meeting = Json::map('{"participant":[42,{"name":"x"},"https://oparl.example.org/p/1"]}', OparlMeeting::class);

        $this->assertSame(['https://oparl.example.org/p/1'], self::uris($meeting->getParticipant()));
    }

    public function testLeavesSingleReferenceNullIfInvalid(): void
    {
        $consultation = Json::map('{"meeting":42,"role":"Beratung"}', OparlConsultation::class);

        $this->assertNull($consultation->getMeeting());
        $this->assertSame(42, $consultation->getAdditionalProperty('meeting'));
        $this->assertSame('Beratung', $consultation->getRole());
    }

    public function testReadsSingleValueAsList(): void
    {
        $person = Json::map('{"email":"info@example.org"}', OparlPerson::class);

        $this->assertSame(['info@example.org'], $person->getEmail());
        $this->assertSame([], $person->getAdditionalProperties());
    }

    public function testLeavesNumberNullIfInvalid(): void
    {
        $item = Json::map('{"order":"eins","name":"TOP 1"}', OparlAgendaItem::class);

        $this->assertNull($item->getOrder());
        $this->assertSame('eins', $item->getAdditionalProperty('order'));
        $this->assertSame('TOP 1', $item->getName());
    }

    public function testKeepsInvalidDateAsAdditionalPropertyAndWritesItBack(): void
    {
        $logger = new RecordingLogger();
        $term = Json::map('{"startDate":"0000-00-00"}', OparlLegislativeTerm::class, $logger);

        $this->assertNull($term->getStartDate());
        $this->assertSame('0000-00-00', $term->getAdditionalProperty('startDate'));
        $this->assertSame(['startDate' => '0000-00-00'], Json::written($term));
        $this->assertSame(
            ['Ignoring invalid value of "startDate" in OparlLegislativeTerm, kept as additional property:'
                . ' expected a date, got "0000-00-00"'],
            $logger->messages('warning'),
        );
    }

    public function testKeepsOtherPropertiesOnInvalidDateTime(): void
    {
        $meeting = Json::map(
            '{"name":"Rat","start":"2024-01-21T18:00:00+01:00","end":"unbekannt","created":"2024-01-01T09:00:00Z"}',
            OparlMeeting::class,
        );

        $this->assertSame('Rat', $meeting->getName());
        $this->assertSame('2024-01-21T18:00:00+01:00', $meeting->getStart()?->format(DATE_ATOM));
        $this->assertNull($meeting->getEnd());
        $this->assertSame('2024-01-01T09:00:00+00:00', $meeting->getCreated()?->format(DATE_ATOM));
    }
}
