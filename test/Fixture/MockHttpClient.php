<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client answering requests from configured responses, recording all requests. Like a
 * client without redirect handling, it returns redirects as they are.
 */
final class MockHttpClient implements ClientInterface
{
    public const BASE = 'https://oparl.example.org';

    /**
     * @var array<string, ResponseInterface|\Throwable>
     */
    private array $responses = [];

    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    public function respond(string $path, int $status, string $body, string $contentType = 'application/json'): self
    {
        $this->responses[self::BASE . $path] = new Response($status, ['Content-Type' => $contentType], $body);
        return $this;
    }

    public function fail(string $path, \Throwable $failure): self
    {
        $this->responses[self::BASE . $path] = $failure;
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = $this->responses[(string) $request->getUri()] ?? new Response(404, [], 'not found');
        if ($response instanceof \Throwable) {
            throw $response;
        }
        return $response;
    }

    public function lastRequest(): RequestInterface
    {
        $request = end($this->requests);
        if ($request === false) {
            throw new \LogicException('no request sent');
        }
        return $request;
    }

    public function requestCount(string $path): int
    {
        return count(array_filter(
            $this->requests,
            static fn(RequestInterface $r): bool => (string) $r->getUri() === self::BASE . $path,
        ));
    }
}
