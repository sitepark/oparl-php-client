<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Common base class of the OParl 1.0 and 1.1 object types, with the properties all of them share.
 *
 * Objects of an unknown type, e.g. vendor-specific ones, are returned as this class by
 * {@see \SP\OparlClient\OparlClient::getAny()}; their other properties are available as
 * additional properties.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#eigenschaften-mit-verwendung-in-mehreren-objekttypen
 */
readonly class OparlObjectV1 extends OparlObject
{
    /**
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private ?string $id = null,
        private ?string $type = null,
        private ?DateTimeImmutable $created = null,
        private ?DateTimeImmutable $modified = null,
        private bool $deleted = false,
        private ?array $keyword = null,
        private ?string $license = null,
        private ?string $web = null,
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
    }

    public static function read(PropertyReader $reader): self
    {
        return new self(
            id: $reader->url('id'),
            type: $reader->string('type'),
            created: $reader->dateTime('created'),
            modified: $reader->dateTime('modified'),
            deleted: $reader->bool('deleted') ?? false,
            keyword: $reader->stringList('keyword'),
            license: $reader->string('license'),
            web: $reader->url('web'),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    /**
     * Returns the URL that identifies the object. It is the identity of an object: two objects with
     * the same `id` are the same object, possibly in different versions (see `modified`).
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Returns the type URL, e.g. `https://schema.oparl.org/1.1/Body`.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Returns the time the object was created.
     */
    public function getCreated(): ?DateTimeImmutable
    {
        return $this->created;
    }

    /**
     * Returns the time the object was last modified; for a deleted object the time of deletion.
     */
    public function getModified(): ?DateTimeImmutable
    {
        return $this->modified;
    }

    /**
     * Returns whether the object has been deleted. Unlike the other properties, which are `null`
     * if the server did not send them, this one is `false` then, since the specification only
     * marks deleted objects.
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Schlagworte zur optionalen Kategorisierung des Objekts.
     *
     * @return list<string>|null
     */
    public function getKeyword(): ?array
    {
        return $this->keyword;
    }

    /**
     * Lizenz, unter der die Daten des Objekts stehen. Wird `license` am `System` oder am `Body`
     * verwendet, gilt sie für alle Objekte des Systems bzw. der Körperschaft, sofern das einzelne
     * Objekt keine andere angibt. In der Regel eine URL; das Schema erlaubt bei den meisten
     * Objekttypen aber beliebige Zeichenketten.
     */
    public function getLicense(): ?string
    {
        return $this->license;
    }

    /**
     * Returns the URL of a website showing the object, e.g. in the council information system.
     */
    public function getWeb(): ?string
    {
        return $this->web;
    }

    /**
     * Returns the common properties; the subclasses append their own properties. `deleted` is
     * only written if it is `true`, as the specification only marks deleted objects.
     */
    protected function mappedProperties(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'keyword' => $this->keyword,
            'license' => $this->license,
            'created' => OparlTime::formatDateTime($this->created),
            'modified' => OparlTime::formatDateTime($this->modified),
            'web' => $this->web,
            'deleted' => $this->deleted ?: null,
        ];
    }
}
