<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Ein `oparl:System`-Objekt repräsentiert eine OParl-Schnittstelle für eine bestimmte
 * OParl-Version. Es ist außerdem der Startpunkt für Clients beim Zugriff auf einen Server.
 *
 * Möchte ein Server mehrere zueinander inkompatible OParl-Versionen unterstützen, dann **muss** der
 * Server für jede Version eine eigenen OParl-Schnittstelle mit einem eigenen `System`-Objekt
 * ausgeben.
 *
 * @see https://dev.oparl.org/spezifikation/1.1#entity-system
 */
final class OparlSystem extends OparlObjectV1
{
    /**
     * @param list<OparlReference<OparlSystem>>|null $otherOparlVersions
     * @param OparlReference<OparlList<OparlBody>>|null $body
     * @param list<string>|null $keyword
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        ?string $id = null,
        ?string $type = null,
        private readonly ?string $oparlVersion = null,
        private readonly ?array $otherOparlVersions = null,
        private readonly ?OparlReference $body = null,
        private readonly ?string $name = null,
        private readonly ?string $contactEmail = null,
        private readonly ?string $contactName = null,
        private readonly ?string $website = null,
        private readonly ?string $vendor = null,
        private readonly ?string $product = null,
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
            oparlVersion: $reader->string('oparlVersion'),
            otherOparlVersions: $reader->referenceList('otherOparlVersions', OparlSystem::class),
            body: $reader->listReference('body', OparlBody::class),
            name: $reader->string('name'),
            contactEmail: $reader->string('contactEmail'),
            contactName: $reader->string('contactName'),
            website: $reader->url('website'),
            vendor: $reader->url('vendor'),
            product: $reader->url('product'),
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
     * Die URL der OParl-Spezifikation, die von diesem Server unterstützt wird. Aktuell kommt hier
     * nur ein Wert in Frage. Mit zukünftigen OParl-Versionen kommen weitere mögliche URLs hinzu.
     * Wert: `https://schema.oparl.org/1.1/`
     */
    public function getOparlVersion(): ?string
    {
        return $this->oparlVersion;
    }

    /**
     * Dient der Angabe von System-Objekten mit anderen OParl-Versionen.
     *
     * @return list<OparlReference<OparlSystem>>|null
     */
    public function getOtherOparlVersions(): ?array
    {
        return $this->otherOparlVersions;
    }

    /**
     * Link zur [Objektliste](#objektlisten) mit allen Körperschaften, die auf dem System
     * existieren.
     *
     * @return OparlReference<OparlList<OparlBody>>|null
     */
    public function getBody(): ?OparlReference
    {
        return $this->body;
    }

    /**
     * Nutzerfreundlicher Name für das System, mit dessen Hilfe Nutzerinnen und Nutzer das System
     * erkennen und von anderen unterscheiden können.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * E-Mail-Adresse für Anfragen zur OParl-API. Die Angabe einer E-Mail-Adresse dient sowohl
     * NutzerInnen wie auch Entwicklerinnen von Clients zur Kontaktaufnahme mit dem Betreiber.
     */
    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /**
     * Name der Ansprechpartnerin bzw. des Ansprechpartners oder der Abteilung, die über die in
     * `contactEmail` angegebene Adresse erreicht werden kann.
     */
    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    /**
     * URL der Website des parlamentarischen Informationssystems
     */
    public function getWebsite(): ?string
    {
        return $this->website;
    }

    /**
     * URL der Website des Softwareanbieters, von dem die OParl-Server-Software stammt.
     */
    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    /**
     * URL zu Informationen über die auf dem System genutzte OParl-Server-Software
     */
    public function getProduct(): ?string
    {
        return $this->product;
    }

    protected function mappedProperties(): array
    {
        return [
            ...parent::mappedProperties(),
            'oparlVersion' => $this->oparlVersion,
            'otherOparlVersions' => $this->otherOparlVersions,
            'body' => $this->body,
            'name' => $this->name,
            'contactEmail' => $this->contactEmail,
            'contactName' => $this->contactName,
            'website' => $this->website,
            'vendor' => $this->vendor,
            'product' => $this->product,
        ];
    }
}
