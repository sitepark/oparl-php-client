<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\LogSafe;

/**
 * Thrown when a server answers with a status code outside of 2xx. If the server sent an OParl
 * error object, it is available via {@see self::getError()}.
 */
class OparlHttpException extends OparlException
{
    public function __construct(
        string $uri,
        private readonly int $statusCode,
        private readonly ?OparlError $error = null,
    ) {
        $message = 'HTTP ' . $statusCode . ' for ' . $uri;
        if ($error?->getMessage() !== null) {
            $message .= ': ' . LogSafe::of($error->getMessage());
        }
        parent::__construct($message, $uri);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the error object sent by the server, or `null` if the response did not contain one.
     */
    public function getError(): ?OparlError
    {
        return $this->error;
    }
}
