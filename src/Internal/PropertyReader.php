<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

use Closure;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Core\OparlReference;

/**
 * Reads the properties of one JSON object, with one typed accessor per kind of value. Values that
 * can not be mapped do not fail the whole object or list page:
 *
 * - a single value is read as `null`,
 * - invalid elements of a list are left out, the valid ones are kept (`null` if none is valid),
 * - the original value is kept as additional property under the same name, so no information is
 *   lost and it is written back when the object is serialized,
 * - a warning is logged.
 *
 * A missing property and `null` are read as `null` without warning. A single value where a list
 * is required is read as list with that value.
 *
 * @internal
 */
final class PropertyReader
{
    private const MAX_LOGGED_VALUE_LENGTH = 200;

    /**
     * Names of the properties read so far.
     *
     * @var array<string, true>
     */
    private array $read = [];

    /**
     * Original values that could not be mapped, by property name.
     *
     * @var array<string, mixed>
     */
    private array $invalid = [];

    /**
     * @param array<mixed> $data the JSON object, as decoded by `json_decode(..., true)`
     * @param class-string<OparlObject> $class the class the object is mapped to, for log messages
     */
    public function __construct(
        private readonly array $data,
        private readonly string $class,
        private readonly ObjectMapper $mapper,
    ) {}

    public function string(string $name): ?string
    {
        return $this->single($name, self::toString(...), 'a string');
    }

    /**
     * @return list<string>|null
     */
    public function stringList(string $name): ?array
    {
        return $this->list($name, self::toString(...), 'a list of strings');
    }

    public function int(string $name): ?int
    {
        return $this->single($name, self::toInt(...), 'an integer');
    }

    public function bool(string $name): ?bool
    {
        return $this->single($name, self::toBool(...), 'a boolean');
    }

    public function url(string $name): ?string
    {
        return $this->single($name, self::toUrl(...), 'a URL');
    }

    /**
     * @return list<string>|null
     */
    public function urlList(string $name): ?array
    {
        return $this->list($name, self::toUrl(...), 'a list of URLs');
    }

    public function dateTime(string $name): ?DateTimeImmutable
    {
        return $this->single(
            $name,
            static fn(mixed $value): ?DateTimeImmutable => is_string($value) ? OparlTime::parseDateTime($value) : null,
            'a date-time',
        );
    }

    public function date(string $name): ?DateTimeImmutable
    {
        return $this->single(
            $name,
            static fn(mixed $value): ?DateTimeImmutable => is_string($value) ? OparlTime::parseDate($value) : null,
            'a date',
        );
    }

    /**
     * Reads free JSON, e.g. GeoJSON: a JSON object or array.
     *
     * @return array<mixed>|null
     */
    public function json(string $name): ?array
    {
        return $this->single(
            $name,
            static fn(mixed $value): ?array => is_array($value) ? $value : null,
            'a JSON object',
        );
    }

    /**
     * Reads a reference: a URL or, if the server embedded the object instead of referencing it,
     * the `id` of that object.
     *
     * @template C of OparlObject
     * @param class-string<C> $class the class of the referenced object
     * @return OparlReference<C>|null
     */
    public function reference(string $name, string $class): ?OparlReference
    {
        return $this->single($name, fn(mixed $value): ?OparlReference => $this->toReference($value, $class), 'a URL');
    }

    /**
     * @template C of OparlObject
     * @param class-string<C> $class the class of the referenced objects
     * @return list<OparlReference<C>>|null
     */
    public function referenceList(string $name, string $class): ?array
    {
        return $this->list(
            $name,
            fn(mixed $value): ?OparlReference => $this->toReference($value, $class),
            'a list of URLs',
        );
    }

    /**
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return C|null
     */
    public function object(string $name, string $class): ?OparlObject
    {
        return $this->single(
            $name,
            fn(mixed $value): ?OparlObject => $this->toObject($value, $class),
            'an embedded object',
        );
    }

    /**
     * Reads an embedded object with an own read function, for classes that need more than the
     * properties to be created, e.g. the links of a list page, which need the element class.
     *
     * @template C of OparlObject
     * @param class-string<OparlObject> $class the class of the object, for log messages
     * @param Closure(PropertyReader): C $read creates the object
     * @return C|null
     */
    public function objectWith(string $name, string $class, Closure $read): ?OparlObject
    {
        return $this->single(
            $name,
            fn(mixed $value): ?OparlObject => self::isObject($value) ? $this->mapper->mapWith($value, $class, $read) : null,
            'an embedded object',
        );
    }

