<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Ein Objekt vom Typ `oparl:File` repräsentiert eine Datei, beispielsweise eine PDF-Datei, ein RTF-
 * oder ODF-Dokument,
 *
 * und hält Metadaten zu der Datei sowie URLs zum Zugriff auf  die Datei bereit.
 *
 * Objekte vom Typ `oparl:File` können unter anderem mit Drucksachen (`oparl:Paper`) oder Sitzungen
 * (`oparl:Meeting`) in Beziehung stehen.
 *
 * Dies wird durch  die Eigenschaft `paper` bzw. `meeting` angezeigt. Mehrere Objekte vom Typ
 * `oparl:File` können mit einander in direkter
 *
 * Beziehung stehen, z.B. wenn sie den selben Inhalt in unterschiedlichen technischen Formaten
 * wiedergeben. Hierfür werden die
 *
 * Eigenschaften `masterFile` bzw. `derivativeFile` eingesetzt. Das gezeigte Beispiel-Objekt
 * repräsentiert eine PDF-Datei
 *
 * (zu erkennen an der Eigenschaft `mimeType`) und zeigt außerdem über die Eigenschaft  `masterFile`
 * an, von welcher anderen Datei es
 *
 * abgeleitet wurde. Umgekehrt **kann** über die Eigenschaft `derivativeFile` angezeigt werden,
 * welche Ableitungen einer Datei existieren.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-file
 */
