<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Internal\ObjectMapper;

/**
 * Maps JSON strings like the client does, without a client.
 */
final class Json
{
    /**
     * @template C of OparlObject
     * @param class-string<C> $class
     * @return C
     */
    public static function map(string $json, string $class, LoggerInterface $logger = new NullLogger()): OparlObject
    {
        return (new ObjectMapper($logger))->map(self::decodeObject($json), $class);
    }

    /**
     * @return array<mixed>
     */
    public static function decodeObject(string $json): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('no JSON object: ' . $json);
        }
        return $data;
    }

    /**
     * Writes the object as JSON and decodes it again, for comparisons independent of formatting.
     *
     * @return array<mixed>
     */
    public static function written(OparlObject $object): array
    {
        return self::decodeObject((string) json_encode($object));
    }
}
