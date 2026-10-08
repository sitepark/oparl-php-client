<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Über Objekte dieses Typs wird die Mitgliedschaft von Personen in Gruppierungen dargestellt. Diese
 * Mitgliedschaften können zeitlich begrenzt sein. Zudem kann abgebildet werden, dass eine Person
 * eine bestimmte Rolle bzw. Position innerhalb der Gruppierung innehat, beispielsweise den Vorsitz
 * einer Fraktion.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-membership
 */
final readonly class OparlMembership extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlPerson>|null $person
     * @param OparlReference<OparlOrganization>|null $organization
     * @param OparlReference<OparlOrganization>|null $onBehalfOf
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?OparlReference $person = null,
        private ?OparlReference $organization = null,
        private ?string $role = null,
        private ?bool $votingRight = null,
        private ?DateTimeImmutable $startDate = null,
        private ?DateTimeImmutable $endDate = null,
        private ?OparlReference $onBehalfOf = null,
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
            person: $reader->reference('person', OparlPerson::class),
            organization: $reader->reference('organization', OparlOrganization::class),
            role: $reader->string('role'),
            votingRight: $reader->bool('votingRight'),
            startDate: $reader->date('startDate'),
            endDate: $reader->date('endDate'),
            onBehalfOf: $reader->reference('onBehalfOf', OparlOrganization::class),
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
     * Rückreferenz auf Person, welches nur dann ausgegeben werden muss, wenn das Membership-Objekt
     * einzeln abgerufen wird, d.h. nicht Teil einer internen Ausgabe ist.
     *
     * @return OparlReference<OparlPerson>|null
     */
    public function getPerson(): ?OparlReference
    {
        return $this->person;
    }

    /**
     * Die Gruppierung, in der die Person Mitglied ist oder war.
     *
     * @return OparlReference<OparlOrganization>|null
     */
    public function getOrganization(): ?OparlReference
    {
        return $this->organization;
    }

    /**
     * Rolle der Person für die Gruppierung. Kann genutzt werden, um verschiedene Arten von
     * Mitgliedschaften zum Beispiel in Gremien zu unterscheiden.
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Gibt an, ob die Person in der Gruppierung stimmberechtigtes Mitglied ist.
     */
    public function getVotingRight(): ?bool
    {
        return $this->votingRight;
    }

    /**
     * Datum, an dem die Mitgliedschaft beginnt.
     */
    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Datum, an dem die Mitgliedschaft endet.
     */
    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Die Gruppierung, für die die Person in der unter `organization` angegebenen Organisation
     * sitzt. Beispiel: Mitgliedschaft als Vertreter einer Ratsfraktion, einer Gruppierung oder
     * einer externen Organisation.
     *
     * @return OparlReference<OparlOrganization>|null
     */
    public function getOnBehalfOf(): ?OparlReference
    {
        return $this->onBehalfOf;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'person' => $this->person,
            'organization' => $this->organization,
            'role' => $this->role,
            'votingRight' => $this->votingRight,
            'startDate' => OparlTime::formatDate($this->startDate),
            'endDate' => OparlTime::formatDate($this->endDate),
            'onBehalfOf' => $this->onBehalfOf,
        ];
    }
}
