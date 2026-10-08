<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Core\Query\Limit;
use SP\OparlClient\Core\Query\Modified;
use SP\OparlClient\Core\Query\OmitInternal;
use SP\OparlClient\Core\Query\QueryParam;

#[CoversClass(OparlReference::class)]
final class OparlReferenceTest extends TestCase
{
    private static function withParams(string $uri, ?QueryParam ...$params): string
    {
        return (new OparlReference($uri))->withQueryParams(...$params)->getUri();
    }

    public function testResolvesThroughLoader(): void
    {
        $requested = [];
        $reference = new OparlReference(
            'https://oparl.example.org/meeting/1',
            function (string $uri) use (&$requested): string {
                $requested[] = $uri;
                return 'meeting';
            },
        );

        $this->assertSame([], $requested, 'no request before get()');
        $this->assertSame('meeting', $reference->get());
        $this->assertSame(['https://oparl.example.org/meeting/1'], $requested);
    }

    public function testFailsWithoutLoader(): void
    {
        $reference = new OparlReference('https://oparl.example.org/meeting/1');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('https://oparl.example.org/meeting/1');
        $reference->get();
    }

    public function testCopyWithParamsResolvesThroughSameLoader(): void
    {
        $reference = new OparlReference(
            'https://oparl.example.org/papers',
            static fn(string $uri): string => 'loaded ' . $uri,
        );

        $copy = $reference->withQueryParams(OmitInternal::true());

        $this->assertSame('loaded https://oparl.example.org/papers?omit_internal=true', $copy->get());
        $this->assertSame('https://oparl.example.org/papers', $reference->getUri(), 'unchanged');
    }

    public function testIsSerializedAsUrl(): void
    {
        $this->assertSame(
            '"https://oparl.example.org/meeting/1"',
            json_encode(new OparlReference('https://oparl.example.org/meeting/1'), JSON_UNESCAPED_SLASHES),
        );
    }

    public function testEncodesPlusOfTimezoneOffset(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?modified_since=2014-01-01T02:00:00%2B01:00',
            self::withParams('https://oparl.example.org/papers', Modified::since('2014-01-01T02:00:00+01:00')),
        );
    }

    public function testEncodesPercentSignOfValuesPassedEncoded(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?q=2014-01-01T00%253A00%253A00%252B01%253A00',
            self::withParams(
                'https://oparl.example.org/papers',
                new QueryParam('q', '2014-01-01T00%3A00%3A00%2B01%3A00'),
            ),
        );
    }

    public function testEncodesPercentWithoutEscapeSequence(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?q=100%25',
            self::withParams('https://oparl.example.org/papers', new QueryParam('q', '100%')),
        );
    }

    public function testEncodesReservedAndNonAsciiCharacters(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?q=a%26b%3Dc%23d%20%C3%A4',
            self::withParams('https://oparl.example.org/papers', new QueryParam('q', 'a&b=c#d ä')),
        );
    }

    public function testKeepsExistingEncodedQueryUnchanged(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?page=2&modified_since=2014-01-01T02%3A00%3A00%2B01%3A00'
            . '&omit_internal=true',
            self::withParams(
                'https://oparl.example.org/papers?page=2&modified_since=2014-01-01T02%3A00%3A00%2B01%3A00',
                OmitInternal::true(),
            ),
        );
    }

    public function testKeepsFragment(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?page=2&omit_internal=true#top',
            self::withParams('https://oparl.example.org/papers?page=2#top', OmitInternal::true()),
        );
        $this->assertSame(
            'https://oparl.example.org/papers?omit_internal=true#a?b',
            self::withParams('https://oparl.example.org/papers#a?b', OmitInternal::true()),
        );
    }

    public function testHandlesEmptyQuery(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?omit_internal=true',
            self::withParams('https://oparl.example.org/papers?', OmitInternal::true()),
        );
        $this->assertSame(
            'https://oparl.example.org/papers?page=2&omit_internal=true',
            self::withParams('https://oparl.example.org/papers?page=2&', OmitInternal::true()),
        );
    }

    public function testSkipsNullParams(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?omit_internal=true',
            self::withParams('https://oparl.example.org/papers', null, OmitInternal::true(), null),
        );
    }

    public function testKeepsReferenceWithoutParams(): void
    {
        $reference = new OparlReference('https://oparl.example.org/papers?page=2');

        $this->assertSame($reference, $reference->withQueryParams());
        $this->assertSame($reference, $reference->withQueryParams(null, new QueryParam(' ', 'x')));
    }

    public function testCombinesLimitWithOtherParameters(): void
    {
        $this->assertSame(
            'https://oparl.example.org/papers?omit_internal=true&limit=20',
            self::withParams('https://oparl.example.org/papers', OmitInternal::true(), Limit::of(20)),
        );
    }
}
