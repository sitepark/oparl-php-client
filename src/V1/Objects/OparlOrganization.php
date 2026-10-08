<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Dieser Objekttyp dient dazu, Gruppierungen von Personen abzubilden, die in der parlamentarischen
 * Arbeit eine Rolle spielen. Dazu zählen in der Praxis insbesondere Fraktionen und Gremien.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-organization
 */
final class OparlOrganization extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlBody>|null $body
     * @param list<OparlReference<OparlMembership>>|null $membership
     * @param OparlReference<OparlList<OparlMeeting>>|null $meeting
     * @param OparlReference<OparlList<OparlConsultation>>|null $consultation
     * @param list<string>|null $post
     * @param OparlReference<OparlOrganization>|null $subOrganizationOf
     * @param OparlReference<OparlBody>|null $externalBody
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?OparlReference $body = null,
        private readonly ?string $name = null,
        private readonly ?array $membership = null,
        private readonly ?OparlReference $meeting = null,
        private readonly ?OparlReference $consultation = null,
        private readonly ?string $shortName = null,
        private readonly ?array $post = null,
        private readonly ?OparlReference $subOrganizationOf = null,
        private readonly ?string $organizationType = null,
        private readonly ?string $classification = null,
        private readonly ?DateTimeImmutable $startDate = null,
        private readonly ?DateTimeImmutable $endDate = null,
        private readonly ?string $website = null,
        private readonly ?OparlLocation $location = null,
        private readonly ?OparlReference $externalBody = null,
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
            membership: $reader->referenceList('membership', OparlMembership::class),
            meeting: $reader->listReference('meeting', OparlMeeting::class),
            consultation: $reader->listReference('consultation', OparlConsultation::class),
            shortName: $reader->string('shortName'),
            post: $reader->stringList('post'),
            subOrganizationOf: $reader->reference('subOrganizationOf', OparlOrganization::class),
            organizationType: $reader->string('organizationType'),
            classification: $reader->string('classification'),
            startDate: $reader->date('startDate'),
            endDate: $reader->date('endDate'),
            website: $reader->url('website'),
            location: $reader->object('location', OparlLocation::class),
            externalBody: $reader->reference('externalBody', OparlBody::class),
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
     * Körperschaft, zu der diese Gruppierung gehört.
     *
     * @return OparlReference<OparlBody>|null
     */
    public function getBody(): ?OparlReference
    {
        return $this->body;
    }

    /**
     * Offizielle (lange) Form des Namens der Gruppierung.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Mitgliedschaften dieser Gruppierung.
     *
     * @return list<OparlReference<OparlMembership>>|null
     */
    public function getMembership(): ?array
    {
        return $this->membership;
    }

    /**
     * URL auf eine externe Objektliste mit den Sitzungen dieser Gruppierung. Invers zur Eigenschaft
     * `organization` der Klasse `oparl:Meeting`
     *
     * @return OparlReference<OparlList<OparlMeeting>>|null
     */
    public function getMeeting(): ?OparlReference
    {
        return $this->meeting;
    }

    /**
     * URL auf eine externe Objektliste mit den Beratungen dieser Gruppierung. Invers zur
     * Eigenschaft `organization` der Klasse `oparl:Consultation`
     *
     * @return OparlReference<OparlList<OparlConsultation>>|null
     */
    public function getConsultation(): ?OparlReference
    {
        return $this->consultation;
    }

    /**
     * Der Name der Gruppierung als Kurzform.
     */
    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    /**
     * Positionen, die für diese Gruppierung vorgesehen sind.
     *
     * @return list<string>|null
     */
    public function getPost(): ?array
    {
        return $this->post;
    }

    /**
     * URL einer eventuellen übergeordneten Gruppierung.
     *
     * @return OparlReference<OparlOrganization>|null
     */
    public function getSubOrganizationOf(): ?OparlReference
    {
        return $this->subOrganizationOf;
    }

    /**
     * Grobe Kategorisierung der Gruppierung. Mögliche Werte sind "Gremium", "Partei", "Fraktion",
     * "Verwaltungsbereich", "externes Gremium", "Institution" und "Sonstiges".
     */
    public function getOrganizationType(): ?string
    {
        return $this->organizationType;
    }

    /**
     * Die Art der Gruppierung. In Frage kommen z.B. "Parlament", "Ausschuss", "Beirat",
     * "Projektbeirat", "Kommission", "AG", "Verwaltungsrat", "Fraktion" oder "Partei". Die Angabe
     * **sollte** möglichst präzise erfolgen. Außerdem **sollten** Abkürzungen vermieden werden. Für
     * die höchste demokratische Instanz in der Kommune **sollte** immer der Begriff "Parlament"
     * verwendet werden, nicht "Rat" oder "Hauptausschuss".
     */
    public function getClassification(): ?string
    {
        return $this->classification;
    }

    /**
     * Gründungsdatum der Gruppierung. Kann z. B. das Datum der konstituierenden Sitzung sein.
     */
    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Datum des letzten Tages der Existenz der Gruppierung.
     */
    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Allgemeine Website der Gruppierung.
     */
    public function getWebsite(): ?string
    {
        return $this->website;
    }

    /**
     * Ort, an dem die Organisation beheimatet ist
     */
    public function getLocation(): ?OparlLocation
    {
        return $this->location;
    }

    /**
     * Externer OParl Body, der dieser Organisation entspricht. Diese Eigenschaft ist dafür gedacht
     * auf eventuelle konkretere OParl-Schnittstellen zu verweisen. Ein Beispiel hierfür wäre eine
     * Stadt, die sowohl ein übergreifendes parlamentarisches Informationssystem, als auch
     * bezirksspezifische Systeme hat.
     *
     * @return OparlReference<OparlBody>|null
     */
    public function getExternalBody(): ?OparlReference
    {
        return $this->externalBody;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'body' => $this->body,
            'name' => $this->name,
            'membership' => $this->membership,
            'meeting' => $this->meeting,
            'consultation' => $this->consultation,
            'shortName' => $this->shortName,
            'post' => $this->post,
            'subOrganizationOf' => $this->subOrganizationOf,
            'organizationType' => $this->organizationType,
            'classification' => $this->classification,
            'startDate' => OparlTime::formatDate($this->startDate),
            'endDate' => OparlTime::formatDate($this->endDate),
            'website' => $this->website,
            'location' => $this->location,
            'externalBody' => $this->externalBody,
        ];
    }
}
