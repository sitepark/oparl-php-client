<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Internal\LogSafe;

#[CoversClass(LogSafe::class)]
final class LogSafeTest extends TestCase
{
    public function testReplacesLineBreaks(): void
    {
        $this->assertSame('a??b', LogSafe::of("a\r\nb"));
    }

    public function testReplacesOtherControlCharacters(): void
    {
        $this->assertSame('a?b?c?d', LogSafe::of("a\tb\x00c\x7Fd"));
    }

    public function testReplacesC1ControlCharacters(): void
    {
        $this->assertSame('a?b', LogSafe::of("a\u{0085}b"));
    }

    public function testKeepsPrintableCharacters(): void
    {
        $this->assertSame('Grüße & "Zitat" €', LogSafe::of('Grüße & "Zitat" €'));
    }

    public function testHandlesInvalidUtf8(): void
    {
        $this->assertSame("a?\xFFb", LogSafe::of("a\n\xFFb"));
    }
}
