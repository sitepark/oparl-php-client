<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SP\OparlClient\Core\OparlConnectionException;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlParseException;

#[CoversClass(OparlException::class)]
#[CoversClass(OparlConnectionException::class)]
#[CoversClass(OparlParseException::class)]
final class OparlExceptionTest extends TestCase
{
    public function testUriIsOptional(): void
    {
        $e = new OparlException('Invalid URL');

        $this->assertNull($e->getUri());
        $this->assertSame('Invalid URL', $e->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testKeepsUriAndPrevious(): void
    {
        $previous = new RuntimeException('cause');
        $e = new OparlException('Empty response', 'https://oparl.example.org/', $previous);

        $this->assertSame('https://oparl.example.org/', $e->getUri());
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testConnectionExceptionNamesUriAndCause(): void
    {
        $previous = new RuntimeException("Connection refused\nforged line");
        $e = new OparlConnectionException('https://oparl.example.org/', $previous);

        $this->assertSame(
            'Request to https://oparl.example.org/ failed: Connection refused?forged line',
            $e->getMessage(),
        );
        $this->assertSame('https://oparl.example.org/', $e->getUri());
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testParseExceptionNamesUriAndReason(): void
    {
        $e = new OparlParseException('https://oparl.example.org/', 'Syntax error');

        $this->assertSame(
            'Invalid response from https://oparl.example.org/: Syntax error',
            $e->getMessage(),
        );
        $this->assertSame('https://oparl.example.org/', $e->getUri());
        $this->assertInstanceOf(OparlException::class, $e);
    }
}