final readonly class OparlFile extends OparlObjectV1
{
    /**
     * @param OparlReference<OparlFile>|null $masterFile
     * @param list<OparlReference<OparlFile>>|null $derivativeFile
     * @param list<OparlReference<OparlMeeting>>|null $meeting
     * @param list<OparlReference<OparlAgendaItem>>|null $agendaItem
     * @param list<OparlReference<OparlPaper>>|null $paper
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private ?string $name = null,
        private ?string $fileName = null,
        private ?string $mimeType = null,
        private ?DateTimeImmutable $date = null,
        private ?int $size = null,
        private ?string $sha1Checksum = null,
        private ?string $sha512Checksum = null,
        private ?string $text = null,
        private ?string $accessUrl = null,
        private ?string $downloadUrl = null,
        private ?string $externalServiceUrl = null,
        private ?OparlReference $masterFile = null,
        private ?array $derivativeFile = null,
        private ?string $fileLicense = null,
        private ?array $meeting = null,
        private ?array $agendaItem = null,
        private ?array $paper = null,
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
            fileName: $reader->string('fileName'),
            mimeType: $reader->string('mimeType'),
            date: $reader->date('date'),
            size: $reader->int('size'),
            sha1Checksum: $reader->string('sha1Checksum'),
            sha512Checksum: $reader->string('sha512Checksum'),
            text: $reader->string('text'),
            accessUrl: $reader->url('accessUrl'),
            downloadUrl: $reader->url('downloadUrl'),
            externalServiceUrl: $reader->url('externalServiceUrl'),
            masterFile: $reader->reference('masterFile', OparlFile::class),
            derivativeFile: $reader->referenceList('derivativeFile', OparlFile::class),
            fileLicense: $reader->url('fileLicense'),
            meeting: $reader->referenceList('meeting', OparlMeeting::class),
            agendaItem: $reader->referenceList('agendaItem', OparlAgendaItem::class),
            paper: $reader->referenceList('paper', OparlPaper::class),
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
     * Ein zur Anzeige für Endnutzer bestimmter Name für dieses Objekt. Leerzeichen **dürfen**
     * enthalten sein, Datei-Endungen wie ".pdf" **sollten nicht** enthalten sein.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Dateiname, unter dem die Datei in einem Dateisystem gespeichert werden kann. Beispiel:
     * "einedatei.pdf". Da der Name den kompletten Unicode-Zeichenumfang nutzen kann, **sollten**
     * Clients ggfs. selbst dafür sorgen, diesen beim Speichern in ein Dateisystem den lokalen
     * Erfordernissen anzupassen.
     */
    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /**
     * MIME-Type der Datei ^[vgl. RFC2046: <http://tools.ietf.org/html/rfc2046>].
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Datum, welches als Startpunkt für Fristen u.ä. verwendet ist.
     */
    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Größe der Datei in Bytes.
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * [Veraltet] SHA1-Prüfsumme des Dateiinhalts in Hexadezimal-Schreibweise. Sollte nicht mehr
     * verwendet werden, da sha1 als unsicher gilt. Stattdessen sollte `sha512checksum` verwendet
     * werden.
     */
    public function getSha1Checksum(): ?string
    {
        return $this->sha1Checksum;
    }

    /**
     * SHA512-Prüfsumme des Dateiinhalts in Hexadezimal-Schreibweise.
     */
    public function getSha512Checksum(): ?string
    {
        return $this->sha512Checksum;
    }

    /**
     * Reine Text-Wiedergabe des Dateiinhalts, sofern dieser in Textform wiedergegeben werden kann.
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * URL zum allgemeinen Zugriff auf die Datei. Näheres unter [Dateizugriffe](#dateizugriff).
     */
    public function getAccessUrl(): ?string
    {
        return $this->accessUrl;
    }

    /**
     * URL zum Download der Datei. Näheres unter [Dateizugriffe](#dateizugriff).
     */
    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    /**
     * Externe URL, welche eine zusätzliche Zugriffsmöglichkeit bietet. Beispiel: YouTube-Video.
     */
    public function getExternalServiceUrl(): ?string
    {
        return $this->externalServiceUrl;
    }

    /**
     * Datei, von der das aktuelle Objekt abgeleitet wurde. Details dazu in der allgemeinen
     * Beschreibung weiter oben.
     *
     * @return OparlReference<OparlFile>|null
     */
    public function getMasterFile(): ?OparlReference
    {
        return $this->masterFile;
    }

    /**
     * Dateien, die von dem aktuellen Objekt abgeleitet wurden. Details dazu in der allgemeinen
     * Beschreibung weiter oben.
     *
     * @return list<OparlReference<OparlFile>>|null
     */
    public function getDerivativeFile(): ?array
    {
        return $this->derivativeFile;
    }

    /**
     * Lizenz, unter der die Datei angeboten wird. Wenn diese Eigenschaft nicht verwendet wird, ist
     * der Wert von `license` beziehungsweise die Lizenz eines übergeordneten Objektes maßgeblich.
     * Siehe [license](#eigenschaft_license)
     */
    public function getFileLicense(): ?string
    {
        return $this->fileLicense;
    }

    /**
     * Rückreferenzen auf Meeting-Objekte. Wird nur dann ausgegeben, wenn das File-Objekt nicht als
     * eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlMeeting>>|null
     */
    public function getMeeting(): ?array
    {
        return $this->meeting;
    }

    /**
     * Rückreferenzen auf AgendaItem-Objekte. Wird nur dann ausgegeben, wenn das File-Objekt nicht
     * als eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlAgendaItem>>|null
     */
    public function getAgendaItem(): ?array
    {
        return $this->agendaItem;
    }

    /**
     * Rückreferenzen auf Paper-Objekte. Wird nur dann ausgegeben, wenn das File-Objekt nicht als
     * eingebettetes Objekt aufgerufen wird.
     *
     * @return list<OparlReference<OparlPaper>>|null
     */
    public function getPaper(): ?array
    {
        return $this->paper;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'name' => $this->name,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'date' => OparlTime::formatDate($this->date),
            'size' => $this->size,
            'sha1Checksum' => $this->sha1Checksum,
            'sha512Checksum' => $this->sha512Checksum,
            'text' => $this->text,
            'accessUrl' => $this->accessUrl,
            'downloadUrl' => $this->downloadUrl,
            'externalServiceUrl' => $this->externalServiceUrl,
            'masterFile' => $this->masterFile,
            'derivativeFile' => $this->derivativeFile,
            'fileLicense' => $this->fileLicense,
            'meeting' => $this->meeting,
            'agendaItem' => $this->agendaItem,
            'paper' => $this->paper,
        ];
    }
}
