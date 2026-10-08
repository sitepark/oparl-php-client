<?php

declare(strict_types=1);

namespace SP\OparlClient\Core\Query;

use DateTimeInterface;

/**
 * Filters a list by the `created` property of its elements, via the URL parameters
 * `created_since` and `created_until`:
 *
 * ```php
 * $body->getPaper()->withQueryParams(Created::since(new DateTimeImmutable('2024-01-01T00:00:00+01:00')))->get();
 * ```
 *
 * The specification requires a full date-time including the time zone. A `DateTimeInterface` is
 * sent with the offset of its time zone and without fraction of seconds.
 */
final class Created extends QueryParam
{
    public const PARAM_NAME_SINCE = 'created_since';

    public const PARAM_NAME_UNTIL = 'created_until';

    private function __construct(string $name, string $value)
    {
        parent::__construct($name, $value);
    }

    /**
     * Objects created since the given date-time. A string, e.g. `2024-01-01T00:00:00+01:00`, is
     * sent as is; pass it unencoded, it is URL-encoded.
     */
    public static function since(string|DateTimeInterface $date): self
    {
        return new self(self::PARAM_NAME_SINCE, self::value($date));
    }

    /**
     * Objects created until the given date-time. A string, e.g. `2024-01-01T00:00:00+01:00`, is
     * sent as is; pass it unencoded, it is URL-encoded.
     */
    public static function until(string|DateTimeInterface $date): self
    {
        return new self(self::PARAM_NAME_UNTIL, self::value($date));
    }

    private static function value(string|DateTimeInterface $date): string
    {
        return $date instanceof DateTimeInterface ? self::formatDateTime($date) : $date;
    }
}
