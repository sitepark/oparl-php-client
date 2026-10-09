<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Internal\PercentEncoder;

#[CoversClass(PercentEncoder::class)]
final class PercentEncoderTest extends TestCase
{
    public function testKeepsLettersDigitsAndGivenCharacters(): void
    {
        $this->assertSame('azAZ09-_', PercentEncoder::encode('azAZ09-_', '-_'));
    }

    public function testEncodesOtherCharactersAsUtf8(): void
    {
        $this->assertSame('a%20b%7C%C3%A4%E2%82%AC', PercentEncoder::encode('a b|ä€', ''));
    }

    public function testKeepsValidEscapeSequences(): void
    {
        $this->assertSame('a%20b%2Bc', PercentEncoder::encode('a%20b%2Bc', ''));
    }

    public function testEncodesPercentNotStartingAnEscapeSequence(): void
    {
        $this->assertSame('100%25%25zz%252', PercentEncoder::encode('100%%zz%2', ''));
    }

    public function testEncodesEveryPercentIfEscapeSequencesAreNotKept(): void
    {
        $this->assertSame('a%2520b', PercentEncoder::encode('a%20b', '', false));
    }

    public function testEncodesNonAsciiBytesEvenIfListedAsUnencoded(): void
    {
        $this->assertSame('%C3%A4', PercentEncoder::encode('ä', "\xC3\xA4"));
    }
}
