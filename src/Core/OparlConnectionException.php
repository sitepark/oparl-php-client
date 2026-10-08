<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\LogSafe;
use Throwable;

/**
 * Thrown when a request fails before a complete response arrived, e.g. because the server can not
 * be reached, closes the connection or does not answer in time. The previous exception is the
 * one of the PSR-18 client.
 */
class OparlConnectionException extends OparlException
{
    public function __construct(string $uri, Throwable $previous)
    {
        parent::__construct(
            'Request to ' . $uri . ' failed: ' . LogSafe::of($previous->getMessage()),
            $uri,
            $previous,
        );
    }
}
