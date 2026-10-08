<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use JsonSerializable;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Base class of all objects read from an OParl server. Keeps the properties of the JSON object
 * that are not mapped, see {@see self::getAdditionalProperties()}.
 */
abstract class OparlObject implements JsonSerializable
{
    /**
     * @param array<string, mixed> $additionalProperties properties of the JSON object that are not
     *     mapped, as decoded by `json_decode(..., true)`
     */
    public function __construct(
        private readonly array $additionalProperties = [],
    ) {}

    /**
     * Creates the object from the properties of a JSON object.
     *
     * @internal used by the client to map responses
     */
    abstract public static function read(PropertyReader $reader): self;

    /**
     * Returns the mapped properties in the format of the specification, see
     * {@see self::jsonSerialize()}; properties without value may be `null`.
     *
     * @return array<string, mixed>
     */
    abstract protected function mappedProperties(): array;

    /**
     * Returns all properties of the JSON object that are not mapped to a property of this class,
     * e.g. vendor-specific extensions like `"Hersteller:faxNumber"` (see "Herstellerspezifische
     * Erweiterungen" in the OParl specification). Values are as decoded by
     * `json_decode(..., true)`: JSON objects are associative arrays. They are written back when
     * the object is serialized.
     *
     * Values of mapped properties that can not be mapped, e.g. a URL where the specification
     * requires an embedded object, are kept here as well with their original value; the mapped
     * property then leaves out the invalid elements of a list, or is `null`.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    /**
     * Returns a property that is not mapped, see {@see self::getAdditionalProperties()}.
     *
     * @return mixed the value, or `null` if the JSON object did not contain the property; use
     *     {@see self::hasAdditionalProperty()} to tell it from a property with value `null`
     */
    public function getAdditionalProperty(string $name): mixed
    {
        return $this->additionalProperties[$name] ?? null;
    }

    public function hasAdditionalProperty(string $name): bool
    {
        return array_key_exists($name, $this->additionalProperties);
    }

    /**
     * Returns the object in the format of the specification, for `json_encode()`. Properties
     * without value are left out, as the specification recommends; references are written as
     * URL. The additional properties are written as well; a value kept because it could not be
     * mapped is written instead of the mapped value, so no information is lost.
     */
    public function jsonSerialize(): object
    {
        $properties = array_filter(
            $this->mappedProperties(),
            static fn(mixed $value): bool => $value !== null,
        );
        return (object) array_replace($properties, $this->additionalProperties);
    }
}
