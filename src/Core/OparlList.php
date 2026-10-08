<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use Generator;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Internal\PropertyReader;

/**
 * One page of an external object list, e.g. all meetings of a body. A server may split a list
 * into pages; {@see self::all()} iterates over the elements of all pages and fetches further pages
 * on demand:
 *
 * ```php
 * foreach ($body->getMeeting()->get()->all() as $meeting) {
 *     // ...
 * }
 * ```
 *
 * @template-covariant T of OparlObject the type of the elements, e.g. `OparlMeeting`
 */
final class OparlList extends OparlObject
{
    private readonly OparlPagination $pagination;

    /**
     * @var OparlListLinks<T>
     */
    private readonly OparlListLinks $links;

    /**
     * @param list<T> $data
     * @param OparlListLinks<T>|null $links
     * @param list<string> $sourceUris the URLs the page was loaded from, see
     *     {@see self::getSourceUris()}
     * @param LoggerInterface $logger logs when iterating over all pages ends early on a cycle
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly array $data = [],
        ?OparlPagination $pagination = null,
        ?OparlListLinks $links = null,
        private readonly array $sourceUris = [],
        private readonly LoggerInterface $logger = new NullLogger(),
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
        $this->pagination = $pagination ?? new OparlPagination();
        $this->links = $links ?? OparlListLinks::none();
    }

    /**
     * Pages are read with the class of their elements, see {@see self::readPage()}.
     */
    public static function read(PropertyReader $reader): never
    {
        throw new LogicException('List pages are read with the class of their elements');
    }

    /**
     * @template E of OparlObject
     * @param class-string<E> $elementClass the class of the elements
     * @return self<E>
     * @internal used by the client to map responses
     */
    public static function readPage(PropertyReader $reader, string $elementClass): self
    {
        return new self(
            data: $reader->objectList('data', $elementClass) ?? [],
            pagination: $reader->object('pagination', OparlPagination::class),
            links: $reader->objectWith(
                'links',
                OparlListLinks::class,
                static fn(PropertyReader $links): OparlListLinks => OparlListLinks::readLinks($links, $elementClass),
            ),
            logger: $reader->logger(),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    /**
     * Returns a copy of this page that knows the URLs it was loaded from.
     *
     * @return self<T>
     * @internal set by the client after loading the page
     */
    public function withSourceUris(string ...$sourceUris): self
    {
        return new self(
            $this->data,
            $this->pagination,
            $this->links,
            array_values(array_unique($sourceUris)),
            $this->logger,
            $this->getAdditionalProperties(),
        );
    }

    /**
     * Returns the elements of this page only, see {@see self::all()} for all pages.
     *
     * @return list<T>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns information about the number of elements and pages, as far as the server sends it.
     */
    public function getPagination(): OparlPagination
    {
        return $this->pagination;
    }

    /**
     * Returns the links to other pages of the list.
     *
     * @return OparlListLinks<T>
     */
    public function getLinks(): OparlListLinks
    {
        return $this->links;
    }

    /**
     * Returns the URLs this page was requested from; empty if the page was not loaded by a
     * client. Not part of the serialized page.
     *
     * @return list<string>
     */
    public function getSourceUris(): array
    {
        return $this->sourceUris;
    }

    /**
     * Returns whether there is a following page, i.e. this page has a `next` link.
     */
    public function hasNextPage(): bool
    {
        return $this->links->getNext() !== null;
    }

    /**
     * Fetches the following page.
     *
     * @return self<T>
     * @throws OparlException if the page can not be fetched
     * @throws LogicException if this is the last page
     */
    public function fetchNextPage(): self
    {
        $next = $this->links->getNext();
        if ($next === null) {
            throw new LogicException('This is the last page');
        }
        return $next->get();
    }

    /**
     * Returns the elements of this page and all following pages, e.g. for use in a `foreach`
     * loop. Following pages are fetched while iterating, each one when the elements of the
     * previous page have been consumed. Every call starts again with this page.
     *
     * If a page can not be fetched, the iteration fails with the {@see OparlException} of that
     * page, e.g. an {@see OparlHttpException}; {@see OparlException::getUri()} is the URL of the
     * page. Empty pages are skipped, and the iteration ends with a warning if a page links to a
     * page that has already been visited.
     *
     * @return Generator<int, T, mixed, void>
     */
    public function all(): Generator
    {
        $page = $this;
        $visited = array_fill_keys($this->pageUris(), true);
        while (true) {
            // not "yield from", which would repeat the keys 0, 1, ... on every page
            foreach ($page->data as $element) {
                yield $element;
            }
            $next = $page->links->getNext();
            if ($next === null) {
                return;
            }
            $nextUri = $next->getUri();
            if (isset($visited[$nextUri])) {
                $this->logger->warning(
                    'Stopping pagination, page {uri} has already been visited',
                    ['uri' => $nextUri],
                );
                return;
            }
            // a failed page is reported with its own exception, e.g. an OparlHttpException
            $page = $next->get();
            // the requested URL was new, but the page may name itself as a page already visited
            $pageUris = array_diff($page->pageUris(), [$nextUri]);
            if (array_intersect_key($visited, array_flip($pageUris)) !== []) {
                $this->logger->warning(
                    'Stopping pagination, page {uri} leads to a page that has already been visited',
                    ['uri' => $nextUri],
                );
                return;
            }
            $visited[$nextUri] = true;
            $visited += array_fill_keys($pageUris, true);
        }
    }

    /**
     * All known URLs of this page: the URLs it was loaded from and its `self` link.
     *
     * @return list<string>
     */
    private function pageUris(): array
    {
        $uris = $this->sourceUris;
        $self = $this->links->getSelf();
        if ($self !== null) {
            $uris[] = $self->getUri();
        }
        return $uris;
    }

    protected function mappedProperties(): array
    {
        return [
            'data' => $this->data,
            'pagination' => $this->pagination,
            'links' => $this->links,
        ];
    }
}