    /**
     * Reads a reference to an external list, e.g. all meetings of a body.
     *
     * @template E of OparlObject
     * @param class-string<E> $elementClass the class of the elements of the list
     * @return OparlReference<OparlList<E>>|null
     */
    public function listReference(string $name, string $elementClass): ?OparlReference
    {
        return $this->single(
            $name,
            function (mixed $value) use ($elementClass): ?OparlReference {
                $url = self::toUrl($value);
                return $url !== null ? new OparlReference($url, $this->mapper->listLoader($elementClass)) : null;
            },
            'a URL',
        );
    }

    /**
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return list<C>|null
     */
    public function objectList(string $name, string $class): ?array
    {
        return $this->list(
            $name,
            fn(mixed $value): ?OparlObject => $this->toObject($value, $class),
            'a list of embedded objects',
        );
    }

    public function logger(): LoggerInterface
    {
        return $this->mapper->logger();
    }

    /**
     * Returns all properties that have not been read and the original values of the properties
     * that could not be mapped, in the order of the JSON object. Call it after all other
     * accessors.
     *
     * @return array<string, mixed>
     */
    public function additionalProperties(): array
    {
        $additional = [];
        foreach ($this->data as $name => $value) {
            $name = (string) $name;
            if (array_key_exists($name, $this->invalid)) {
                $additional[$name] = $this->invalid[$name];
            } elseif (!isset($this->read[$name])) {
                $additional[$name] = $value;
            }
        }
        return $additional;
    }

    /**
     * @template V
     * @param callable(mixed): (V|null) $convert returns `null` if the value is invalid
     * @return V|null
     */
    private function single(string $name, callable $convert, string $expected): mixed
    {
        $value = $this->take($name);
        if ($value === null) {
            return null;
        }
        $converted = $convert($value);
        if ($converted === null) {
            $this->keepInvalid($name, $value, $expected);
        }
        return $converted;
    }

    /**
     * @template V
     * @param callable(mixed): (V|null) $convert returns `null` if the element is invalid
     * @return list<V>|null
     */
    private function list(string $name, callable $convert, string $expected): ?array
    {
        $value = $this->take($name);
        if ($value === null) {
            return null;
        }
        // some servers send a single value where the specification requires a list
        $elements = is_array($value) && array_is_list($value) ? $value : [$value];
        $converted = [];
        foreach ($elements as $element) {
            $mapped = $element !== null ? $convert($element) : null;
            if ($mapped !== null) {
                $converted[] = $mapped;
            }
        }
        if (count($converted) < count($elements)) {
            $this->keepInvalid($name, $value, $expected);
            return $converted !== [] ? $converted : null;
        }
        return $converted;
    }

    /**
     * Marks the property as read and returns its value, `null` if it is missing.
     */
    private function take(string $name): mixed
    {
        $this->read[$name] = true;
        return $this->data[$name] ?? null;
    }

    private function keepInvalid(string $name, mixed $value, string $expected): void
    {
        $this->invalid[$name] = $value;
        $this->mapper->logger()->warning(
            'Ignoring invalid value of "{property}" in {class}, kept as additional property:'
            . ' expected {expected}, got {value}',
            [
                'property' => LogSafe::of($name),
                'class' => basename(str_replace('\\', '/', $this->class)),
                'expected' => $expected,
                'value' => self::shortJson($value),
            ],
        );
    }

    private static function shortJson(mixed $value): string
    {
        $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (preg_match('/^.{' . self::MAX_LOGGED_VALUE_LENGTH . '}(?=.)/su', $json, $match) === 1) {
            $json = $match[0] . '…';
        }
        return LogSafe::of($json);
    }

    /**
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return OparlReference<C>|null
     */
    private function toReference(mixed $value, string $class): ?OparlReference
    {
        if (is_array($value) && !array_is_list($value)) {
            // the server embedded the object instead of referencing it
            $value = $value['id'] ?? null;
        }
        $url = self::toUrl($value);
        return $url !== null ? new OparlReference($url, $this->mapper->objectLoader($class)) : null;
    }

    /**
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return C|null
     */
    private function toObject(mixed $value, string $class): ?OparlObject
    {
        return self::isObject($value) ? $this->mapper->map($value, $class) : null;
    }

    /**
     * Whether the value is a decoded JSON object. An empty array may be an empty JSON object.
     *
     * @phpstan-assert-if-true array<mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    private static function toString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            default => null,
        };
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_string($value)) {
            // integral numbers only, e.g. 3.0 or "3", but not 3.5 or "drei"
            $int = filter_var($value, FILTER_VALIDATE_INT);
            return is_int($int) ? $int : null;
        }
        return null;
    }

    private static function toBool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 'true', $value === 1 => true,
            $value === 'false', $value === 0 => false,
            default => null,
        };
    }

    private static function toUrl(mixed $value): ?string
    {
        return is_string($value) ? LenientUrl::parse($value) : null;
    }
}
