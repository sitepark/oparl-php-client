<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Reads and writes the `date` and `date-time` values of OParl.
 *
 * Values are written in the format of the specification (`Y-m-d` and `Y-m-d\TH:i:sP`). Reading is
 * tolerant: date-times without offset are interpreted in the time zone {@see self::DEFAULT_ZONE},
 * a date where a date-time is expected as start of that day, and a date-time where a date is
 * expected as its date. Values that can not be parsed at all are read as `null`.
 *
 * @internal
 */
final class OparlTime
{
    /**
     * Time zone of date-times sent without offset, and of dates. OParl is used by German council
     * information systems, so their local time is assumed.
     */
    public const DEFAULT_ZONE = 'Europe/Berlin';

    public const DATE_TIME_FORMAT = 'Y-m-d\TH:i:sP';

    public const DATE_FORMAT = 'Y-m-d';

    /**
     * Date, optionally followed by a time with optional seconds, fraction and offset.
     */
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})'
        . '(?:T(\d{2}):(\d{2})(?::(\d{2})(?:[.,](\d{1,9}))?)?(Z|[+-]\d{2}(?::?\d{2})?)?)?$/i';

    private function __construct() {}

    /**
     * Parses an OParl `date-time` tolerantly.
     *
     * @return DateTimeImmutable|null the date-time with the offset sent by the server, or `null`
     *     if the value can not be parsed
     */
    public static function parseDateTime(string $value): ?DateTimeImmutable
    {
        $parts = self::parse($value);
        if ($parts === null) {
            return null;
        }
        [$year, $month, $day, $hour, $minute, $second, $microsecond, $offset] = $parts;
        $zone = new DateTimeZone($offset ?? self::DEFAULT_ZONE);
        return (new DateTimeImmutable('now', $zone))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second, $microsecond);
    }

    /**
     * Parses an OParl `date` tolerantly, also a `date-time`, of which the date is taken as sent.
     *
     * @return DateTimeImmutable|null the start of the day in {@see self::DEFAULT_ZONE}, or `null`
     *     if the value can not be parsed
     */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $parts = self::parse($value);
        if ($parts === null) {
            return null;
        }
        return (new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_ZONE)))
            ->setDate($parts[0], $parts[1], $parts[2])
            ->setTime(0, 0);
    }

    /**
     * Formats a date-time as required by the specification, e.g. `2024-01-21T10:00:00+01:00`.
     *
     * @return ($dateTime is null ? null : string)
     */
    public static function formatDateTime(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime?->format(self::DATE_TIME_FORMAT);
    }

    /**
     * Formats a date as required by the specification, e.g. `2024-01-21`.
     *
     * @return ($date is null ? null : string)
     */
    public static function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }

    /**
     * Splits the value into its parts and checks their ranges. A space instead of `T` between date
     * and time and surrounding whitespace are accepted.
     *
     * @return array{int, int, int, int, int, int, int, ?non-empty-string}|null year, month, day, hour,
     *     minute, second, microsecond and offset (`null` if none was sent)
     */
    private static function parse(string $value): ?array
    {
        $normalized = trim($value);
        if (strlen($normalized) > 10 && $normalized[10] === ' ') {
            $normalized[10] = 'T';
        }
        if (preg_match(self::PATTERN, $normalized, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        $hour = (int) ($m[4] ?? 0);
        $minute = (int) ($m[5] ?? 0);
        $second = (int) ($m[6] ?? 0);
        $microsecond = (int) substr(str_pad($m[7] ?? '', 6, '0'), 0, 6);
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }
        $offset = null;
        if ($m[8] !== null) {
            $offset = self::offset($m[8]);
            if ($offset === null) {
                return null;
            }
        }
        return [$year, $month, $day, $hour, $minute, $second, $microsecond, $offset];
    }

    /**
     * Normalizes the offset to `+hh:mm`, as accepted by {@see DateTimeZone}.
     *
     * @return non-empty-string|null the offset, or `null` if it is out of the range of ±18:00
     */
    private static function offset(string $offset): ?string
    {
        if (strtoupper($offset) === 'Z') {
            return '+00:00';
        }
        $digits = str_replace(':', '', $offset);
        $hours = (int) substr($digits, 1, 2);
        $minutes = (int) str_pad(substr($digits, 3), 2, '0');
        if ($minutes > 59 || $hours * 60 + $minutes > 18 * 60) {
            return null;
        }
        return ($digits[0] === '-' ? '-' : '+') . sprintf('%02d:%02d', $hours, $minutes);
    }
}
