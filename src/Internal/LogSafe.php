<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

/**
 * Makes strings sent by a server safe to include in log and exception messages, so that a server
 * can not forge log lines by sending line breaks or other control characters.
 *
 * @internal
 */
final class LogSafe
{
    private function __construct() {}

    /**
     * Replaces control characters, including line breaks, with `?`.
     */
    public static function of(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            // valid UTF-8: also covers the C1 control characters U+0080 to U+009F
            return (string) preg_replace('/\p{Cc}/u', '?', $value);
        }
        return (string) preg_replace('/[\x00-\x1F\x7F]/', '?', $value);
    }
}
