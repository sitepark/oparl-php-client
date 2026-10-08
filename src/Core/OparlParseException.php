<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\LogSafe;
use Throwable;

/**
 * Thrown when a response is no valid JSON or can not be mapped to the requested type.
 */
class OparlParseException extends OparlException
{
    public function __construct(string $uri, string $reason, ?Throwable $previous = null)
    {
        parent::__construct(
            'Invalid response from ' . $uri . ': ' . LogSafe::of($reason),
            $uri,
            $previous,
        );
    }
}
