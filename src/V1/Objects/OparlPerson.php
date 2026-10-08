<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Jede natürliche Person, die in der parlamentarischen Arbeit tätig und insbesondere
 *
 * Mitglied in einer Gruppierung ([oparl:Organization](#oparl_organization)) ist, wird
 *
 * mit einem Objekt vom Typ `oparl:Person` abgebildet.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-person
 */
final readonly class OparlPerson extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlBody>|null $body
     * @param list<string>|null $title
     * @param list<string>|null $phone
     * @param list<string>|null $email
     * @param OparlReference<OparlLocation>|null $location
     * @param list<string>|null $status
     * @param list<OparlMembership>|null $membership
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?OparlReference $body = null,
        private ?string $name = null,
        private ?string $familyName = null,
        private ?string $givenName = null,
        private ?string $formOfAddress = null,
        private ?string $affix = null,
        private ?array $title = null,
        private ?string $gender = null,
        private ?array $phone = null,
        private ?array $email = null,
        private ?OparlReference $location = null,
        private ?OparlLocation $locationObject = null,
        private ?array $status = null,
        private ?array $membership = null,
        private ?string $life = null,
        private ?string $lifeSource = null,
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
            familyName: $reader->string('familyName'),
            givenName: $reader->string('givenName'),
            formOfAddress: $reader->string('formOfAddress'),
            affix: $reader->string('affix'),
            title: $reader->stringList('title'),
            gender: $reader->string('gender'),
            phone: $reader->stringList('phone'),
            email: $reader->stringList('email'),
            location: $reader->reference('location', OparlLocation::class),
            locationObject: $reader->object('locationObject', OparlLocation::class),
            status: $reader->stringList('status'),
            membership: $reader->objectList('membership', OparlMembership::class),
            life: $reader->string('life'),
            lifeSource: $reader->string('lifeSource'),
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
     * Körperschaft, zu der die Person gehört.
     *
     * @return OparlReference<OparlBody>|null
     */
    public function getBody(): ?OparlReference
    {
        return $this->body;
    }

    /**
     * Der vollständige Name der Person mit akademischem Grad und dem gebräuchlichen Vornamen,
     *
     * wie er zur Anzeige durch den Client genutzt werden kann.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Familienname bzw. Nachname.
     */
    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    /**
     * Vorname bzw. Taufname.
     */
    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    /**
     * Anrede.
     */
    public function getFormOfAddress(): ?string
    {
        return $this->formOfAddress;
    }

    /**
     * Namenszusatz (z.B. `jun.` oder `MdL.`)
     */
    public function getAffix(): ?string
    {
        return $this->affix;
    }

    /**
     * Akademische Titel
     *
     * @return list<string>|null
     */
    public function getTitle(): ?array
    {
        return $this->title;
    }

    /**
     * Geschlecht. Vorgegebene Werte sind `female` und `male`, weitere werden durch die durchgehend
     * klein geschriebene englische Bezeichnung angegeben. Für den Fall, dass
     *
     * das Geschlecht der Person unbekannt ist, **sollte** die Eigenschaft nicht ausgegeben werden.
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * Telefonnummern der Person.
     *
     * @return list<string>|null
     */
    public function getPhone(): ?array
    {
        return $this->phone;
    }

    /**
     * E-Mail-Adressen der Person.
     *
     * @return list<string>|null
     */
    public function getEmail(): ?array
    {
        return $this->email;
    }

    /**
     * Referenz der Kontakt-Anschrift der Person.
     *
     * @return OparlReference<OparlLocation>|null
     */
    public function getLocation(): ?OparlReference
    {
        return $this->location;
    }

    /**
     * Kontakt-Anschrift der Person. Wenn diese Eigenschaft ausgegeben wird, dann **muss**
     *
     * auch die Eigenschaft `location` ausgegeben werden und auf das gleiche Location-Objekt
     * verweisen.
     *
     * Dieses Feld sollte die eigentliche Ausgabeform von `location` in OParl 1.0 werden.
     *
     * vgl. https://github.com/OParl/spec/issues/373. Neu in OParl 1.1
     */
    public function getLocationObject(): ?OparlLocation
    {
        return $this->locationObject;
    }

    /**
     * Status, d.h. Rollen in der Kommune.
     *
     * @return list<string>|null
     */
    public function getStatus(): ?array
    {
        return $this->status;
    }

    /**
     * Mitgliedschaften der Person in Gruppierungen, z. B. Gremien und Fraktionen. Es **sollen**
     *
     * sowohl aktuelle als auch vergangene Mitgliedschaften angegeben werden
     *
     * @return list<OparlMembership>|null
     */
    public function getMembership(): ?array
    {
        return $this->membership;
    }

    /**
     * Kurzer Informationstext zur Person. Eine Länge von weniger als 300 Zeichen ist **empfohlen**
     */
    public function getLife(): ?string
    {
        return $this->life;
    }

    /**
     * Angabe der Quelle, aus der die Informationen für `life` stammen. Bei Angabe von `life` ist
     * diese Eigenschaft **empfohlen**
     */
    public function getLifeSource(): ?string
    {
        return $this->lifeSource;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'body' => $this->body,
            'name' => $this->name,
            'familyName' => $this->familyName,
            'givenName' => $this->givenName,
            'formOfAddress' => $this->formOfAddress,
            'affix' => $this->affix,
            'title' => $this->title,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'location' => $this->location,
            'locationObject' => $this->locationObject,
            'status' => $this->status,
            'membership' => $this->membership,
            'life' => $this->life,
            'lifeSource' => $this->lifeSource,
        ];
    }
}
