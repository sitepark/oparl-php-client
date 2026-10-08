<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

/**
 * Percent-encoding (RFC 3986) that can leave existing escape sequences untouched.
 *
 * @internal
 */
final class PercentEncoder
{
    private function __construct() {}

    /**
     * Encodes all bytes of the UTF-8 string `$value` as escape sequences, except letters, digits
     * and `$unencodedChars`.
     *
     * @param bool $keepEscapeSequences whether a `%` starting a valid escape sequence is kept, so
     *     that values already encoded are not encoded twice; otherwise every `%` is encoded
     */
    public static function encode(
        string $value,
        string $unencodedChars,
        bool $keepEscapeSequences = true,
    ): string {
        $encoded = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $c = $value[$i];
            if (
                self::isAlphanumeric($c)
                || (ord($c) < 0x80 && str_contains($unencodedChars, $c))
                || ($keepEscapeSequences && $c === '%' && self::isEscapeSequence($value, $i))
            ) {
                $encoded .= $c;
            } else {
                $encoded .= sprintf('%%%02X', ord($c));
            }
        }
        return $encoded;
    }

    /**
     * ASCII letters and digits only; unlike `ctype_alnum()` independent of the locale.
     */
    private static function isAlphanumeric(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9');
    }

    private static function isEscapeSequence(string $value, int $index): bool
    {
        return preg_match('/\\G%[0-9A-Fa-f]{2}/', $value, $matches, 0, $index) === 1;
    }
}
