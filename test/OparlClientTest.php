<?php

declare(strict_types=1);

namespace SP\OparlClient\Test;

use Http\Discovery\ClassDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use SP\OparlClient\Core\OparlConnectionException;
use SP\OparlClient\Core\OparlError;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlHttpException;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlListLinks;
use SP\OparlClient\Core\OparlParseException;
use SP\OparlClient\OparlClient;
use SP\OparlClient\Test\Fixture\MockClientStrategy;
use SP\OparlClient\Test\Fixture\MockHttpClient;
use SP\OparlClient\Test\Fixture\RecordingLogger;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlConsultation;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlOrganization;
use SP\OparlClient\V1\Objects\OparlSystem;

#[CoversClass(OparlClient::class)]
#[CoversClass(OparlHttpException::class)]
#[CoversClass(OparlError::class)]
final class OparlClientTest extends TestCase
{
    private const BASE = MockHttpClient::BASE;

    private MockHttpClient $http;

    private RecordingLogger $logger;

    private OparlClient $client;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->logger = new RecordingLogger();
        $this->client = new OparlClient($this->http, new Psr17Factory(), logger: $this->logger);
    }

    /**
     * @return callable(): mixed
     */
    private function getBody(string $path): callable
    {
        return fn(): OparlBody => $this->client->get(self::BASE . $path, OparlBody::class);
    }

    private function expectFailure(callable $call): OparlException
    {
        try {
            $call();
        } catch (OparlException $e) {
            return $e;
        }
        $this->fail('OparlException expected');
    }

    public function testResolvesObject(): void
    {
        $this->http->respond('/body/1', 200, '{"id":"https://oparl.example.org/body/1",'
            . '"type":"https://schema.oparl.org/1.1/Body","name":"Stadt Beispiel"}');

        $body = $this->client->get(self::BASE . '/body/1', OparlBody::class);

        $this->assertSame('Stadt Beispiel', $body->getName());
        $this->assertSame(['Requesting https://oparl.example.org/body/1'], $this->logger->messages('debug'));
    }

    public function testSendsAcceptAndUserAgentHeaders(): void
    {
        $this->http->respond('/body/1', 200, '{}');

        $this->client->get(self::BASE . '/body/1', OparlBody::class);

        $request = $this->http->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame(OparlClient::defaultUserAgent(), $request->getHeaderLine('User-Agent'));
    }

    public function testDefaultUserAgentContainsInstalledVersion(): void
    {
        $this->assertMatchesRegularExpression('~^oparl-client/\S+$~', OparlClient::defaultUserAgent());
        $this->assertSame(OparlClient::defaultUserAgent(), $this->client->getUserAgent());
    }

    public function testSendsConfiguredUserAgent(): void
    {
        $this->http->respond('/body/1', 200, '{}');
        $client = new OparlClient($this->http, new Psr17Factory(), 'my-app/1.0 (+https://example.org/contact)');

        $client->get(self::BASE . '/body/1', OparlBody::class);

        $this->assertSame('my-app/1.0 (+https://example.org/contact)', $this->http->lastRequest()->getHeaderLine('User-Agent'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUserAgents(): iterable
    {
        yield 'blank' => [' '];
        yield 'line break' => ["my-app\r\nX: y"];
    }

    #[DataProvider('invalidUserAgents')]
    public function testRejectsInvalidUserAgent(string $userAgent): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent must not be blank or contain control characters: "'
            . str_replace(["\r", "\n"], '?', $userAgent) . '"');
        new OparlClient($this->http, new Psr17Factory(), $userAgent);
    }

    public function testDiscoversHttpClientAndRequestFactory(): void
    {
        $strategies = ClassDiscovery::getStrategies();
        $strategies = is_array($strategies) ? $strategies : iterator_to_array($strategies, false);
        Psr18ClientDiscovery::prependStrategy(MockClientStrategy::class);
        try {
            $client = new OparlClient();
            MockClientStrategy::$client?->respond('/body/1', 200, '{"name":"gefunden"}');

            $this->assertSame('gefunden', $client->get(self::BASE . '/body/1', OparlBody::class)->getName());
        } finally {
            ClassDiscovery::setStrategies($strategies);
            MockClientStrategy::$client = null;
        }
    }

    public function testResolvesReferencesThroughClient(): void
    {
        $this->http->respond('/consultation/1', 200, '{"meeting":"https://oparl.example.org/meeting/1"}');
        $this->http->respond('/meeting/1', 200, '{"name":"Ratssitzung","organization":["https://oparl.example.org/organization/1"]}');
        $this->http->respond('/organization/1', 200, '{"name":"Rat"}');

        $consultation = $this->client->get(self::BASE . '/consultation/1', OparlConsultation::class);
        $this->assertSame(1, count($this->http->requests), 'references are resolved lazily');
        $meeting = $consultation->getMeeting()?->get();

        $this->assertInstanceOf(OparlMeeting::class, $meeting);
        $this->assertSame('Ratssitzung', $meeting->getName());
        $organization = ($meeting->getOrganization()[0] ?? null)?->get();
        $this->assertInstanceOf(OparlOrganization::class, $organization);
        $this->assertSame('Rat', $organization->getName());
    }

    public function testResolvesListAndPaginatesThroughClient(): void
    {
        $this->http->respond('/', 200, '{"type":"https://schema.oparl.org/1.1/System","body":"https://oparl.example.org/bodies"}');
        $this->http->respond('/bodies', 200, '{"data":[{"name":"a"}],"links":{"next":"https://oparl.example.org/bodies?page=2"}}');
        $this->http->respond('/bodies?page=2', 200, '{"data":[{"name":"b"}],"links":{}}');

        $system = $this->client->get(self::BASE . '/', OparlSystem::class);
        $names = [];
        foreach ($system->getBody()?->get()->all() ?? [] as $body) {
            $names[] = $body->getName();
        }

        $this->assertSame(['a', 'b'], $names);
    }

    public function testRecordsRequestedUrlOfList(): void
    {
        $this->http->respond('/bodies', 200, '{"data":[],"pagination":{},"links":{}}');

        $list = $this->client->getList(self::BASE . '/bodies', OparlBody::class);

        $this->assertSame([self::BASE . '/bodies'], $list->getSourceUris());
    }

    public function testStopsPaginationOnCycleOfRequestedUrls(): void
    {
        $this->http->respond('/p1', 200, '{"data":[{"name":"a"}],"links":{"next":"https://oparl.example.org/p2"}}');
        $this->http->respond('/p2', 200, '{"data":[{"name":"b"}],"links":{"next":"https://oparl.example.org/p1"}}');

        $names = array_map(
            static fn(OparlBody $b): ?string => $b->getName(),
            iterator_to_array($this->client->getList(self::BASE . '/p1', OparlBody::class)->all(), false),
        );

        $this->assertSame(['a', 'b'], $names);
        $this->assertSame(1, $this->http->requestCount('/p1'));
        $this->assertCount(1, $this->logger->messages('warning'));
    }

    public function testRejectsListClassForSingleObjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request lists with getList()');
        $this->client->get(self::BASE . '/bodies', OparlList::class);
    }

    public function testRejectsLinksClassForSingleObjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The links of a list page are only read as part of the page');
        $this->client->get(self::BASE . '/bodies', OparlListLinks::class);
    }

    public function testResolvesAnyObjectByType(): void
    {
        $this->http->respond('/meeting/1', 200, '{"id":"https://oparl.example.org/meeting/1",'
            . '"type":"https://schema.oparl.org/1.0/Meeting","name":"Ratssitzung",'
            . '"organization":["https://oparl.example.org/organization/1"]}');

        $meeting = $this->client->getAny(self::BASE . '/meeting/1');

        $this->assertInstanceOf(OparlMeeting::class, $meeting);
        $this->assertSame('Ratssitzung', $meeting->getName());
        $this->assertSame('https://oparl.example.org/organization/1', ($meeting->getOrganization()[0] ?? null)?->getUri());
    }

    public function testResolvesVendorSpecificTypeAsGenericObject(): void
    {
        $this->http->respond('/other', 200, '{"id":"https://oparl.example.org/other/1",'
            . '"type":"https://hersteller.example.org/oparl/Ausschussvorlage","Hersteller:titel":"Vorlage 1"}');

        $object = $this->client->getAny(self::BASE . '/other');

        $this->assertSame(OparlObjectV1::class, $object::class);
        $this->assertSame('https://oparl.example.org/other/1', $object->getId());
        $this->assertSame('https://hersteller.example.org/oparl/Ausschussvorlage', $object->getType());
        $this->assertSame('Vorlage 1', $object->getAdditionalProperty('Hersteller:titel'));
    }

    public function testResolvesObjectWithoutTypeAsGenericObject(): void
    {
        $this->http->respond('/untyped', 200, '{"name":"x","type":42}');

        $object = $this->client->getAny(self::BASE . '/untyped');

        $this->assertSame(OparlObjectV1::class, $object::class);
        $this->assertSame('x', $object->getAdditionalProperty('name'));
    }

    public function testAcceptsWrongType(): void
    {
        $this->http->respond('/meeting/2', 200, '{"type":"https://schema.oparl.org/1.1/Meeting"}');

        $body = $this->client->get(self::BASE . '/meeting/2', OparlBody::class);

        $this->assertSame('https://schema.oparl.org/1.1/Meeting', $body->getType());
    }

    public function testFailsWithOparlErrorObject(): void
    {
        $this->http->respond('/body/404', 404, '{"type":"https://schema.oparl.org/1.1/Error",'
            . '"message":"Körperschaft nicht gefunden","debug":"id 404"}');

        $e = $this->expectFailure($this->getBody('/body/404'));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame(self::BASE . '/body/404', $e->getUri());
        $this->assertSame('HTTP 404 for https://oparl.example.org/body/404: Körperschaft nicht gefunden', $e->getMessage());
        $this->assertSame('https://schema.oparl.org/1.1/Error', $e->getError()?->getType());
        $this->assertSame('Körperschaft nicht gefunden', $e->getError()->getMessage());
        $this->assertSame('id 404', $e->getError()->getDebug());
    }

    public function testRecognizesErrorObjectOfOparl10(): void
    {
        $this->http->respond('/body/410', 410, '{"type":"https://schema.oparl.org/1.0/Error","message":"gelöscht"}');

        $e = $this->expectFailure($this->getBody('/body/410'));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertSame('gelöscht', $e->getError()?->getMessage());
    }

    public function testRemovesLineBreaksOfServerMessageFromExceptionMessage(): void
    {
        $this->http->respond('/body/400', 400, '{"type":"https://schema.oparl.org/1.1/Error",'
            . '"message":"falsch\nINFO gefälschte Logzeile"}');

        $e = $this->expectFailure($this->getBody('/body/400'));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertStringEndsWith('falsch?INFO gefälschte Logzeile', $e->getMessage());
        $this->assertSame("falsch\nINFO gefälschte Logzeile", $e->getError()?->getMessage());
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function errorResponsesWithoutErrorObject(): iterable
    {
        yield 'html error page' => [500, '<html><body>Oops</body></html>', 'text/html'];
        yield 'json without error type' => [503, '{"message":"maintenance"}', 'application/json'];
        yield 'json list' => [500, '["error"]', 'application/json'];
        yield 'empty' => [502, '', 'text/plain'];
        yield 'redirect' => [301, '', 'text/html'];
        yield 'multiple choices' => [300, '', 'text/html'];
    }

    #[DataProvider('errorResponsesWithoutErrorObject')]
    public function testFailsWithHttpExceptionWithoutErrorObject(int $status, string $body, string $contentType): void
    {
        $this->http->respond('/body/1', $status, $body, $contentType);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertSame($status, $e->getStatusCode());
        $this->assertSame('HTTP ' . $status . ' for https://oparl.example.org/body/1', $e->getMessage());
        $this->assertNull($e->getError());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyBodies(): iterable
    {
        yield 'null' => ['null'];
        yield 'empty' => [''];
        yield 'whitespace' => [" \n"];
    }

    #[DataProvider('emptyBodies')]
    public function testFailsForEmptyResponse(string $body): void
    {
        $this->http->respond('/empty', 200, $body);

        foreach ([
            fn(): object => $this->client->get(self::BASE . '/empty', OparlBody::class),
            fn(): object => $this->client->getList(self::BASE . '/empty', OparlBody::class),
            fn(): object => $this->client->getAny(self::BASE . '/empty'),
        ] as $call) {
            $e = $this->expectFailure($call);
            $this->assertSame(OparlException::class, $e::class);
            $this->assertSame('Empty response from https://oparl.example.org/empty', $e->getMessage());
            $this->assertSame(self::BASE . '/empty', $e->getUri());
        }
    }

    public function testIgnoresByteOrderMark(): void
    {
        $this->http->respond('/body/1', 200, "\xEF\xBB\xBF \n" . '{"name":"Stadt"}');

        $this->assertSame('Stadt', $this->client->get(self::BASE . '/body/1', OparlBody::class)->getName());
    }

    public function testReplacesInvalidUtf8InsteadOfFailing(): void
    {
        // e.g. a server sending Latin-1
        $this->http->respond('/body/1', 200, '{"name":"K' . "\xF6" . 'ln","shortName":"K"}');

        $body = $this->client->get(self::BASE . '/body/1', OparlBody::class);

        $this->assertSame("K\u{FFFD}ln", $body->getName());
        $this->assertSame('K', $body->getShortName());
    }

    public function testReadsErrorObjectWithByteOrderMarkAndInvalidUtf8(): void
    {
        $this->http->respond(
            '/body/404',
            404,
            "\xEF\xBB\xBF" . '{"type":"https://schema.oparl.org/1.1/Error","message":"nicht gef' . "\xFC" . 'nden"}',
        );

        $e = $this->expectFailure($this->getBody('/body/404'));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertSame("nicht gef\u{FFFD}nden", $e->getError()?->getMessage());
    }

    public function testIterationFailsForNullPage(): void
    {
        $this->http->respond('/p1', 200, '{"data":[{"name":"a"}],"links":{"next":"https://oparl.example.org/null"}}');
        $this->http->respond('/null', 200, 'null');
        $names = [];

        $e = $this->expectFailure(function () use (&$names): void {
            foreach ($this->client->getList(self::BASE . '/p1', OparlBody::class)->all() as $body) {
                $names[] = $body->getName();
            }
        });

        $this->assertSame(['a'], $names);
        $this->assertSame(self::BASE . '/null', $e->getUri());
    }

    public function testIterationFailsWithHttpExceptionOfPage(): void
    {
        $this->http->respond('/p1', 200, '{"data":[{"name":"a"}],"links":{"next":"https://oparl.example.org/p2"}}');
        $this->http->respond('/p2', 500, 'error', 'text/plain');

        $e = $this->expectFailure(fn(): array => iterator_to_array(
            $this->client->getList(self::BASE . '/p1', OparlBody::class)->all(),
        ));

        $this->assertInstanceOf(OparlHttpException::class, $e);
        $this->assertSame(500, $e->getStatusCode());
        $this->assertSame(self::BASE . '/p2', $e->getUri());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'syntax error' => ['{"name":', 'Invalid response from https://oparl.example.org/body/1: Syntax error'];
        yield 'list' => ['[{"name":"a"}]', 'Invalid response from https://oparl.example.org/body/1: expected a JSON object, got array'];
        yield 'empty list' => ['[]', 'Invalid response from https://oparl.example.org/body/1: expected a JSON object, got array'];
        yield 'string' => ['"text"', 'Invalid response from https://oparl.example.org/body/1: expected a JSON object, got string'];
        yield 'html' => ['<html></html>', 'Invalid response from https://oparl.example.org/body/1: Syntax error'];
    }

    #[DataProvider('invalidJson')]
    public function testFailsWithParseExceptionForInvalidJson(string $body, string $message): void
    {
        $this->http->respond('/body/1', 200, $body);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertInstanceOf(OparlParseException::class, $e);
        $this->assertSame($message, $e->getMessage());
        $this->assertSame(self::BASE . '/body/1', $e->getUri());
    }

    public function testReportsNetworkFailureAsConnectionException(): void
    {
        $failure = new class ('Connection refused') extends RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return (new Psr17Factory())->createRequest('GET', MockHttpClient::BASE);
            }
        };
        $this->http->fail('/body/1', $failure);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertInstanceOf(OparlConnectionException::class, $e);
        $this->assertSame(self::BASE . '/body/1', $e->getUri());
        $this->assertSame($failure, $e->getPrevious());
        $this->assertSame('Request to https://oparl.example.org/body/1 failed: Connection refused', $e->getMessage());
    }

    public function testReportsFailureWhileReadingBodyAsConnectionException(): void
    {
        $failure = new RuntimeException('Unable to read from stream');
        $this->http->fail('/body/1', $failure);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertInstanceOf(OparlConnectionException::class, $e);
        $this->assertSame($failure, $e->getPrevious());
    }

    public function testReportsOtherFailuresOfHttpClientAsOparlException(): void
    {
        $failure = new \Exception('client closed');
        $this->http->fail('/body/1', $failure);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertSame(OparlException::class, $e::class);
        $this->assertSame(self::BASE . '/body/1', $e->getUri());
        $this->assertSame($failure, $e->getPrevious());
        $this->assertSame('Request to https://oparl.example.org/body/1 failed: client closed', $e->getMessage());
    }

    public function testReportsClientExceptionThatIsNoRuntimeExceptionAsConnectionException(): void
    {
        $failure = new class ('TLS handshake failed') extends \Exception implements ClientExceptionInterface {};
        $this->http->fail('/body/1', $failure);

        $e = $this->expectFailure($this->getBody('/body/1'));

        $this->assertInstanceOf(OparlConnectionException::class, $e);
        $this->assertSame($failure, $e->getPrevious());
    }

    public function testAcceptsSchemeInUpperCase(): void
    {
        $this->http->respond('/body/1', 200, '{"name":"Stadt"}');

        $body = $this->client->get('HTTPS://oparl.example.org/body/1', OparlBody::class);

        $this->assertSame('Stadt', $body->getName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedUrls(): iterable
    {
        yield 'relative' => ['/meeting/1', 'Unsupported URL /meeting/1, only absolute http and https URLs can be requested'];
        yield 'other scheme' => ['ftp://oparl.example.org/meeting/1', 'Unsupported URL ftp://oparl.example.org/meeting/1, only absolute http and https URLs can be requested'];
        yield 'without host' => ['https:/meeting/1', 'Unsupported URL https:/meeting/1, only absolute http and https URLs can be requested'];
        yield 'invalid' => ["https://oparl.example.org/a b\n", 'Invalid URL https://oparl.example.org/a b?'];
    }

    #[DataProvider('unsupportedUrls')]
    public function testRejectsUnsupportedUrl(string $url, string $message): void
    {
        foreach ([
            fn(): object => $this->client->get($url, OparlBody::class),
            fn(): object => $this->client->getList($url, OparlBody::class),
            fn(): object => $this->client->getAny($url),
        ] as $call) {
            $e = $this->expectFailure($call);
            $this->assertSame(OparlException::class, $e::class);
            $this->assertSame($message, $e->getMessage());
            $this->assertSame($url, $e->getUri());
        }
        $this->assertSame([], $this->http->requests);
    }

    public function testIterationFailsWithExceptionOfPageWithUnsupportedUrl(): void
    {
        $this->http->respond('/p1', 200, '{"data":[{"name":"a"}],"links":{"next":"/bodies?page=2"}}');

        $e = $this->expectFailure(fn(): array => iterator_to_array(
            $this->client->getList(self::BASE . '/p1', OparlBody::class)->all(),
        ));

        $this->assertStringStartsWith('Unsupported URL /bodies?page=2', $e->getMessage());
    }

    public function testReportsUnparsableUrlAsInvalid(): void
    {
        $e = $this->expectFailure(fn(): object => $this->client->get('https://oparl.example.org:99999/', OparlBody::class));

        $this->assertSame('Invalid URL https://oparl.example.org:99999/', $e->getMessage());
    }

    public function testReportsUrlRejectedByRequestFactoryAsOparlException(): void
    {
        $failure = new InvalidArgumentException('Unsupported host');
        $factory = new class ($failure) implements RequestFactoryInterface {
            public function __construct(private readonly InvalidArgumentException $failure) {}

            public function createRequest(string $method, $uri): RequestInterface
            {
                throw $this->failure;
            }
        };
        $client = new OparlClient($this->http, $factory);

        $e = $this->expectFailure(fn(): object => $client->get(self::BASE . '/body/1', OparlBody::class));

        $this->assertSame(OparlException::class, $e::class);
        $this->assertSame($failure, $e->getPrevious());
        $this->assertSame('Invalid URL https://oparl.example.org/body/1: Unsupported host', $e->getMessage());
    }
}
