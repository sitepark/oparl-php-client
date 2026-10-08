<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\V1\Objects;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlPaper;
use SP\OparlClient\V1\Objects\OparlSystem;
use SP\OparlClient\V1\Objects\OparlTypes;

#[CoversClass(OparlTypes::class)]
final class OparlTypesTest extends TestCase
{
    public function testMapsTypesOfOparl11(): void
    {
        $this->assertSame(OparlBody::class, OparlTypes::classOf('https://schema.oparl.org/1.1/Body'));
        $this->assertSame(OparlAgendaItem::class, OparlTypes::classOf('https://schema.oparl.org/1.1/AgendaItem'));
        $this->assertSame(OparlSystem::class, OparlTypes::classOf('https://schema.oparl.org/1.1/System'));
    }

    public function testMapsTypesOfOparl10AndVariants(): void
    {
        $this->assertSame(OparlPaper::class, OparlTypes::classOf('https://schema.oparl.org/1.0/Paper'));
        $this->assertSame(OparlPaper::class, OparlTypes::classOf('http://schema.oparl.org/1.1/Paper'));
        $this->assertSame(OparlPaper::class, OparlTypes::classOf('https://schema.oparl.org/1.1/Paper/'));
        $this->assertSame(OparlPaper::class, OparlTypes::classOf(' https://schema.oparl.org/1.1/Paper '));
    }

    public function testReturnsNullForUnknownTypes(): void
    {
        $this->assertNull(OparlTypes::classOf(null));
        $this->assertNull(OparlTypes::classOf('https://schema.oparl.org/1.1/Error'));
        $this->assertNull(OparlTypes::classOf('https://schema.oparl.org/2.0/Body'));
        $this->assertNull(OparlTypes::classOf('https://schema.oparl.org/1.1/Body/x'));
        $this->assertNull(OparlTypes::classOf('Body'));
    }

    public function testReturnsTypeOfClass(): void
    {
        $this->assertSame('https://schema.oparl.org/1.1/Meeting', OparlTypes::typeOf(OparlMeeting::class));
        $this->assertNull(OparlTypes::typeOf(OparlObjectV1::class));
        $this->assertNull(OparlTypes::typeOf(\stdClass::class));
    }
}
