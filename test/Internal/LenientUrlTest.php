<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Internal\LenientUrl;

#[CoversClass(LenientUrl::class)]
final class LenientUrlTest extends TestCase
{
    public function testKeepsValidUrlUnchanged(): void
    {
        $url = 'https://oparl.example.org/files/a%20b.pdf?x=1&y=%2B#top';

        $this->assertSame($url, LenientUrl::parse($url));
    }

    public function testEncodesSpaces(): void
    {
        $this->assertSame(
            'https://oparl.example.org/files/a%20b.pdf',
            LenientUrl::parse('https://oparl.example.org/files/a b.pdf'),
        );
    }

    public function testEncodesCharactersOutsideOfRfc3986(): void
    {
        $this->assertSame(
            'https://oparl.example.org/paper/a%7Cb/%C3%A4?q=%25zz',
            LenientUrl::parse('https://oparl.example.org/paper/a|b/ä?q=%zz'),
        );
    }

    public function testTrimsWhitespace(): void
    {
        $this->assertSame('https://oparl.example.org/', LenientUrl::parse(" https://oparl.example.org/\n"));
    }

    public function testKeepsEmptyUrl(): void
    {
        $this->assertSame('', LenientUrl::parse(''));
    }

    public function testIgnoresUrlThatCanNotBeRepaired(): void
    {
        $this->assertNull(LenientUrl::parse('http://[oparl.example.org'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validUrls(): iterable
    {
        yield 'http' => ['https://oparl.example.org/body/1'];
        yield 'relative' => ['body/1?x=1'];
        yield 'ipv6' => ['http://[::1]:8080/oparl'];
        yield 'ipv6 with user info' => ['http://user@[2001:db8::1]/'];
        yield 'ip future' => ['http://[v1.fe80::a+en1]/'];
        yield 'all allowed characters' => ["https://u:p@h/a-._~!$&'()*+,;=:@/?q=/?#f/?"];
    }

    #[DataProvider('validUrls')]
    public function testAcceptsValidUrl(string $url): void
    {
        $this->assertTrue(LenientUrl::isValid($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'space' => ['https://oparl.example.org/a b'];
        yield 'invalid escape' => ['https://oparl.example.org/%zz'];
        yield 'invalid scheme' => ['1http://oparl.example.org/'];
        yield 'unclosed bracket' => ['http://[oparl.example.org'];
        yield 'bracket in user info' => ['http://u[@oparl.example.org/'];
        yield 'bracket in path' => ['https://oparl.example.org/[1]'];
        yield 'two fragments' => ['https://oparl.example.org/#a#b'];
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsInvalidUrl(string $url): void
    {
        $this->assertFalse(LenientUrl::isValid($url));
    }
}
