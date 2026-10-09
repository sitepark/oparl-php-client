<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\LogSafe;
use Throwable;

/**
 * Thrown when a response, or JSON passed to {@see \SP\OparlClient\OparlClient::fromJson()}, is no
 * valid JSON or no JSON object.
 */
class OparlParseException extends OparlException
{
    /**
     * @param string|null $uri the URL of the response, `null` for JSON not read from a server
     */
    public function __construct(?string $uri, string $reason, ?Throwable $previous = null)
    {
        parent::__construct(
            ($uri !== null ? 'Invalid response from ' . $uri : 'Invalid JSON') . ': ' . LogSafe::of($reason),
            $uri,
            $previous,
        );
    }
}
