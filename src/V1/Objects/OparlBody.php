<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Der Objekttyp oparl:Body dient dazu, eine Körperschaft zu repräsentieren. Eine Körperschaft ist
 * in den meisten Fällen eine Gemeinde, eine Stadt oder ein Landkreis.
 *
 * In der Regel sind auf einem OParl-Server Daten von genau einer Körperschaft gespeichert und es
 * wird daher auch nur ein Body-Objekt ausgegeben. Sind auf dem Server jedoch Daten von mehreren
 * Körperschaften gespeichert, **muss** für jede Körperschaft ein eigenes Body-Objekt ausgegeben
 * werden.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-body
 */
final class OparlBody extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlSystem>|null $system
     * @param list<string>|null $equivalent
     * @param OparlReference<OparlList<OparlOrganization>>|null $organization
     * @param OparlReference<OparlList<OparlPerson>>|null $person
     * @param OparlReference<OparlList<OparlMeeting>>|null $meeting
     * @param OparlReference<OparlList<OparlPaper>>|null $paper
     * @param list<OparlLegislativeTerm>|null $legislativeTerm
     * @param OparlReference<OparlList<OparlAgendaItem>>|null $agendaItem
     * @param OparlReference<OparlList<OparlConsultation>>|null $consultation
     * @param OparlReference<OparlList<OparlFile>>|null $file
     * @param OparlReference<OparlList<OparlLocation>>|null $locationList
     * @param OparlReference<OparlList<OparlLegislativeTerm>>|null $legislativeTermList
     * @param OparlReference<OparlList<OparlMembership>>|null $membership
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?OparlReference $system = null,
        private readonly ?string $shortName = null,
        private readonly ?string $name = null,
        private readonly ?string $website = null,
        private readonly ?DateTimeImmutable $licenseValidSince = null,
        private readonly ?DateTimeImmutable $oparlSince = null,
        private readonly ?string $ags = null,
        private readonly ?string $rgs = null,
        private readonly ?array $equivalent = null,
        private readonly ?string $contactEmail = null,
        private readonly ?string $contactName = null,
        private readonly ?OparlReference $organization = null,
        private readonly ?OparlReference $person = null,
        private readonly ?OparlReference $meeting = null,
        private readonly ?OparlReference $paper = null,
        private readonly ?array $legislativeTerm = null,
        private readonly ?OparlReference $agendaItem = null,
        private readonly ?OparlReference $consultation = null,
        private readonly ?OparlReference $file = null,
        private readonly ?OparlReference $locationList = null,
        private readonly ?OparlReference $legislativeTermList = null,
        private readonly ?OparlReference $membership = null,
        private readonly ?string $classification = null,
        private readonly ?OparlLocation $location = null,
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
            system: $reader->reference('system', OparlSystem::class),
            shortName: $reader->string('shortName'),
            name: $reader->string('name'),
            website: $reader->url('website'),
            licenseValidSince: $reader->dateTime('licenseValidSince'),
            oparlSince: $reader->dateTime('oparlSince'),
            ags: $reader->string('ags'),
            rgs: $reader->string('rgs'),
            equivalent: $reader->urlList('equivalent'),
            contactEmail: $reader->string('contactEmail'),
            contactName: $reader->string('contactName'),
            organization: $reader->listReference('organization', OparlOrganization::class),
            person: $reader->listReference('person', OparlPerson::class),
            meeting: $reader->listReference('meeting', OparlMeeting::class),
            paper: $reader->listReference('paper', OparlPaper::class),
            legislativeTerm: $reader->objectList('legislativeTerm', OparlLegislativeTerm::class),
            agendaItem: $reader->listReference('agendaItem', OparlAgendaItem::class),
            consultation: $reader->listReference('consultation', OparlConsultation::class),
            file: $reader->listReference('file', OparlFile::class),
            locationList: $reader->listReference('locationList', OparlLocation::class),
            legislativeTermList: $reader->listReference('legislativeTermList', OparlLegislativeTerm::class),
            membership: $reader->listReference('membership', OparlMembership::class),
            classification: $reader->string('classification'),
            location: $reader->object('location', OparlLocation::class),
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
     * System, zu dem dieses Objekt gehört.
     *
     * @return OparlReference<OparlSystem>|null
     */
    public function getSystem(): ?OparlReference
    {
        return $this->system;
    }

    /**
     * Kurzer Name der Körperschaft.
     */
    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    /**
     * Der offizielle lange Name der Körperschaft.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Allgemeine Website der Körperschaft.
     */
    public function getWebsite(): ?string
    {
        return $this->website;
    }

    /**
     * Zeitpunkt, seit dem die unter `license` angegebene Lizenz gilt. _Vorsicht bei Änderungen der
     * Lizenz die zu restriktiveren Bedingungen führen!_
     */
    public function getLicenseValidSince(): ?DateTimeImmutable
    {
        return $this->licenseValidSince;
    }

    /**
     * Zeitpunkt, ab dem OParl für dieses Body bereitgestellt wurde. Dies hilft, um die
     * Datenqualität einzuschätzen, denn erst ab der Einrichtung für OParl kann sichergestellt
     * werden, dass sämtliche Werte korrekt in der Original-Quelle vorliegen.
     */
    public function getOparlSince(): ?DateTimeImmutable
    {
        return $this->oparlSince;
    }

    /**
     * Der achtstellige Amtliche Gemeindeschlüssel^[Amtliche Gemeindeschlüssel können im
     * [Gemeindeverzeichnis (GV-ISys) des Statistischen
     * Bundesamtes](https://www.destatis.de/DE/ZahlenFakten/LaenderRegionen/Regionales/Gemeindeverzeichnis/Gemeindeverzeichnis.html)
     * eingesehen werden].
     */
    public function getAgs(): ?string
    {
        return $this->ags;
    }

    /**
     * Der zwölfstellige Regionalschlüssel.
     */
    public function getRgs(): ?string
    {
        return $this->rgs;
    }

    /**
     * Dient der Angabe zusätzlicher URLs, die dieselbe Körperschaft repräsentieren. Hier können
     * beispielsweise der entsprechende Eintrag der gemeinsamen Normdatei der Deutschen
     * Nationalbibliothek^[Gemeinsame Normdatei <http://www.dnb.de/gnd>], der DBPedia^[DBPedia
     * <http://www.dbpedia.org/>] oder der Wikipedia^[Wikipedia <http://de.wikipedia.org/>]
     * angegeben werden. Body- oder System-Objekte mit anderen OParl-Versionen **dürfen nicht** Teil
     * der Liste sein.
     *
     * @return list<string>|null
     */
    public function getEquivalent(): ?array
    {
        return $this->equivalent;
    }

    /**
     * Dient der Angabe einer Kontakt-E-Mail-Adresse. Die Adresse soll die Kontaktaufnahme zu einer
     * für die Körperschaft und idealerweise das parlamentarische Informationssystem zuständigen
     * Stelle ermöglichen.
     */
    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /**
     * Name oder Bezeichnung der mit `contactEmail` erreichbaren Stelle.
     */
    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    /**
     * Link zur [Objektliste](#objektlisten) mit allen Gruppierungen der Körperschaft.
     *
     * @return OparlReference<OparlList<OparlOrganization>>|null
     */
    public function getOrganization(): ?OparlReference
    {
        return $this->organization;
    }

    /**
     * Link zur [Objektliste](#objektlisten) mit allen Personen der Körperschaft.
     *
     * @return OparlReference<OparlList<OparlPerson>>|null
     */
    public function getPerson(): ?OparlReference
    {
        return $this->person;
    }

    /**
     * Link zur [Objektliste](#objektlisten) mit allen Sitzungen der Körperschaft.
     *
     * @return OparlReference<OparlList<OparlMeeting>>|null
     */
    public function getMeeting(): ?OparlReference
    {
        return $this->meeting;
    }

    /**
     * Link zur [Objektliste](#objektlisten) mit allen Drucksachen der Körperschaft.
     *
     * @return OparlReference<OparlList<OparlPaper>>|null
     */
    public function getPaper(): ?OparlReference
    {
        return $this->paper;
    }

    /**
     * [Objektliste](#objektlisten) mit den Wahlperioden der Körperschaft.
     *
     * @return list<OparlLegislativeTerm>|null
     */
    public function getLegislativeTerm(): ?array
    {
        return $this->legislativeTerm;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Tagesordnungspunkten der
     * Körperschaft. Neu in OParl 1.1.
     *
     * @return OparlReference<OparlList<OparlAgendaItem>>|null
     */
    public function getAgendaItem(): ?OparlReference
    {
        return $this->agendaItem;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Beratungen der Körperschaft. Neu
     * in OParl 1.1.
     *
     * @return OparlReference<OparlList<OparlConsultation>>|null
     */
    public function getConsultation(): ?OparlReference
    {
        return $this->consultation;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Dateien der Körperschaft. Neu in
     * OParl 1.1.
     *
     * @return OparlReference<OparlList<OparlFile>>|null
     */
    public function getFile(): ?OparlReference
    {
        return $this->file;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Ortsangaben der Körperschaft.
     * Neu in OParl 1.1.
     *
     * @return OparlReference<OparlList<OparlLocation>>|null
     */
    public function getLocationList(): ?OparlReference
    {
        return $this->locationList;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Legislaturperioden der
     * Körperschaft. Neu in OParl 1.1. Die externe Objektliste enthält die gleichen Objekte wie
     * `legislativeTerm`
     *
     * @return OparlReference<OparlList<OparlLegislativeTerm>>|null
     */
    public function getLegislativeTermList(): ?OparlReference
    {
        return $this->legislativeTermList;
    }

    /**
     * **ZWINGEND** Link zur [Objektliste](#objektlisten) mit allen Mitgliedschaften der
     * Körperschaft. Neu in OParl 1.1.
     *
     * @return OparlReference<OparlList<OparlMembership>>|null
     */
    public function getMembership(): ?OparlReference
    {
        return $this->membership;
    }

    /**
     * Art der Körperschaft.
     */
    public function getClassification(): ?string
    {
        return $this->classification;
    }

    /**
     * Ort, an dem die Körperschaft beheimatet ist.
     */
    public function getLocation(): ?OparlLocation
    {
        return $this->location;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'system' => $this->system,
            'shortName' => $this->shortName,
            'name' => $this->name,
            'website' => $this->website,
            'licenseValidSince' => OparlTime::formatDateTime($this->licenseValidSince),
            'oparlSince' => OparlTime::formatDateTime($this->oparlSince),
            'ags' => $this->ags,
            'rgs' => $this->rgs,
            'equivalent' => $this->equivalent,
            'contactEmail' => $this->contactEmail,
            'contactName' => $this->contactName,
            'organization' => $this->organization,
            'person' => $this->person,
            'meeting' => $this->meeting,
            'paper' => $this->paper,
            'legislativeTerm' => $this->legislativeTerm,
            'agendaItem' => $this->agendaItem,
            'consultation' => $this->consultation,
            'file' => $this->file,
            'locationList' => $this->locationList,
            'legislativeTermList' => $this->legislativeTermList,
            'membership' => $this->membership,
            'classification' => $this->classification,
            'location' => $this->location,
        ];
    }
}
