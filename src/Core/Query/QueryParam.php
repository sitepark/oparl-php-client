<?php

declare(strict_types=1);

namespace SP\OparlClient\Core\Query;

use DateTimeInterface;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PercentEncoder;
use Stringable;

/**
 * A URL parameter, appended to a reference with
 * {@see \SP\OparlClient\Core\OparlReference::withQueryParams()}. Use the subclasses for the
 * parameters defined by the specification, or this class for any other parameter.
 */
class QueryParam implements Stringable
{
    /**
     * Characters besides letters and digits that are not encoded.
     */
    private const UNENCODED_CHARS = "-._~!$'()*,:@/?";

    /**
     * @param string $name the name of the parameter
     * @param string $value the value, unencoded; it is URL-encoded when the parameter is appended
     */
    public function __construct(
        private readonly string $name,
        private readonly string $value,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the given parameters as URL-encoded query string, joined with `&`. `null` parameters
     * and parameters without name are skipped.
     */
    public static function join(?QueryParam ...$params): string
    {
        $query = [];
        foreach ($params as $param) {
            $string = (string) $param;
            if ($string !== '') {
                $query[] = $string;
            }
        }
        return implode('&', $query);
    }

    /**
     * Returns `name=value`, URL-encoded for use in a query string, or an empty string if the name
     * is blank.
     */
    public function __toString(): string
    {
        if (trim($this->name) === '') {
            return '';
        }
        return self::encode($this->name) . '=' . self::encode($this->value);
    }

    /**
     * Formats a date-time as required by the specification for filters: with seconds, without
     * fraction and with the offset of the date-time, e.g. `2024-01-21T00:00:00+01:00`.
     */
    protected static function formatDateTime(DateTimeInterface $dateTime): string
    {
        return OparlTime::formatDateTime($dateTime);
    }

    private static function encode(string $s): string
    {
        return PercentEncoder::encode($s, self::UNENCODED_CHARS, false);
    }
}
