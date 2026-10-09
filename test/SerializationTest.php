<?php

declare(strict_types=1);

namespace SP\OparlClient\Test;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlListLinks;
use SP\OparlClient\Core\OparlParseException;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\OparlClient;
use SP\OparlClient\Test\Fixture\MockHttpClient;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlPaper;

/**
 * Storing objects, e.g. in a cache, and restoring them.
 */
#[CoversClass(OparlClient::class)]
#[CoversClass(OparlReference::class)]
#[CoversClass(OparlParseException::class)]
final class SerializationTest extends TestCase
{
    private const MEETING = '{"id":"https://oparl.example.org/meeting/1","type":"https://schema.oparl.org/1.1/Meeting",'
        . '"name":"Ratssitzung","start":"2024-01-21T18:00:00+01:00",'
        . '"organization":["https://oparl.example.org/organization/1"],'
        . '"location":{"id":"https://oparl.example.org/location/1","room":"Ratssaal"},'
        . '"Hersteller:stream":"https://video.example.org/1"}';

    private MockHttpClient $http;

    private OparlClient $client;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->client = new OparlClient($this->http, new Psr17Factory());
    }

    public function testRestoresObjectWrittenAsJson(): void
    {
        $meeting = $this->client->fromJson(self::MEETING, OparlMeeting::class);

        $copy = $this->client->fromJson((string) json_encode($meeting), OparlMeeting::class);

        $this->assertSame('Ratssitzung', $copy->getName());
        $this->assertSame('2024-01-21T18:00:00+01:00', $copy->getStart()?->format(DATE_ATOM));
        $this->assertSame('Ratssaal', $copy->getLocation()?->getRoom());
        $this->assertSame('https://video.example.org/1', $copy->getAdditionalProperty('Hersteller:stream'));
        $this->assertEquals(json_decode(self::MEETING, true), json_decode((string) json_encode($copy), true));
        $this->assertSame([], $this->http->requests, 'no request');
    }

    public function testResolvesReferencesOfRestoredObjectThroughClient(): void
    {
        $this->http->respond('/organization/1', 200, '{"name":"Rat"}');

        $meeting = $this->client->fromJson(self::MEETING, OparlMeeting::class);

        $this->assertSame('Rat', ($meeting->getOrganization()[0] ?? null)?->get()->getName());
    }

    public function testRestoresListPageAndFetchesFurtherPages(): void
    {
        $this->http->respond('/papers?page=2', 200, '{"data":[{"name":"b"}],"links":{}}');

        $page = $this->client->listFromJson(
            '{"data":[{"name":"a"}],"links":{"next":"https://oparl.example.org/papers?page=2"}}',
            OparlPaper::class,
        );

        $this->assertSame(['a', 'b'], array_map(
            static fn(OparlPaper $p): ?string => $p->getName(),
            iterator_to_array($page->all(), false),
        ));
        $this->assertSame([], $page->getSourceUris());
    }

    public function testRejectsListClassForSingleObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('listFromJson()');
        $this->client->fromJson('{}', OparlList::class);
    }

    public function testRejectsLinksClassForSingleObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->fromJson('{}', OparlListLinks::class);
    }

    public function testFailsForInvalidJson(): void
    {
        try {
            $this->client->fromJson('{"name":', OparlBody::class);
            $this->fail('exception expected');
        } catch (OparlParseException $e) {
            $this->assertSame('Invalid JSON: Syntax error', $e->getMessage());
            $this->assertNull($e->getUri());
        }
    }

    public function testFailsForJsonThatIsNoObject(): void
    {
        $this->expectException(OparlParseException::class);
        $this->expectExceptionMessage('Invalid JSON: expected a JSON object, got array');
        $this->client->listFromJson('[]', OparlBody::class);
    }

    public function testFailsForEmptyJson(): void
    {
        try {
            $this->client->fromJson(' null ', OparlBody::class);
            $this->fail('exception expected');
        } catch (OparlException $e) {
            $this->assertSame(OparlException::class, $e::class);
            $this->assertSame('Empty JSON', $e->getMessage());
            $this->assertNull($e->getUri());
        }
    }

    public function testSerializesObjectWithoutLoaders(): void
    {
        $meeting = $this->client->fromJson(self::MEETING, OparlMeeting::class);

        $copy = unserialize(serialize($meeting));

        $this->assertInstanceOf(OparlMeeting::class, $copy);
        $this->assertSame('Ratssitzung', $copy->getName());
        $this->assertSame('https://video.example.org/1', $copy->getAdditionalProperty('Hersteller:stream'));
        $reference = $copy->getOrganization()[0] ?? null;
        $this->assertNotNull($reference);
        $this->assertSame('https://oparl.example.org/organization/1', $reference->getUri());
        $this->expectException(LogicException::class);
        $reference->get();
    }
}
