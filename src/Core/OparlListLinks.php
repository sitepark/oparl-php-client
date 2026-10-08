<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use LogicException;
use SP\OparlClient\Internal\PropertyReader;

/**
 * Links to other pages of a list. Only `next` is mandatory, on all pages except the last one; all
 * other links may be `null`.
 *
 * @template-covariant T of OparlObject = never the type of the elements of the list; `never` for links
 *     without any page
 */
final class OparlListLinks extends OparlObject
{
    /**
     * @param OparlReference<OparlList<T>>|null $first
     * @param OparlReference<OparlList<T>>|null $prev
     * @param OparlReference<OparlList<T>>|null $self
     * @param OparlReference<OparlList<T>>|null $next
     * @param OparlReference<OparlList<T>>|null $last
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?OparlReference $first = null,
        private readonly ?OparlReference $prev = null,
        private readonly ?OparlReference $self = null,
        private readonly ?OparlReference $next = null,
        private readonly ?OparlReference $last = null,
        private readonly ?string $web = null,
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
    }

    /**
     * Returns links without any page, e.g. for a page the server sent without links.
     *
     * @return self<never>
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * Links are read with the element class of their list, see {@see self::readLinks()}.
     */
    public static function read(PropertyReader $reader): never
    {
        throw new LogicException('Links of a list are read with the element class of the list');
    }

    /**
     * @template E of OparlObject
     * @param class-string<E> $elementClass the class of the elements of the list
     * @return self<E>
     * @internal used by the client to map responses
     */
    public static function readLinks(PropertyReader $reader, string $elementClass): self
    {
        return new self(
            first: $reader->listReference('first', $elementClass),
            prev: $reader->listReference('prev', $elementClass),
            self: $reader->listReference('self', $elementClass),
            next: $reader->listReference('next', $elementClass),
            last: $reader->listReference('last', $elementClass),
            web: $reader->url('web'),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    /**
     * Returns the first page.
     *
     * @return OparlReference<OparlList<T>>|null
     */
    public function getFirst(): ?OparlReference
    {
        return $this->first;
    }

    /**
     * Returns the previous page.
     *
     * @return OparlReference<OparlList<T>>|null
     */
    public function getPrev(): ?OparlReference
    {
        return $this->prev;
    }

    /**
     * Returns the canonical URL of this page.
     *
     * @return OparlReference<OparlList<T>>|null
     */
    public function getSelf(): ?OparlReference
    {
        return $this->self;
    }

    /**
     * Returns the next page, or `null` on the last page.
     *
     * @return OparlReference<OparlList<T>>|null
     */
    public function getNext(): ?OparlReference
    {
        return $this->next;
    }

    /**
     * Returns the last page.
     *
     * @return OparlReference<OparlList<T>>|null
     */
    public function getLast(): ?OparlReference
    {
        return $this->last;
    }

    /**
     * Returns the URL of a website showing this page, e.g. in the council information system.
     */
    public function getWeb(): ?string
    {
        return $this->web;
    }

    protected function mappedProperties(): array
    {
        return [
            'first' => $this->first,
            'prev' => $this->prev,
            'self' => $this->self,
            'next' => $this->next,
            'last' => $this->last,
            'web' => $this->web,
        ];
    }
}
