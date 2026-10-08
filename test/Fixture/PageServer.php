<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Internal\ObjectMapper;

/**
 * Serves list pages from memory, mapped like the client does, and counts the requests.
 */
final class PageServer
{
    public const BASE = 'https://oparl.example.org';

    /**
     * @var array<string, array<mixed>|OparlException>
     */
    private array $responses = [];

    /**
     * @var array<string, int>
     */
    private array $requests = [];

    private ObjectMapper $mapper;

    public function __construct(LoggerInterface $logger = new NullLogger())
    {
        $this->mapper = new ObjectMapper(
            $logger,
            listLoader: fn(string $url, string $class): OparlList => $this->load($url, $class),
        );
    }

    /**
     * Adds a page with elements of the given names.
     */
    public function page(string $path, ?string $self, ?string $next, string ...$names): void
    {
        $links = [];
        if ($self !== null) {
            $links['self'] = self::BASE . $self;
        }
        if ($next !== null) {
            $links['next'] = self::BASE . $next;
        }
        $this->responses[self::BASE . $path] = [
            'data' => array_map(static fn(string $name): array => ['name' => $name], $names),
            'pagination' => [],
            'links' => $links,
        ];
    }

    /**
     * @param array<mixed> $json
     */
    public function json(string $path, array $json): void
    {
        $this->responses[self::BASE . $path] = $json;
    }

    public function fail(string $path, OparlException $exception): void
    {
        $this->responses[self::BASE . $path] = $exception;
    }

    /**
     * @template E of OparlObject
     * @param class-string<E> $class
     * @return OparlList<E>
     */
    public function load(string $url, string $class): OparlList
    {
        $this->requests[$url] = ($this->requests[$url] ?? 0) + 1;
        $response = $this->responses[$url] ?? new OparlException('HTTP 404 for ' . $url, $url);
        if ($response instanceof OparlException) {
            throw $response;
        }
        return $this->mapper->mapList($response, $class)->withSourceUris($url);
    }

    /**
     * @return OparlList<TestObject>
     */
    public function get(string $path): OparlList
    {
        return $this->load(self::BASE . $path, TestObject::class);
    }

    public function requestCount(string $path): int
    {
        return $this->requests[self::BASE . $path] ?? 0;
    }
}
