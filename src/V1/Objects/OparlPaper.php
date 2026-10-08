<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Dieser Objekttyp dient der Abbildung von Drucksachen in der parlamentarischen Arbeit, wie zum
 * Beispiel Anfragen, Anträgen und Beschlussvorlagen.
 *
 * Drucksachen werden in Form einer Beratung (oparl:Consultation) im Rahmen eines
 * Tagesordnungspunkts (oparl:AgendaItem) einer Sitzung (oparl:Meeting) behandelt.
 *
 * Drucksachen spielen in der schriftlichen wie mündlichen Kommunikation eine besondere Rolle, da in
 * vielen Texten auf bestimmte Drucksachen Bezug genommen wird. Hierbei kommen in parlamentarischen
 * Informationssystemen in der Regel unveränderliche Kennungen der Drucksachen zum Einsatz.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-paper
 */
final readonly class OparlPaper extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlBody>|null $body
     * @param list<OparlReference<OparlPaper>>|null $relatedPaper
     * @param list<OparlReference<OparlPaper>>|null $superordinatedPaper
     * @param list<OparlReference<OparlPaper>>|null $subordinatedPaper
     * @param list<OparlFile>|null $auxiliaryFile
     * @param list<OparlLocation>|null $location
     * @param list<OparlReference<OparlPerson>>|null $originatorPerson
     * @param list<OparlReference<OparlOrganization>>|null $underDirectionOf
     * @param list<OparlReference<OparlOrganization>>|null $originatorOrganization
     * @param list<OparlConsultation>|null $consultation
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?OparlReference $body = null,
        private ?string $name = null,
        private ?string $reference = null,
        private ?DateTimeImmutable $date = null,
        private ?string $paperType = null,
        private ?array $relatedPaper = null,
        private ?array $superordinatedPaper = null,
        private ?array $subordinatedPaper = null,
        private ?OparlFile $mainFile = null,
        private ?array $auxiliaryFile = null,
        private ?array $location = null,
        private ?array $originatorPerson = null,
        private ?array $underDirectionOf = null,
        private ?array $originatorOrganization = null,
        private ?array $consultation = null,
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
            reference: $reader->string('reference'),
            date: $reader->date('date'),
            paperType: $reader->string('paperType'),
            relatedPaper: $reader->referenceList('relatedPaper', OparlPaper::class),
            superordinatedPaper: $reader->referenceList('superordinatedPaper', OparlPaper::class),
            subordinatedPaper: $reader->referenceList('subordinatedPaper', OparlPaper::class),
            mainFile: $reader->object('mainFile', OparlFile::class),
            auxiliaryFile: $reader->objectList('auxiliaryFile', OparlFile::class),
            location: $reader->objectList('location', OparlLocation::class),
            originatorPerson: $reader->referenceList('originatorPerson', OparlPerson::class),
            underDirectionOf: $reader->referenceList('underDirectionOf', OparlOrganization::class),
            originatorOrganization: $reader->referenceList('originatorOrganization', OparlOrganization::class),
            consultation: $reader->objectList('consultation', OparlConsultation::class),
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
     * Körperschaft, zu der die Drucksache gehört.
     *
     * @return OparlReference<OparlBody>|null
     */
    public function getBody(): ?OparlReference
    {
        return $this->body;
    }

    /**
     * Titel der Drucksache.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Kennung bzw. Aktenzeichen der Drucksache, mit der sie in der parlamentarischen Arbeit
     * eindeutig referenziert werden kann.
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * Datum, welches als Startpunkt für Fristen u.ä. verwendet ist.
     */
    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Art der Drucksache, z. B. Beantwortung einer Anfrage.
     */
    public function getPaperType(): ?string
    {
        return $this->paperType;
    }

    /**
     * Inhaltlich verwandte Drucksachen.
     *
     * @return list<OparlReference<OparlPaper>>|null
     */
    public function getRelatedPaper(): ?array
    {
        return $this->relatedPaper;
    }

    /**
     * Übergeordnete Drucksachen.
     *
     * @return list<OparlReference<OparlPaper>>|null
     */
    public function getSuperordinatedPaper(): ?array
    {
        return $this->superordinatedPaper;
    }

    /**
     * Untergeordnete Drucksachen.
     *
     * @return list<OparlReference<OparlPaper>>|null
     */
    public function getSubordinatedPaper(): ?array
    {
        return $this->subordinatedPaper;
    }

    /**
     * Die Hauptdatei zu dieser Drucksache. Beispiel: Die Drucksache repräsentiert eine
     * Beschlussvorlage und die Hauptdatei enthält den Text der Beschlussvorlage. Sollte keine
     * eindeutige Hauptdatei vorhanden sein, wird diese Eigenschaft nicht ausgegeben.
     */
    public function getMainFile(): ?OparlFile
    {
        return $this->mainFile;
    }

    /**
     * Alle weiteren Dateien zur Drucksache ausgenommen der gegebenenfalls in `mainFile`
     * angegebenen.
     *
     * @return list<OparlFile>|null
     */
    public function getAuxiliaryFile(): ?array
    {
        return $this->auxiliaryFile;
    }

    /**
     * Sofern die Drucksache einen inhaltlichen Ortsbezug hat, beschreibt diese Eigenschaft den Ort
     * in Textform und/oder in Form von Geodaten.
     *
     * @return list<OparlLocation>|null
     */
    public function getLocation(): ?array
    {
        return $this->location;
    }

    /**
     * Urheber der Drucksache, falls der Urheber eine Person ist. Es können auch mehrere Personen
     * angegeben werden.
     *
     * @return list<OparlReference<OparlPerson>>|null
     */
    public function getOriginatorPerson(): ?array
    {
        return $this->originatorPerson;
    }

    /**
     * Federführung. Amt oder Abteilung, für die Inhalte oder Beantwortung der Drucksache
     * verantwortlich.
     *
     * @return list<OparlReference<OparlOrganization>>|null
     */
    public function getUnderDirectionOf(): ?array
    {
        return $this->underDirectionOf;
    }

    /**
     * Urheber der Drucksache, falls der Urheber eine Gruppierung ist. Es können auch mehrere
     * Gruppierungen angegeben werden.
     *
     * @return list<OparlReference<OparlOrganization>>|null
     */
    public function getOriginatorOrganization(): ?array
    {
        return $this->originatorOrganization;
    }

    /**
     * Beratungen der Drucksache.
     *
     * @return list<OparlConsultation>|null
     */
    public function getConsultation(): ?array
    {
        return $this->consultation;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'body' => $this->body,
            'name' => $this->name,
            'reference' => $this->reference,
            'date' => OparlTime::formatDate($this->date),
            'paperType' => $this->paperType,
            'relatedPaper' => $this->relatedPaper,
            'superordinatedPaper' => $this->superordinatedPaper,
            'subordinatedPaper' => $this->subordinatedPaper,
            'mainFile' => $this->mainFile,
            'auxiliaryFile' => $this->auxiliaryFile,
            'location' => $this->location,
            'originatorPerson' => $this->originatorPerson,
            'underDirectionOf' => $this->underDirectionOf,
            'originatorOrganization' => $this->originatorOrganization,
            'consultation' => $this->consultation,
        ];
    }
}
