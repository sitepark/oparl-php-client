<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Test\Fixture\Json;
use SP\OparlClient\V1\Objects\OparlConsultation;
use SP\OparlClient\V1\Objects\OparlFile;

/**
 * Malformed URLs and references, with the OParl object types.
 */
final class DeserializationRobustnessTest extends TestCase
{
    public function testEncodesSpacesInUrl(): void
    {
        $file = Json::map('{"accessUrl":"https://oparl.example.org/files/a b.pdf"}', OparlFile::class);

        $this->assertSame('https://oparl.example.org/files/a%20b.pdf', $file->getAccessUrl());
    }

    public function testKeepsValidUrlUnchanged(): void
    {
        $file = Json::map('{"accessUrl":"https://oparl.example.org/files/a%20b.pdf?x=1&y=%2B#top"}', OparlFile::class);

        $this->assertSame('https://oparl.example.org/files/a%20b.pdf?x=1&y=%2B#top', $file->getAccessUrl());
    }

    public function testEncodesInvalidUrlsInList(): void
    {
        $file = Json::map(
            '{"paper":["https://oparl.example.org/paper/1","https://oparl.example.org/paper/a|b"]}',
            OparlFile::class,
        );

        $this->assertSame(
            ['https://oparl.example.org/paper/1', 'https://oparl.example.org/paper/a%7Cb'],
            array_map(static fn(OparlReference $r): string => $r->getUri(), $file->getPaper() ?? []),
        );
        $this->assertSame([], $file->getAdditionalProperties());
    }

    public function testIgnoresUrlThatCanNotBeRepaired(): void
    {
        $file = Json::map('{"accessUrl":"http://[oparl.example.org","name":"Datei"}', OparlFile::class);

        $this->assertNull($file->getAccessUrl());
        $this->assertSame('Datei', $file->getName());
        $this->assertSame('http://[oparl.example.org', $file->getAdditionalProperty('accessUrl'));
    }

    public function testKeepsEmptyUrl(): void
    {
        $this->assertSame('', Json::map('{"accessUrl":""}', OparlFile::class)->getAccessUrl());
    }

    public function testEncodesSpacesInReference(): void
    {
        $consultation = Json::map('{"meeting":"https://oparl.example.org/meeting/a b"}', OparlConsultation::class);

        $this->assertSame('https://oparl.example.org/meeting/a%20b', $consultation->getMeeting()?->getUri());
    }

    public function testIgnoresReferenceThatCanNotBeRepaired(): void
    {
        $consultation = Json::map('{"meeting":"http://[oparl.example.org","role":"Beratung"}', OparlConsultation::class);

        $this->assertNull($consultation->getMeeting());
        $this->assertSame('Beratung', $consultation->getRole());
    }

    public function testUsesIdOfEmbeddedObjectAsReference(): void
    {
        $consultation = Json::map(
            '{"meeting":{"id":"https://oparl.example.org/meeting/1","name":"Rat"}}',
            OparlConsultation::class,
        );

        $this->assertSame('https://oparl.example.org/meeting/1', $consultation->getMeeting()?->getUri());
    }

    public function testIgnoresReferenceThatIsNoUrl(): void
    {
        $this->assertNull(Json::map('{"meeting":42}', OparlConsultation::class)->getMeeting());
    }
}
