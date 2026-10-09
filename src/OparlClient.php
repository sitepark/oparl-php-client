<?php

declare(strict_types=1);

namespace SP\OparlClient;

use Composer\InstalledVersions;
use Exception;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use SP\OparlClient\Core\OparlConnectionException;
use SP\OparlClient\Core\OparlError;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlHttpException;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlListLinks;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Core\OparlParseException;
use SP\OparlClient\Internal\LenientUrl;
use SP\OparlClient\Internal\LogSafe;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlTypes;

/**
 * Client for OParl interfaces. Requests OParl objects and lists from their URLs and maps them to
 * the classes in `SP\OparlClient\V1\Objects`; references between objects are resolved through the
 * same client.
 *
 * ```php
 * $client = new OparlClient();
 * $system = $client->get('https://oparl.example.org/', OparlSystem::class);
 * foreach ($system->getBody()?->get()->all() ?? [] as $body) {
 *     // ...
 * }
 * ```
 *
 * Requests are sent with a PSR-18 http client. Timeouts, redirects and proxies are configured
 * there; OParl servers may redirect to their canonical URLs, so the client should follow
 * redirects.
 */
final class OparlClient
{
    /**
     * Name in the default `User-Agent`, followed by the installed version of this library.
     */
    public const USER_AGENT_NAME = 'oparl-client';

    private const PACKAGE = 'sitepark/oparl-client';

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly string $userAgent;

    private readonly LoggerInterface $logger;

    private readonly ObjectMapper $mapper;

    /**
     * @param ClientInterface|null $httpClient the PSR-18 client sending the requests; if none is
     *     given, an installed one is discovered, see `php-http/discovery`
     * @param RequestFactoryInterface|null $requestFactory the PSR-17 factory creating the
     *     requests; if none is given, an installed one is discovered
     * @param string|null $userAgent the `User-Agent` header; `null` for
     *     `oparl-client/<version>`. An own value that names the application and a contact helps
     *     server operators, e.g. `my-app/1.0 (+https://example.org/contact)`
     * @param LoggerInterface|null $logger receives warnings about values the client ignored and
     *     debug messages about requests
     * @throws InvalidArgumentException if the user agent is blank or contains control characters
     * @throws \Http\Discovery\Exception\NotFoundException if no PSR-18 client or PSR-17 factory is
     *     given and none can be discovered
     */
    public function __construct(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?string $userAgent = null,
        ?LoggerInterface $logger = null,
    ) {
        if ($userAgent !== null && (trim($userAgent) === '' || LogSafe::of($userAgent) !== $userAgent)) {
            throw new InvalidArgumentException(
                'userAgent must not be blank or contain control characters: "' . LogSafe::of($userAgent) . '"',
            );
        }
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->userAgent = $userAgent ?? self::defaultUserAgent();
        $this->logger = $logger ?? new NullLogger();
        $this->mapper = new ObjectMapper(
            $this->logger,
            fn(string $url, string $class): OparlObject => $this->get($url, $class),
            fn(string $url, string $class): OparlList => $this->getList($url, $class),
        );
    }

