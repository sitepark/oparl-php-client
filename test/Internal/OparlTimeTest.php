<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Internal;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Internal\OparlTime;

#[CoversClass(OparlTime::class)]
final class OparlTimeTest extends TestCase
{
    private const FULL_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dateTimes(): iterable
    {
        yield 'specification' => ['1969-07-21T02:56:00+00:00', '1969-07-21T02:56:00.000000+00:00'];
        yield 'zulu and fraction' => ['2024-01-21T10:15:30.5Z', '2024-01-21T10:15:30.500000+00:00'];
        yield 'nanoseconds' => ['2024-01-21T10:15:30.123456789+01:00', '2024-01-21T10:15:30.123456+01:00'];
        yield 'without offset in winter' => ['2024-01-21T10:00:00', '2024-01-21T10:00:00.000000+01:00'];
        yield 'without offset in summer' => ['2024-07-21 10:00:00', '2024-07-21T10:00:00.000000+02:00'];
        yield 'date only' => ['2024-01-21', '2024-01-21T00:00:00.000000+01:00'];
        yield 'without seconds' => ['2024-01-21T10:00+05:30', '2024-01-21T10:00:00.000000+05:30'];
        yield 'offset without colon' => ['2024-01-21T10:00:00-0230', '2024-01-21T10:00:00.000000-02:30'];
        yield 'offset hours only' => ['2024-01-21T10:00:00+01', '2024-01-21T10:00:00.000000+01:00'];
        yield 'last second of a day' => ['2024-12-31T23:59:59+01:00', '2024-12-31T23:59:59.000000+01:00'];
        yield 'largest offset' => ['2024-01-21T10:00:00+18:00', '2024-01-21T10:00:00.000000+18:00'];
        yield 'smallest offset' => ['2024-01-21T10:00:00-18:00', '2024-01-21T10:00:00.000000-18:00'];
        yield 'offset with minutes' => ['2024-01-21T10:00:00+05:45', '2024-01-21T10:00:00.000000+05:45'];
        yield 'whitespace and lower case' => [" 2024-01-21t10:00:00z\n", '2024-01-21T10:00:00.000000+00:00'];
    }

    #[DataProvider('dateTimes')]
    public function testParsesDateTime(string $value, string $expected): void
    {
        $this->assertSame($expected, OparlTime::parseDateTime($value)?->format(self::FULL_FORMAT));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'text' => ['gestern'];
        yield 'zero date' => ['0000-00-00'];
        yield 'month 13' => ['2024-13-01'];
        yield 'february 30' => ['2024-02-30'];
        yield 'hour 24' => ['2024-01-21T24:00:00Z'];
        yield 'offset out of range' => ['2024-01-21T10:00:00+19:00'];
        yield 'offset just out of range' => ['2024-01-21T10:00:00+18:01'];
        yield 'minute 60' => ['2024-01-21T10:60:00Z'];
        yield 'second 60' => ['2024-01-21T10:00:60Z'];
        yield 'offset minutes out of range' => ['2024-01-21T10:00:00+01:60'];
        yield 'empty' => [''];
        yield 'trailing text' => ['2024-01-21T10:00:00Z abc'];
    }

    #[DataProvider('invalidValues')]
    public function testReturnsNullForInvalidDateTime(string $value): void
    {
        $this->assertNull(OparlTime::parseDateTime($value));
    }

    #[DataProvider('invalidValues')]
    public function testReturnsNullForInvalidDate(string $value): void
    {
        $this->assertNull(OparlTime::parseDate($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dates(): iterable
    {
        yield 'date' => ['2024-03-01'];
        yield 'date-time with offset' => ['2024-03-01T23:30:00-05:00'];
        yield 'date-time without offset' => ['2024-03-01T23:30:00'];
    }

    #[DataProvider('dates')]
    public function testParsesDateAsStartOfDayInGermanTime(string $value): void
    {
        $date = OparlTime::parseDate($value);

        $this->assertSame('2024-03-01T00:00:00.000000+01:00', $date?->format(self::FULL_FORMAT));
        $this->assertSame('Europe/Berlin', $date->getTimezone()->getName());
    }

    public function testKeepsOffsetSentByServer(): void
    {
        $dateTime = OparlTime::parseDateTime('2024-01-21T10:00:00-03:00');

        $this->assertSame('-03:00', $dateTime?->getTimezone()->getName());
    }

    public function testInterpretsLocalTimeInGapOfDaylightSavingTimeLikeJava(): void
    {
        $this->assertSame(
            '2024-03-31T03:30:00.000000+02:00',
            OparlTime::parseDateTime('2024-03-31T02:30:00')?->format(self::FULL_FORMAT),
        );
    }

    public function testFormatsDateTimeAsInSpecification(): void
    {
        $this->assertSame(
            '2024-01-21T10:00:00+01:00',
            OparlTime::formatDateTime(new DateTimeImmutable('2024-01-21T10:00:00.5', new DateTimeZone('+01:00'))),
        );
        $this->assertSame(
            '2024-01-21T10:00:00+00:00',
            OparlTime::formatDateTime(new DateTimeImmutable('2024-01-21T10:00:00Z')),
        );
    }

    public function testFormatsDateAsInSpecification(): void
    {
        $this->assertSame('2020-11-01', OparlTime::formatDate(new DateTimeImmutable('2020-11-01T23:00:00')));
    }
}
