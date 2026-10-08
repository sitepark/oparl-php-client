<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Eine Sitzung ist die Versammlung einer oder mehrerer Gruppierungen (oparl:Organization) zu einem
 * bestimmten Zeitpunkt an einem bestimmten Ort.
 *
 * Die geladenen Teilnehmer der Sitzung sind jeweils als Objekte vom Typ oparl:Person, die in
 * entsprechender Form referenziert werden. Verschiedene Dateien (Einladung, Ergebnis- und
 * Wortprotokoll, sonstige Anlagen) können referenziert werden.
 *
 * Die Inhalte einer Sitzung werden durch Tagesordnungspunkte (oparl:AgendaItem) abgebildet.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-meeting
 */
final class OparlMeeting extends OparlObjectV1
{
    /**
     * @param list<OparlReference<OparlOrganization>>|null $organization
     * @param list<OparlReference<OparlPerson>>|null $participant
     * @param list<OparlFile>|null $auxiliaryFile
     * @param list<OparlAgendaItem>|null $agendaItem
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?string $name = null,
        private readonly ?string $meetingState = null,
        private readonly ?bool $cancelled = null,
        private readonly ?DateTimeImmutable $start = null,
        private readonly ?DateTimeImmutable $end = null,
        private readonly ?OparlLocation $location = null,
        private readonly ?array $organization = null,
        private readonly ?array $participant = null,
        private readonly ?OparlFile $invitation = null,
        private readonly ?OparlFile $resultsProtocol = null,
        private readonly ?OparlFile $verbatimProtocol = null,
        private readonly ?array $auxiliaryFile = null,
        private readonly ?array $agendaItem = null,
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
            name: $reader->string('name'),
            meetingState: $reader->string('meetingState'),
            cancelled: $reader->bool('cancelled'),
            start: $reader->dateTime('start'),
            end: $reader->dateTime('end'),
            location: $reader->object('location', OparlLocation::class),
            organization: $reader->referenceList('organization', OparlOrganization::class),
            participant: $reader->referenceList('participant', OparlPerson::class),
            invitation: $reader->object('invitation', OparlFile::class),
            resultsProtocol: $reader->object('resultsProtocol', OparlFile::class),
            verbatimProtocol: $reader->object('verbatimProtocol', OparlFile::class),
            auxiliaryFile: $reader->objectList('auxiliaryFile', OparlFile::class),
            agendaItem: $reader->objectList('agendaItem', OparlAgendaItem::class),
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
     * Name der Sitzung.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Aktueller Status der Sitzung. **Empfohlen** ist die Verwendung von `terminiert` (geplant),
     * `eingeladen` (vor der Sitzung bis zur Freigabe des Protokolls) und `durchgeführt` (nach
     * Freigabe des Protokolls).
     */
    public function getMeetingState(): ?string
    {
        return $this->meetingState;
    }

    /**
     * Wenn die Sitzung ausfällt, wird cancelled auf true gesetzt.
     */
    public function getCancelled(): ?bool
    {
        return $this->cancelled;
    }

    /**
     * Datum und Uhrzeit des Anfangszeitpunkts der Sitzung. Bei einer zukünftigen Sitzung ist dies
     * der geplante Zeitpunkt, bei einer stattgefundenen **kann** es der tatsächliche Startzeitpunkt
     * sein.
     */
    public function getStart(): ?DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Endzeitpunkt der Sitzung als Datum/Uhrzeit. Bei einer zukünftigen Sitzung ist dies der
     * geplante Zeitpunkt, bei einer stattgefundenen **kann** es der tatsächliche Endzeitpunkt sein.
     */
    public function getEnd(): ?DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Sitzungsort.
     */
    public function getLocation(): ?OparlLocation
    {
        return $this->location;
    }

    /**
     * Gruppierungen, denen die Sitzung zugeordnet ist. Im Regelfall wird hier eine Gruppierung
     * verknüpft sein, es kann jedoch auch gemeinsame Sitzungen mehrerer Gruppierungen geben. Das
     * erste Element **sollte** dann das federführende Gremium sein.
     *
     * @return list<OparlReference<OparlOrganization>>|null
     */
    public function getOrganization(): ?array
    {
        return $this->organization;
    }

    /**
     * Personen, die an der Sitzung teilgenommen haben (d.h. nicht nur die eingeladenen Personen,
     * sondern die tatsächlich anwesenden). Diese Eigenschaft kann selbstverständlich erst nach dem
     * Stattfinden der Sitzung vorkommen.
     *
     * @return list<OparlReference<OparlPerson>>|null
     */
    public function getParticipant(): ?array
    {
        return $this->participant;
    }

    /**
     * Einladungsdokument zur Sitzung.
     */
    public function getInvitation(): ?OparlFile
    {
        return $this->invitation;
    }

    /**
     * Ergebnisprotokoll zur Sitzung. Diese Eigenschaft kann selbstverständlich erst nachdem
     * Stattfinden der Sitzung vorkommen.
     */
    public function getResultsProtocol(): ?OparlFile
    {
        return $this->resultsProtocol;
    }

    /**
     * Wortprotokoll zur Sitzung. Diese Eigenschaft kann selbstverständlich erst nach dem
     * Stattfinden der Sitzung vorkommen.
     */
    public function getVerbatimProtocol(): ?OparlFile
    {
        return $this->verbatimProtocol;
    }

    /**
     * Dateianhang zur Sitzung. Hiermit sind Dateien gemeint, die üblicherweise mit der Einladung zu
     * einer Sitzung verteilt werden, und die nicht bereits über einzelne Tagesordnungspunkte
     * referenziert sind.
     *
     * @return list<OparlFile>|null
     */
    public function getAuxiliaryFile(): ?array
    {
        return $this->auxiliaryFile;
    }

    /**
     * Tagesordnungspunkte der Sitzung. Die Reihenfolge ist relevant. Es kann Sitzungen ohne TOPs
     * geben.
     *
     * @return list<OparlAgendaItem>|null
     */
    public function getAgendaItem(): ?array
    {
        return $this->agendaItem;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'name' => $this->name,
            'meetingState' => $this->meetingState,
            'cancelled' => $this->cancelled,
            'start' => OparlTime::formatDateTime($this->start),
            'end' => OparlTime::formatDateTime($this->end),
            'location' => $this->location,
            'organization' => $this->organization,
            'participant' => $this->participant,
            'invitation' => $this->invitation,
            'resultsProtocol' => $this->resultsProtocol,
            'verbatimProtocol' => $this->verbatimProtocol,
            'auxiliaryFile' => $this->auxiliaryFile,
            'agendaItem' => $this->agendaItem,
        ];
    }
}
