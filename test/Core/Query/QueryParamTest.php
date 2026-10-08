<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core\Query;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\Query\Created;
use SP\OparlClient\Core\Query\Limit;
use SP\OparlClient\Core\Query\Modified;
use SP\OparlClient\Core\Query\OmitInternal;
use SP\OparlClient\Core\Query\QueryParam;

#[CoversClass(QueryParam::class)]
#[CoversClass(Created::class)]
#[CoversClass(Modified::class)]
#[CoversClass(OmitInternal::class)]
#[CoversClass(Limit::class)]
final class QueryParamTest extends TestCase
{
    public function testJoinsMultipleParams(): void
    {
        $this->assertSame(
            'created_since=2014-01-01T00:00:00%2B01:00&created_until=2014-01-31T23:59:59%2B01:00',
            QueryParam::join(
                Created::since('2014-01-01T00:00:00+01:00'),
                Created::until('2014-01-31T23:59:59+01:00'),
            ),
        );
    }

    public function testSkipsParamsWithoutName(): void
    {
        $this->assertSame('', (string) new QueryParam('', 'x'));
        $this->assertSame('a=1', QueryParam::join(null, new QueryParam(' ', 'x'), new QueryParam('a', '1')));
    }

    public function testEncodesName(): void
    {
        $this->assertSame('a%20b=c%252Fd/', (string) new QueryParam('a b', 'c%2Fd/'));
    }

    public function testFormatsDateTimeWithoutFractionOfSeconds(): void
    {
        $param = Modified::since(new DateTimeImmutable('2014-01-01T02:00:00.123+01:00'));

        $this->assertSame('modified_since', $param->getName());
        $this->assertSame('2014-01-01T02:00:00+01:00', $param->getValue());
        $this->assertSame('modified_since=2014-01-01T02:00:00%2B01:00', (string) $param);
    }

    public function testFormatsNegativeOffset(): void
    {
        $param = Modified::until(new DateTimeImmutable('2014-01-31T23:59:59-05:00'));

        $this->assertSame('modified_until', $param->getName());
        $this->assertSame('2014-01-31T23:59:59-05:00', $param->getValue());
    }

    public function testFormatsDateTimeInTimeZoneWithOffsetOfThatDate(): void
    {
        $berlin = new DateTimeZone('Europe/Berlin');

        $this->assertSame(
            '2024-01-21T00:00:00+01:00',
            Created::since(new DateTimeImmutable('2024-01-21 00:00:00', $berlin))->getValue(),
        );
        $this->assertSame(
            '2024-08-16T00:00:00+02:00',
            Created::until(new DateTimeImmutable('2024-08-16 00:00:00', $berlin))->getValue(),
        );
    }

    public function testFormatsUtcWithNumericOffset(): void
    {
        $param = Created::until(new DateTimeImmutable('2024-08-16T10:15:30.500Z'));

        $this->assertSame('created_until', $param->getName());
        $this->assertSame('2024-08-16T10:15:30+00:00', $param->getValue());
    }

    public function testCreatesOmitInternal(): void
    {
        $this->assertSame('omit_internal=true', (string) OmitInternal::true());
        $this->assertSame('omit_internal=false', (string) OmitInternal::false());
    }

    public function testCreatesLimit(): void
    {
        $limit = Limit::of(100);

        $this->assertSame('limit', $limit->getName());
        $this->assertSame('100', $limit->getValue());
        $this->assertSame('limit=100', (string) $limit);
    }

    public function testRejectsZeroLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Limit::of(0);
    }

    public function testRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be positive: -1');
        Limit::of(-1);
    }
}
