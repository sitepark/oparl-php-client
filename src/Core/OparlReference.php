<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use Closure;
use JsonSerializable;
use LogicException;
use SP\OparlClient\Core\Query\QueryParam;

/**
 * Reference to another OParl object or to a list of objects, given by its URL. The referenced
 * object is only requested when the reference is resolved:
 *
 * ```php
 * $reference = $consultation->getMeeting();
 * $url = $reference->getUri();  // no request
 * $meeting = $reference->get(); // request
 * ```
 *
 * References are created when an object is read by an `OparlClient` and are resolved through that
 * client. A reference is immutable.
 *
 * @template-covariant T the type of the referenced object, e.g. `OparlMeeting` or
 *     `OparlList<OparlPaper>`
 */
final class OparlReference implements JsonSerializable
{
    /**
     * Creates a reference. Usually references are created by the client while reading an object.
     *
     * @param string $uri the URL of the referenced object
     * @param (Closure(string): T)|null $loader requests the object at the given URL; without one,
     *     {@see self::get()} fails
     */
    public function __construct(
        private readonly string $uri,
        private readonly ?Closure $loader = null,
    ) {}

    /**
     * Returns the URL of the referenced object. A reference is serialized as this URL.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Requests the referenced object.
     *
     * @return T
     * @throws OparlException if the request fails
     * @throws LogicException if the reference was not created by a client
     */
    public function get(): mixed
    {
        if ($this->loader === null) {
            throw new LogicException(
                'No loader set for the reference to ' . $this->uri
                . ', references are resolved through the OparlClient that read them',
            );
        }
        return ($this->loader)($this->uri);
    }

    /**
     * Returns a copy of this reference with the given URL parameters appended, e.g. filters for a
     * list:
     *
     * ```php
     * $body->getPaper()->withQueryParams(Modified::since($date), OmitInternal::true())->get();
     * ```
     *
     * The parameters are URL-encoded; the existing URL, including its query and fragment, is kept
     * unchanged. This reference itself is not modified.
     *
     * @return self<T>
     */
    public function withQueryParams(?QueryParam ...$params): self
    {
        $query = QueryParam::join(...$params);
        if ($query === '') {
            return $this;
        }
        return new self(self::appendQuery($this->uri, $query), $this->loader);
    }

    /**
     * Appends the already encoded `$query` to the query of `$uri`. Works on the raw string so that
     * the existing parts of the URL, e.g. of a `next` link, stay unchanged.
     */
    private static function appendQuery(string $uri, string $query): string
    {
        $hash = strpos($uri, '#');
        $base = $hash === false ? $uri : substr($uri, 0, $hash);
        $fragment = $hash === false ? '' : substr($uri, $hash);
        if (!str_contains($base, '?')) {
            $separator = '?';
        } elseif (str_ends_with($base, '?') || str_ends_with($base, '&')) {
            $separator = '';
        } else {
            $separator = '&';
        }
        return $base . $separator . $query . $fragment;
    }

    public function jsonSerialize(): string
    {
        return $this->uri;
    }
}
