<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\PropertyReader;

/**
 * Error object a server may return together with an error status code, see the chapter
 * "Ausnahmebehandlung" of the OParl specification.
 */
final class OparlError extends OparlObject
{
    /**
     * Type URL of the error object in OParl 1.1.
     */
    public const TYPE = 'https://schema.oparl.org/1.1/Error';

    /**
     * Type URLs of the error object in OParl 1.0 and 1.1.
     */
    private const TYPE_PATTERN = '~^https?://schema\.oparl\.org/1\.[01]/Error/?$~';

    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?string $type = null,
        private readonly ?string $message = null,
        private readonly ?string $debug = null,
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
    }

    public static function read(PropertyReader $reader): self
    {
        return new self(
            type: $reader->string('type'),
            message: $reader->string('message'),
            debug: $reader->string('debug'),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    /**
     * Returns whether the given `type` URL denotes an error object of OParl 1.0 or 1.1.
     */
    public static function isErrorType(?string $type): bool
    {
        return $type !== null && preg_match(self::TYPE_PATTERN, trim($type)) === 1;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Returns the message for the user of the client.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Returns additional information for the developer of the client.
     */
    public function getDebug(): ?string
    {
        return $this->debug;
    }

    protected function mappedProperties(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'debug' => $this->debug,
        ];
    }
}
