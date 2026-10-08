<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use RuntimeException;
use Throwable;

/**
 * Base class of all exceptions thrown by the OParl client. Every failed request is reported as an
 * `OparlException`:
 *
 * - {@see OparlHttpException}: the server answered with a status code outside of 2xx
 * - {@see OparlParseException}: the response is no valid JSON or does not match the type
 * - {@see OparlConnectionException}: the server could not be reached
 * - `OparlException` itself: other errors, e.g. an invalid URL or an empty response
 */
class OparlException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $uri = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Returns the URL of the failed request, or `null` if the error is not tied to one.
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }
}
