<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Tagesordnungspunkte sind die Bestandteile von Sitzungen (`oparl:Meeting`).
 *
 * Jeder Tagesordnungspunkt widmet sich inhaltlich einem bestimmten Thema, wozu
 *
 * in der Regel auch die Beratung bestimmter Drucksachen gehört.
 *
 * Die Beziehung zwischen einem Tagesordnungspunkt und einer Drucksache wird
 *
 * über ein Objekt vom Typ `oparl:Consultation` hergestellt, das über die
 *
 * Eigenschaft `consultation` referenziert werden kann.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-agendaitem
 */
final readonly class OparlAgendaItem extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlMeeting>|null $meeting
     * @param OparlReference<OparlConsultation>|null $consultation
     * @param list<OparlFile>|null $auxiliaryFile
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?OparlReference $meeting = null,
        private ?string $number = null,
        private ?int $order = null,
        private ?string $name = null,
        private ?bool $public = null,
        private ?OparlReference $consultation = null,
        private ?string $result = null,
        private ?string $resolutionText = null,
        private ?OparlFile $resolutionFile = null,
        private ?array $auxiliaryFile = null,
        private ?DateTimeImmutable $start = null,
        private ?DateTimeImmutable $end = null,
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
            meeting: $reader->reference('meeting', OparlMeeting::class),
            number: $reader->string('number'),
            order: $reader->int('order'),
            name: $reader->string('name'),
            public: $reader->bool('public'),
            consultation: $reader->reference('consultation', OparlConsultation::class),
            result: $reader->string('result'),
            resolutionText: $reader->string('resolutionText'),
            resolutionFile: $reader->object('resolutionFile', OparlFile::class),
            auxiliaryFile: $reader->objectList('auxiliaryFile', OparlFile::class),
            start: $reader->dateTime('start'),
            end: $reader->dateTime('end'),
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
     * Rückreferenz auf das Meeting, welches nur dann ausgegeben werden muss,
     *
     * wenn das agendaItem-Objekt einzeln abgerufen wird, d.h. nicht Teil einer
     *
     * internen Ausgabe ist.
     *
     * @return OparlReference<OparlMeeting>|null
     */
    public function getMeeting(): ?OparlReference
    {
        return $this->meeting;
    }

    /**
     * Gliederungs-"Nummer" des Tagesordnungspunktes. Eine beliebige Zeichenkette,
     *
     * wie z. B. "10.", "10.1", "C", "c)" o. ä. Die Reihenfolge wird nicht dadurch,
     *
     * sondern durch die Reihenfolge der TOPs im `agendaItem`-Attribut von `oparl:Meeting`
     *
     * festgelegt, **sollte** allerdings zu dieser identisch sein.
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * Neu in OParl 1.1: Die Position des Tagesordnungspunkts in der Sitzung,
     *
     * wenn alle Tagesordnungspunkte von 0 an durchgehend numeriert werden.
     *
     * Diese Nummer entspricht der Position in `Meeting:agendaItem`
     */
    public function getOrder(): ?int
    {
        return $this->order;
    }

    /**
     * Das Thema des Tagesordnungspunktes.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Kennzeichnet, ob der Tagesordnungspunkt zur Behandlung in öffentlicher Sitzung
     *
     * vorgesehen ist/war. Es wird ein Wahrheitswert (`true` oder `false`) erwartet.
     */
    public function getPublic(): ?bool
    {
        return $this->public;
    }

    /**
     * Beratung, die diesem Tagesordnungspunkt zugewiesen ist.
     *
     * @return OparlReference<OparlConsultation>|null
     */
    public function getConsultation(): ?OparlReference
    {
        return $this->consultation;
    }

    /**
     * Kategorische Information darüber, welches Ergebnis die Beratung des
     *
     * Tagesordnungspunktes erbracht hat, in der Bedeutung etwa "Unverändert
     *
     * beschlossen" oder "Geändert beschlossen".
     */
    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * Falls in diesem Tagesordnungspunkt ein Beschluss gefasst wurde, kann hier ein
     *
     * Text angegeben werden. Das ist besonders dann in der Praxis relevant, wenn der
     *
     * gefasste Beschluss (z. B. durch Änderungsantrag) von der Beschlussvorlage abweicht.
     */
    public function getResolutionText(): ?string
    {
        return $this->resolutionText;
    }

    /**
     * Falls in diesem Tagesordnungspunkt ein Beschluss gefasst wurde, kann hier eine
     *
     * Datei angegeben werden. Das ist besonders dann in der Praxis relevant, wenn der
     *
     * gefasste Beschluss (z. B. durch Änderungsantrag) von der Beschlussvorlage abweicht.
     */
    public function getResolutionFile(): ?OparlFile
    {
        return $this->resolutionFile;
    }

    /**
     * Weitere Dateianhänge zum Tagesordnungspunkt.
     *
     * @return list<OparlFile>|null
     */
    public function getAuxiliaryFile(): ?array
    {
        return $this->auxiliaryFile;
    }

    /**
     * Datum und Uhrzeit des Anfangszeitpunkts des Tagesordnungspunktes. Bei zukünftigen
     *
     * Tagesordnungspunkten ist dies der geplante Zeitpunkt, bei einem stattgefundenen **kann**
     *
     * es der tatsächliche Startzeitpunkt sein.
     */
    public function getStart(): ?DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Endzeitpunkt des Tagesordnungspunktes als Datum/Uhrzeit. Bei zukünftigen
     *
     * Tagesordnungspunkten ist dies der geplante Zeitpunkt, bei einer
     *
     * stattgefundenen **kann** es der tatsächliche Endzeitpunkt sein.
     */
    public function getEnd(): ?DateTimeImmutable
    {
        return $this->end;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'meeting' => $this->meeting,
            'number' => $this->number,
            'order' => $this->order,
            'name' => $this->name,
            'public' => $this->public,
            'consultation' => $this->consultation,
            'result' => $this->result,
            'resolutionText' => $this->resolutionText,
            'resolutionFile' => $this->resolutionFile,
            'auxiliaryFile' => $this->auxiliaryFile,
            'start' => OparlTime::formatDateTime($this->start),
            'end' => OparlTime::formatDateTime($this->end),
        ];
    }
}
