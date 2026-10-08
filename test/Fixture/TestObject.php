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
final readonly class TestObject extends OparlObject
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
        public ?string $name = null,
        public ?int $count = null,
        public ?bool $active = null,
        public ?string $url = null,
        public ?array $tags = null,
        public ?array $links = null,
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $day = null,
        public ?array $geo = null,
        public ?OparlReference $ref = null,
        public ?array $refs = null,
        public ?TestObject $child = null,
        public ?array $children = null,
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
