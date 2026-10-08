<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

/**
 * Repairs URLs that are not valid URI references (RFC 3986), e.g. because they contain spaces, by
 * percent-encoding the offending characters. A single malformed URL thereby no longer prevents
 * the whole object or list page from being read.
 *
 * Valid URLs are kept exactly as sent by the server, as required by the OParl specification.
 * Only invalid URLs are changed, since they could not be requested otherwise.
 *
 * @internal
 */
final class LenientUrl
{
    /**
     * Characters besides letters and digits that may appear in a URI (RFC 3986).
     */
    private const URI_CHARS = "-._~:/?#[]@!$&'()*+,;=";

    /**
     * Components of a URI reference, RFC 3986 appendix B.
     */
    private const COMPONENTS = '~^(?:([^:/?#]+):)?(?://([^/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#(.*))?$~s';

    /**
     * IPv6 address or IPvFuture in brackets, optionally followed by a port.
     */
    private const IP_LITERAL_AND_PORT = '~^\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._\~!$&\'()*+,;=:]+)\](?::[0-9]*)?$~';

    /**
     * Characters allowed in a URI and escape sequences.
     */
    private const ALLOWED = '~^(?:[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=]|%[0-9A-Fa-f]{2})*$~';

    private function __construct() {}

    /**
     * Parses the given URL, percent-encoding characters that are not allowed in a URI.
     *
     * @return string|null the URL, or `null` if it is invalid even after encoding
     */
    public static function parse(string $value): ?string
    {
        $trimmed = trim($value);
        if (self::isValid($trimmed)) {
            return $trimmed;
        }
        $encoded = PercentEncoder::encode($trimmed, self::URI_CHARS);
        return self::isValid($encoded) ? $encoded : null;
    }

    /**
     * Returns whether the value is a valid URI reference: only characters allowed by RFC 3986,
     * valid escape sequences, a valid scheme, brackets only around an IP literal host and no
     * second `#`.
     */
    public static function isValid(string $value): bool
    {
        if (preg_match(self::ALLOWED, $value) !== 1) {
            return false;
        }
        // matches every string, it only splits it into its components
        preg_match(self::COMPONENTS, $value, $parts, PREG_UNMATCHED_AS_NULL);
        [, $scheme, $authority, $path, $query, $fragment] = $parts + array_fill(0, 6, null);
        if ($scheme !== null && preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*$~', $scheme) !== 1) {
            return false;
        }
        if ($authority !== null && !self::isValidAuthority($authority)) {
            return false;
        }
        foreach ([$path, $query, $fragment] as $part) {
            if ($part !== null && strpbrk($part, '[]#') !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Brackets are only allowed around an IP literal host, e.g. `[::1]`.
     */
    private static function isValidAuthority(string $authority): bool
    {
        $at = strrpos($authority, '@');
        $userInfo = $at === false ? '' : substr($authority, 0, $at);
        $hostAndPort = $at === false ? $authority : substr($authority, $at + 1);
        if (strpbrk($userInfo, '[]') !== false) {
            return false;
        }
        if (!str_starts_with($hostAndPort, '[')) {
            return strpbrk($hostAndPort, '[]') === false;
        }
        return preg_match(self::IP_LITERAL_AND_PORT, $hostAndPort) === 1;
    }
}
