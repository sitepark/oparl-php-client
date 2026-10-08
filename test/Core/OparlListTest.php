<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlListLinks;
use SP\OparlClient\Core\OparlPagination;
use SP\OparlClient\Internal\ObjectMapper;
use SP\OparlClient\Internal\PropertyReader;
use SP\OparlClient\Test\Fixture\PageServer;
use SP\OparlClient\Test\Fixture\RecordingLogger;
use SP\OparlClient\Test\Fixture\TestObject;

#[CoversClass(OparlList::class)]
#[CoversClass(OparlListLinks::class)]
#[CoversClass(OparlPagination::class)]
#[CoversClass(ObjectMapper::class)]
final class OparlListTest extends TestCase
{
    private RecordingLogger $logger;

    private PageServer $server;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->server = new PageServer($this->logger);
    }

    /**
     * @return list<string|null>
     */
    private function namesFrom(string $path): array
    {
        $names = [];
        foreach ($this->server->get($path)->all() as $element) {
            $names[] = $element->name;
        }
        return $names;
    }

    public function testIteratesOverAllPages(): void
    {
        $this->server->page('/p1', '/p1', '/p2', 'a', 'b');
        $this->server->page('/p2', '/p2', '/p3', 'c');
        $this->server->page('/p3', '/p3', null, 'd');

        $this->assertSame(['a', 'b', 'c', 'd'], $this->namesFrom('/p1'));
        $this->assertSame([], $this->logger->records);
    }

    public function testSkipsEmptyPages(): void
    {
        $this->server->page('/p1', '/p1', '/p2', 'a');
        $this->server->page('/p2', '/p2', '/p3');
        $this->server->page('/p3', '/p3', null, 'b');

        $this->assertSame(['a', 'b'], $this->namesFrom('/p1'));
    }

    public function testContinuesAfterEmptyFirstPage(): void
    {
        $this->server->page('/p1', '/p1', '/p2');
        $this->server->page('/p2', '/p2', null, 'a');

        $this->assertSame(['a'], $this->namesFrom('/p1'));
    }

    public function testStopsAtPageThatWasAlreadyVisited(): void
    {
        $this->server->page('/p1', '/p1', '/p2', 'a');
        $this->server->page('/p2', '/p2', '/p1', 'b');

        $this->assertSame(['a', 'b'], $this->namesFrom('/p1'));
        $this->assertSame(
            ['Stopping pagination, page https://oparl.example.org/p1 has already been visited'],
            $this->logger->messages('warning'),
        );
    }

    public function testStopsOnCycleWithoutSelfLinks(): void
    {
        $this->server->page('/p1', null, '/p2', 'a');
        $this->server->page('/p2', null, '/p1', 'b');

        $this->assertSame(['a', 'b'], $this->namesFrom('/p1'));
        $this->assertSame(1, $this->server->requestCount('/p1'));
    }

    public function testStopsAtPageNamingItselfAsVisitedPage(): void
    {
        // e.g. an old URL that the server answers with the content of the first page
        $this->server->page('/p1', '/p1', '/p2', 'a');
        $this->server->page('/p2', '/p2', '/old/p1', 'b');
        $this->server->page('/old/p1', '/p1', '/p2', 'a');

        $this->assertSame(['a', 'b'], $this->namesFrom('/p1'));
        $this->assertSame(
            ['Stopping pagination, page https://oparl.example.org/old/p1 leads to a page that has already been visited'],
            $this->logger->messages('warning'),
        );
    }

    public function testFailsWithExceptionOfPageThatCanNotBeFetched(): void
    {
        $this->server->page('/p1', '/p1', '/p2', 'a');
        $failure = new OparlException('HTTP 500 for https://oparl.example.org/p2', 'https://oparl.example.org/p2');
        $this->server->fail('/p2', $failure);
        $names = [];

        try {
            foreach ($this->server->get('/p1')->all() as $element) {
                $names[] = $element->name;
            }
            $this->fail('exception expected');
        } catch (OparlException $e) {
            $this->assertSame($failure, $e);
        }
        $this->assertSame(['a'], $names);
    }

    public function testFetchesPagesLazily(): void
    {
        $this->server->page('/p1', null, '/p2', 'a', 'b');
        $this->server->page('/p2', null, '/p3', 'c');
        $this->server->page('/p3', null, null, 'd');

        foreach ($this->server->get('/p1')->all() as $element) {
            $this->assertSame('a', $element->name);
            break;
        }

        $this->assertSame(0, $this->server->requestCount('/p2'));
        $this->assertSame(0, $this->server->requestCount('/p3'));
    }

    public function testCanIterateMoreThanOnce(): void
    {
        $this->server->page('/p1', null, '/p2', 'a');
        $this->server->page('/p2', null, null, 'b');
        $page = $this->server->get('/p1');

        $this->assertSame(['a', 'b'], array_map(static fn(TestObject $o): ?string => $o->name, iterator_to_array($page->all(), false)));
        $this->assertSame(['a', 'b'], array_map(static fn(TestObject $o): ?string => $o->name, iterator_to_array($page->all(), false)));
    }

    public function testYieldsConsecutiveKeys(): void
    {
        $this->server->page('/p1', null, '/p2', 'a', 'b');
        $this->server->page('/p2', null, null, 'c');

        $this->assertSame([0, 1, 2], array_keys(iterator_to_array($this->server->get('/p1')->all())));
    }

    public function testHandlesMissingDataAndLinks(): void
    {
        $this->server->json('/p1', ['data' => null, 'links' => null]);
        $page = $this->server->get('/p1');

        $this->assertSame([], $this->namesFrom('/p1'));
        $this->assertSame([], $page->getData());
        $this->assertFalse($page->hasNextPage());
        $this->assertNull($page->getLinks()->getSelf());
        $this->assertNull($page->getPagination()->getTotalElements());
    }

    public function testNavigatesToNextPage(): void
    {
        $this->server->page('/p1', null, '/p2', 'a');
        $this->server->page('/p2', null, null, 'b');
        $first = $this->server->get('/p1');

        $this->assertTrue($first->hasNextPage());
        $second = $first->fetchNextPage();

        $this->assertSame('b', $second->getData()[0]->name ?? null);
        $this->assertFalse($second->hasNextPage());
        $this->expectException(LogicException::class);
        $second->fetchNextPage();
    }

    public function testReadsPaginationAndLinks(): void
    {
        $this->server->json('/p2', [
            'data' => [['name' => 'a']],
            'pagination' => ['totalElements' => 50, 'elementsPerPage' => 20, 'currentPage' => 2, 'totalPages' => 3],
            'links' => [
                'first' => PageServer::BASE . '/p1',
                'prev' => PageServer::BASE . '/p1',
                'self' => PageServer::BASE . '/p2',
                'next' => PageServer::BASE . '/p3',
                'last' => PageServer::BASE . '/p3',
                'web' => 'https://ris.example.org/gremien',
            ],
            'Hersteller:generated' => '2024-01-01',
        ]);
        $this->server->page('/p1', null, null, 'erste');

        $page = $this->server->get('/p2');

        $pagination = $page->getPagination();
        $this->assertSame([50, 20, 2, 3], [
            $pagination->getTotalElements(),
            $pagination->getElementsPerPage(),
            $pagination->getCurrentPage(),
            $pagination->getTotalPages(),
        ]);
        $links = $page->getLinks();
        $this->assertSame(PageServer::BASE . '/p1', $links->getFirst()?->getUri());
        $this->assertSame(PageServer::BASE . '/p1', $links->getPrev()?->getUri());
        $this->assertSame(PageServer::BASE . '/p2', $links->getSelf()?->getUri());
        $this->assertSame(PageServer::BASE . '/p3', $links->getNext()?->getUri());
        $this->assertSame(PageServer::BASE . '/p3', $links->getLast()?->getUri());
        $this->assertSame('https://ris.example.org/gremien', $links->getWeb());
        $this->assertSame('erste', $links->getFirst()->get()->getData()[0]->name ?? null);
        $this->assertSame('2024-01-01', $page->getAdditionalProperty('Hersteller:generated'));
        $this->assertSame([PageServer::BASE . '/p2'], $page->getSourceUris());
    }

    public function testReadsPageDespiteInvalidElementsAndValues(): void
    {
        $this->server->json('/p1', [
            'data' => [['name' => 'a', 'count' => 'viele'], 'https://oparl.example.org/x', ['name' => 'b']],
            'pagination' => ['totalElements' => 'unbekannt', 'Hersteller:cursor' => 'abc'],
            'links' => ['next' => 42],
        ]);

        $page = $this->server->get('/p1');

        $this->assertSame(['a', 'b'], array_map(static fn(TestObject $o): ?string => $o->name, $page->getData()));
        $this->assertSame(['count' => 'viele'], $page->getData()[0]->getAdditionalProperties());
        $this->assertNull($page->getPagination()->getTotalElements());
        $this->assertSame('abc', $page->getPagination()->getAdditionalProperty('Hersteller:cursor'));
        $this->assertFalse($page->hasNextPage());
        $this->assertSame(42, $page->getLinks()->getAdditionalProperty('next'));
    }

    public function testSerializesPageWithoutSourceUris(): void
    {
        $this->server->json('/p1', [
            'data' => [['name' => 'a']],
            'pagination' => ['totalElements' => 2],
            'links' => ['next' => PageServer::BASE . '/p2', 'web' => 'https://ris.example.org/gremien'],
        ]);

        $this->assertSame(
            '{"data":[{"name":"a"}],"pagination":{"totalElements":2},'
            . '"links":{"next":"https://oparl.example.org/p2","web":"https://ris.example.org/gremien"}}',
            json_encode($this->server->get('/p1'), JSON_UNESCAPED_SLASHES),
        );
    }

    public function testSerializesEmptyPaginationAndLinksAsObjects(): void
    {
        $this->assertSame('{"data":[],"pagination":{},"links":{}}', json_encode(new OparlList()));
    }

    public function testCanNotBeReadWithoutElementClass(): void
    {
        $this->expectException(LogicException::class);
        (new ObjectMapper())->map([], OparlList::class);
    }

    public function testLinksCanNotBeReadWithoutElementClass(): void
    {
        $this->expectException(LogicException::class);
        OparlListLinks::read(new PropertyReader([], OparlListLinks::class, new ObjectMapper()));
    }
}
