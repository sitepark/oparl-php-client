<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Dieser Objekttyp dient dazu, einen Ortsbezug formal abzubilden. Ortsangaben können sowohl aus
 * Textinformationen bestehen (beispielsweise dem Namen einer Straße/eines Platzes oder eine genaue
 * Adresse) als auch aus Geodaten. Ortsangaben sind auch nicht auf einzelne Positionen beschränkt,
 * sondern können eine Vielzahl von Positionen, Flächen, Strecken etc. abdecken.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-location
 */
final readonly class OparlLocation extends OparlObjectV1
{
    /**
     * @param array<mixed>|null $geojson
     * @param list<OparlReference<OparlBody>>|null $bodies
     * @param list<OparlReference<OparlOrganization>>|null $organizations
     * @param list<OparlReference<OparlPerson>>|null $persons
     * @param list<OparlReference<OparlMeeting>>|null $meetings
     * @param list<OparlReference<OparlPaper>>|null $papers
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?string $description = null,
        private ?array $geojson = null,
        private ?string $streetAddress = null,
        private ?string $room = null,
        private ?string $postalCode = null,
        private ?string $subLocality = null,
        private ?string $locality = null,
        private ?array $bodies = null,
        private ?array $organizations = null,
        private ?array $persons = null,
        private ?array $meetings = null,
        private ?array $papers = null,
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
            description: $reader->string('description'),
            geojson: $reader->json('geojson'),
            streetAddress: $reader->string('streetAddress'),
            room: $reader->string('room'),
            postalCode: $reader->string('postalCode'),
            subLocality: $reader->string('subLocality'),
            locality: $reader->string('locality'),
            bodies: $reader->referenceList('bodies', OparlBody::class),
            organizations: $reader->referenceList('organizations', OparlOrganization::class),
            persons: $reader->referenceList('persons', OparlPerson::class),
            meetings: $reader->referenceList('meetings', OparlMeeting::class),
            papers: $reader->referenceList('papers', OparlPaper::class),
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
     * Textuelle Beschreibung eines Orts, z. B. in Form einer Adresse.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Geodaten-Repräsentation des Orts. Der Wert dieser Eigenschaft **muss** der Spezifikation
     *
     * von GeoJSON entsprechen, d.h. es **muss** ein vollständiges `Feature`-Objekt ausgegeben
     * werden.
     *
     * @return array<mixed>|null
     */
    public function getGeojson(): ?array
    {
        return $this->geojson;
    }

    /**
     * Straße und Hausnummer der Anschrift.
     */
    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    /**
     * Raumangabe der Anschrift
     */
    public function getRoom(): ?string
    {
        return $this->room;
    }

    /**
     * Postleitzahl der Anschrift.
     */
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * Untergeordnete Ortsangabe der Anschrift, z.B. Stadtbezirk, Ortsteil oder Dorf.
     */
    public function getSubLocality(): ?string
    {
        return $this->subLocality;
    }

    /**
     * Ortsangabe der Anschrift.
     */
    public function getLocality(): ?string
    {
        return $this->locality;
    }

    /**
     * Rückreferenzen auf Body-Objekte. Wird nur dann ausgegeben, wenn das Location-Objekt
     *
     * nicht als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlBody>>|null
     */
    public function getBodies(): ?array
    {
        return $this->bodies;
    }

    /**
     * Rückreferenzen auf Organization-Objekte. Wird nur dann ausgegeben,
     *
     * wenn das Location-Objekt nicht als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlOrganization>>|null
     */
    public function getOrganizations(): ?array
    {
        return $this->organizations;
    }

    /**
     * Rückreferenzen auf Person-Objekte. Wird nur dann ausgegeben, wenn das Location-Objekt
     *
     * nicht als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlPerson>>|null
     */
    public function getPersons(): ?array
    {
        return $this->persons;
    }

    /**
     * Rückreferenzen auf Meeting-Objekte. Wird nur dann ausgegeben, wenn das Location-Objekt
     *
     * nicht als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlMeeting>>|null
     */
    public function getMeetings(): ?array
    {
        return $this->meetings;
    }

    /**
     * Rückreferenzen auf Paper-Objekte. Wird nur dann ausgegeben, wenn das Location-Objekt
     *
     * nicht als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlPaper>>|null
     */
    public function getPapers(): ?array
    {
        return $this->papers;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'description' => $this->description,
            'geojson' => $this->geojson,
            'streetAddress' => $this->streetAddress,
            'room' => $this->room,
            'postalCode' => $this->postalCode,
            'subLocality' => $this->subLocality,
            'locality' => $this->locality,
            'bodies' => $this->bodies,
            'organizations' => $this->organizations,
            'persons' => $this->persons,
            'meetings' => $this->meetings,
            'papers' => $this->papers,
        ];
    }
}