    /**
     * Returns the `User-Agent` sent if none is configured: `oparl-client/<version>`.
     */
    public static function defaultUserAgent(): string
    {
        $version = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)
            ? InstalledVersions::getPrettyVersion(self::PACKAGE)
            : null;
        return $version !== null ? self::USER_AGENT_NAME . '/' . $version : self::USER_AGENT_NAME;
    }

    /**
     * Returns the value of the `User-Agent` header sent with every request.
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Requests the OParl object at the given URL.
     *
     * @template T of OparlObject
     * @param string $url the URL of the object
     * @param class-string<T> $class the class to map the object to, e.g. `OparlBody::class`
     * @return T
     * @throws OparlException if the request fails, e.g. an {@see OparlHttpException} if the server
     *     answers with an error
     * @throws InvalidArgumentException if `$class` is `OparlList`, use {@see self::getList()}, or
     *     `OparlListLinks`
     */
    public function get(string $url, string $class): OparlObject
    {
        self::assertObjectClass($class, 'Request lists with getList()');
        return $this->mapper->map($this->request($url), $class);
    }

    /**
     * Requests a page of an external object list, e.g. all meetings of a body. Usually lists are
     * requested through the references of an object, e.g. `$body->getMeeting()->get()`.
     *
     * @template T of OparlObject
     * @param string $url the URL of the page
     * @param class-string<T> $elementClass the class of the elements, e.g. `OparlMeeting::class`
     * @return OparlList<T>
     * @throws OparlException if the request fails
     */
    public function getList(string $url, string $elementClass): OparlList
    {
        return $this->mapper->mapList($this->request($url), $elementClass)->withSourceUris($url);
    }

    /**
     * Requests an OParl object without knowing its type in advance. The class of the result is
     * determined by the `type` property, e.g. an {@see \SP\OparlClient\V1\Objects\OparlBody} for
     * `https://schema.oparl.org/1.1/Body`.
     *
     * Servers may deliver vendor-specific object types, and the specification requires clients not
     * to fail on them. Objects of an unknown type, or without `type`, are therefore returned as
     * plain {@see OparlObjectV1}; their other properties are available via
     * {@see OparlObjectV1::getAdditionalProperties()}.
     *
     * @throws OparlException if the request fails
     */
    public function getAny(string $url): OparlObjectV1
    {
        $data = $this->request($url);
        $type = $data['type'] ?? null;
        $class = OparlTypes::classOf(is_string($type) ? $type : null) ?? OparlObjectV1::class;
        return $this->mapper->map($data, $class);
    }

    /**
     * Maps JSON to an object, exactly as a response of a server, e.g. to restore an object that
     * was stored with `json_encode()`. References in the object are resolved through this client.
     *
     * @template T of OparlObject
     * @param string $json a JSON object
     * @param class-string<T> $class the class to map the object to, e.g. `OparlBody::class`
     * @return T
     * @throws OparlException if the JSON is empty or `null`
     * @throws OparlParseException if the JSON is invalid or no JSON object
     * @throws InvalidArgumentException if `$class` is `OparlList`, use {@see self::listFromJson()},
     *     or `OparlListLinks`
     */
    public function fromJson(string $json, string $class): OparlObject
    {
        self::assertObjectClass($class, 'Map list pages with listFromJson()');
        return $this->mapper->map($this->decode(null, $json), $class);
    }

    /**
     * Maps JSON to a list page, exactly as a response of a server, see {@see self::fromJson()}.
     * Further pages are requested through this client.
     *
     * @template T of OparlObject
     * @param string $json a JSON object
     * @param class-string<T> $elementClass the class of the elements, e.g. `OparlMeeting::class`
     * @return OparlList<T>
     * @throws OparlException if the JSON is empty or `null`
     * @throws OparlParseException if the JSON is invalid or no JSON object
     */
    public function listFromJson(string $json, string $elementClass): OparlList
    {
        return $this->mapper->mapList($this->decode(null, $json), $elementClass);
    }

    /**
     * @param class-string<OparlObject> $class
     * @throws InvalidArgumentException if the class can only be mapped as or as part of a list page
     */
    private static function assertObjectClass(string $class, string $listMessage): void
    {
        if (is_a($class, OparlList::class, true)) {
            throw new InvalidArgumentException($listMessage);
        }
        if (is_a($class, OparlListLinks::class, true)) {
            throw new InvalidArgumentException('The links of a list page are only read as part of the page');
        }
    }

    /**
     * Sends a request and returns the JSON object of the response.
     *
     * @return array<mixed>
     * @throws OparlException if the request fails or the response is no JSON object
     */
    private function request(string $url): array
    {
        $this->logger->debug('Requesting {url}', ['url' => LogSafe::of($url)]);
        $request = $this->createRequest($url);
        try {
            $response = $this->httpClient->sendRequest($request);
            $body = (string) $response->getBody();
        } catch (ClientExceptionInterface $e) {
            throw new OparlConnectionException($url, $e);
        } catch (RuntimeException $e) {
            // e.g. the connection broke while reading the body
            throw new OparlConnectionException($url, $e);
        } catch (Exception $e) {
            // any other failure of the http client, so that every failed request is an OparlException
            throw new OparlException('Request to ' . $url . ' failed: ' . LogSafe::of($e->getMessage()), $url, $e);
        }
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new OparlHttpException($url, $statusCode, $this->parseError($body));
        }
        return $this->decode($url, $body);
    }

    /**
     * @throws OparlException if the URL is invalid or no absolute http(s) URL
     */
    private function createRequest(string $url): RequestInterface
    {
        $parts = LenientUrl::isValid($url) ? parse_url($url) : false;
        if ($parts === false) {
            throw new OparlException('Invalid URL ' . LogSafe::of($url), $url);
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            throw new OparlException(
                'Unsupported URL ' . $url . ', only absolute http and https URLs can be requested',
                $url,
            );
        }
        try {
            return $this->requestFactory->createRequest('GET', $url)
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', $this->userAgent);
        } catch (InvalidArgumentException $e) {
            throw new OparlException('Invalid URL ' . $url . ': ' . $e->getMessage(), $url, $e);
        }
    }

    /**
     * @param string|null $url the URL of the response, `null` for JSON not read from a server
     * @return array<mixed>
     * @throws OparlException if the body is empty or `null`
     * @throws OparlParseException if the body is no JSON object
     */
    private function decode(?string $url, string $body): array
    {
        $trimmed = self::normalizeBody($body);
        if ($trimmed === '' || $trimmed === 'null') {
            // must not be mistaken for an object or the end of a list
            throw new OparlException($url !== null ? 'Empty response from ' . $url : 'Empty JSON', $url);
        }
        try {
            $data = self::decodeJson($trimmed);
        } catch (JsonException $e) {
            throw new OparlParseException($url, $e->getMessage(), $e);
        }
        if (!is_array($data) || !str_starts_with($trimmed, '{')) {
            throw new OparlParseException($url, 'expected a JSON object, got ' . get_debug_type($data));
        }
        return $data;
    }

    /**
     * Returns the OParl error object contained in the body, or `null` if there is none.
     */
    private function parseError(string $body): ?OparlError
    {
        try {
            $data = self::decodeJson(self::normalizeBody($body));
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data) || !is_string($data['type'] ?? null) || !OparlError::isErrorType($data['type'])) {
            return null;
        }
        return $this->mapper->map($data, OparlError::class);
    }

    /**
     * Removes surrounding whitespace and a UTF-8 byte order mark, which some servers send.
     */
    private static function normalizeBody(string $body): string
    {
        $trimmed = trim($body);
        return str_starts_with($trimmed, "\xEF\xBB\xBF") ? trim(substr($trimmed, 3)) : $trimmed;
    }

    /**
     * Decodes JSON. Bytes that are no valid UTF-8, e.g. of a server sending Latin-1, are replaced
     * with U+FFFD instead of failing the whole response.
     *
     * @throws JsonException if the JSON is invalid
     */
    private static function decodeJson(string $json): mixed
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
