<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Der Objekttyp `oparl:Consultation` dient dazu, die Beratung einer Drucksache
 * ([`oparl:Paper`](#oparl_paper)) in einer Sitzung abzubilden. Dabei ist es nicht entscheidend, ob
 * diese Beratung in der Vergangenheit stattgefunden hat oder diese für die Zukunft geplant ist.
 *
 * Die Gesamtheit aller Objekte des Typs `oparl:Consultation` zu einer bestimmten Drucksache bildet
 * das ab, was in der Praxis als "Beratungsfolge" der Drucksache bezeichnet wird.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-consultation
 */
final class OparlConsultation extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlPaper>|null $paper
     * @param OparlReference<OparlAgendaItem>|null $agendaItem
     * @param OparlReference<OparlMeeting>|null $meeting
     * @param list<OparlReference<OparlOrganization>>|null $organization
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?OparlReference $paper = null,
        private readonly ?OparlReference $agendaItem = null,
        private readonly ?OparlReference $meeting = null,
        private readonly ?array $organization = null,
        private readonly ?bool $authoritative = null,
        private readonly ?string $role = null,
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
            paper: $reader->reference('paper', OparlPaper::class),
            agendaItem: $reader->reference('agendaItem', OparlAgendaItem::class),
            meeting: $reader->reference('meeting', OparlMeeting::class),
            organization: $reader->referenceList('organization', OparlOrganization::class),
            authoritative: $reader->bool('authoritative'),
            role: $reader->string('role'),
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
     * Referenz auf das Paper, welche nur dann ausgegeben werden muss, wenn das Consultation-Objekt
     * einzeln abgerufen wird, d.h. nicht Teil einer internen Ausgabe ist.
     *
     * @return OparlReference<OparlPaper>|null
     */
    public function getPaper(): ?OparlReference
    {
        return $this->paper;
    }

    /**
     * Referenz auf den Tagesordnungspunkt, unter dem die Drucksache beraten wird, welcher nur dann
     * ausgegeben werden muss, wenn das Consultation-Objekt einzeln abgerufen wird, d.h. nicht Teil
     * einer internen Ausgabe ist.
     *
     * @return OparlReference<OparlAgendaItem>|null
     */
    public function getAgendaItem(): ?OparlReference
    {
        return $this->agendaItem;
    }

    /**
     * Referenz auf die Sitzung, in der die Drucksache beraten wird oder wurde, welche nur dann
     * ausgegeben werden muss, wenn das Consultation-Objekt einzeln abgerufen wird, d.h. nicht Teil
     * einer internen Ausgabe ist.
     *
     * @return OparlReference<OparlMeeting>|null
     */
    public function getMeeting(): ?OparlReference
    {
        return $this->meeting;
    }

    /**
     * Gremium, in dem die Drucksache beraten wird. Hier kann auch eine mit Liste von Gremien
     * angegeben werden (die verschiedenen `oparl:Body` und `oparl:System` angehören können). Die
     * Liste ist dann geordnet. Das erste Gremium der Liste ist federführend.
     *
     * @return list<OparlReference<OparlOrganization>>|null
     */
    public function getOrganization(): ?array
    {
        return $this->organization;
    }

    /**
     * Drückt aus, ob bei dieser Beratung ein Beschluss zu der Drucksache gefasst wird oder wurde
     * (`true`) oder nicht (`false`).
     */
    public function getAuthoritative(): ?bool
    {
        return $this->authoritative;
    }

    /**
     * Rolle oder Funktion der Beratung. Zum Beispiel Anhörung, Entscheidung, Kenntnisnahme,
     * Vorberatung usw.
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'paper' => $this->paper,
            'agendaItem' => $this->agendaItem,
            'meeting' => $this->meeting,
            'organization' => $this->organization,
            'authoritative' => $this->authoritative,
            'role' => $this->role,
        ];
    }
}
