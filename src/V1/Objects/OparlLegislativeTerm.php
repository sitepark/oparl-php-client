<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Dieser Objekttyp dient der Beschreibung einer Wahlperiode.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-legislativeterm
 */
final class OparlLegislativeTerm extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlBody>|null $body
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?OparlReference $body = null,
        private readonly ?string $name = null,
        private readonly ?DateTimeImmutable $startDate = null,
        private readonly ?DateTimeImmutable $endDate = null,
        ?DateTimeImmutable $created = null,
        ?DateTimeImmutable $modified = null,
        bool $deleted = false,
        ?array $keyword = null,
        ?string $license = null,
        ?string $web = null,
        array $additionalProperties = [],
    ) {
        parent::__construct(
            id: $id,
            type: $type,
            created: $created,
            modified: $modified,
            deleted: $deleted,
            keyword: $keyword,
            license: $license,
            web: $web,
            additionalProperties: $additionalProperties,
        );
    }

    public static function read(PropertyReader $reader): self
    {
        return new self(
            id: $reader->url('id'),
            type: $reader->string('type'),
            body: $reader->reference('body', OparlBody::class),
            name: $reader->string('name'),
            startDate: $reader->date('startDate'),
            endDate: $reader->date('endDate'),
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
     * Rückreferenz auf die Körperschaft, welche nur dann ausgegeben werden muss, wenn das
     * LegislativeTerm-Objekt einzeln abgerufen wird, d.h. nicht Teil einer internen Ausgabe ist.
     *
     * @return OparlReference<OparlBody>|null
     */
    public function getBody(): ?OparlReference
    {
        return $this->body;
    }

    /**
     * Nutzerfreundliche Bezeichnung der Wahlperiode.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Der erste Tag der Wahlperiode.
     */
    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Der letzte Tag der Wahlperiode.
     */
    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'body' => $this->body,
            'name' => $this->name,
            'startDate' => OparlTime::formatDate($this->startDate),
            'endDate' => OparlTime::formatDate($this->endDate),
        ];
    }
}
