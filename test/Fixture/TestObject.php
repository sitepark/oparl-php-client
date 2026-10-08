<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use DateTimeImmutable;
use SP\OparlClient\Core\OparlObject;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\Internal\OparlTime;
use SP\OparlClient\Internal\PropertyReader;

/**
 * An object with one property of every kind the reader supports.
 */
final class TestObject extends OparlObject
{
    /**
     * @param list<string>|null $tags
     * @param list<string>|null $links
     * @param array<mixed>|null $geo
     * @param OparlReference<TestObject>|null $ref
     * @param list<OparlReference<TestObject>>|null $refs
     * @param list<TestObject>|null $children
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $count = null,
        public readonly ?bool $active = null,
        public readonly ?string $url = null,
        public readonly ?array $tags = null,
        public readonly ?array $links = null,
        public readonly ?DateTimeImmutable $start = null,
        public readonly ?DateTimeImmutable $day = null,
        public readonly ?array $geo = null,
        public readonly ?OparlReference $ref = null,
        public readonly ?array $refs = null,
        public readonly ?TestObject $child = null,
        public readonly ?array $children = null,
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
    }

    public static function read(PropertyReader $reader): self
    {
        return new self(
            name: $reader->string('name'),
            count: $reader->int('count'),
            active: $reader->bool('active'),
            url: $reader->url('url'),
            tags: $reader->stringList('tags'),
            links: $reader->urlList('links'),
            start: $reader->dateTime('start'),
            day: $reader->date('day'),
            geo: $reader->json('geo'),
            ref: $reader->reference('ref', self::class),
            refs: $reader->referenceList('refs', self::class),
            child: $reader->object('child', self::class),
            children: $reader->objectList('children', self::class),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    protected function mappedProperties(): array
    {
        return [
            'name' => $this->name,
            'count' => $this->count,
            'active' => $this->active,
            'url' => $this->url,
            'tags' => $this->tags,
            'links' => $this->links,
            'start' => OparlTime::formatDateTime($this->start),
            'day' => OparlTime::formatDate($this->day),
            'geo' => $this->geo,
            'ref' => $this->ref,
            'refs' => $this->refs,
            'child' => $this->child,
            'children' => $this->children,
        ];
    }
}
