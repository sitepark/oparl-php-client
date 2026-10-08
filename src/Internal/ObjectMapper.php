<?php

declare(strict_types=1);

namespace SP\OparlClient\Internal;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
     *     the object at a URL; used by the references created while mapping
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?Closure $objectLoader = null,
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

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }
}
