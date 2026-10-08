<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlObject;

/**
 * Maps decoded JSON objects to the classes of this library.
 *
 * @internal
 */
final class ObjectMapper
{
    /**
     * @param (Closure(string, class-string<OparlObject>): OparlObject)|null $objectLoader requests
     *     the object of the given class at a URL; used by the references created while mapping
     * @param (Closure(string, class-string<OparlObject>): OparlList<OparlObject>)|null $listLoader
     *     requests the list page with elements of the given class at a URL
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?Closure $objectLoader = null,
        private readonly ?Closure $listLoader = null,
    ) {}

    /**
     * @template C of OparlObject
     * @param array<mixed> $data a JSON object, as decoded by `json_decode(..., true)`
     * @param class-string<C> $class
     * @return C
     */
    public function map(array $data, string $class): OparlObject
    {
        $object = $class::read(new PropertyReader($data, $class, $this));
        assert($object instanceof $class);
        return $object;
    }

    /**
     * Maps a JSON object with an own read function, see {@see PropertyReader::objectWith()}.
     *
     * @template C of OparlObject
     * @param array<mixed> $data a JSON object, as decoded by `json_decode(..., true)`
     * @param class-string<OparlObject> $class the class of the object, for log messages
     * @param Closure(PropertyReader): C $read
     * @return C
     */
    public function mapWith(array $data, string $class, Closure $read): OparlObject
    {
        return $read(new PropertyReader($data, $class, $this));
    }

    /**
     * Maps a page of an external list.
     *
     * @template E of OparlObject
     * @param array<mixed> $data a JSON object, as decoded by `json_decode(..., true)`
     * @param class-string<E> $elementClass the class of the elements
     * @return OparlList<E>
     */
    public function mapList(array $data, string $elementClass): OparlList
    {
        return $this->mapWith(
            $data,
            OparlList::class,
            static fn(PropertyReader $reader): OparlList => OparlList::readPage($reader, $elementClass),
        );
    }

    /**
     * Returns the loader of references to objects of the given class, or `null` if objects are
     * mapped without a client.
     *
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return (Closure(string): C)|null
     */
    public function objectLoader(string $class): ?Closure
    {
        $loader = $this->objectLoader;
        if ($loader === null) {
            return null;
        }
        return static function (string $url) use ($loader, $class): OparlObject {
            $object = $loader($url, $class);
            assert($object instanceof $class);
            return $object;
        };
    }

    /**
     * Returns the loader of references to list pages with elements of the given class, or `null`
     * if objects are mapped without a client.
     *
     * @template E of OparlObject
     * @param class-string<E> $elementClass
     * @return (Closure(string): OparlList<E>)|null
     */
    public function listLoader(string $elementClass): ?Closure
    {
        $loader = $this->listLoader;
        if ($loader === null) {
            return null;
        }
        return static function (string $url) use ($loader, $elementClass): OparlList {
            /** @var OparlList<E> $list the loader maps the elements to the given class */
            $list = $loader($url, $elementClass);
            return $list;
        };
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }
}
