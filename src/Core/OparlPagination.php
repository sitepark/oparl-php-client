<?php

declare(strict_types=1);

namespace SP\OparlClient\Core;

use SP\OparlClient\Internal\PropertyReader;

/**
 * Information about the number of elements and pages of a list. All values are optional; values
 * not sent by the server are `null`.
 */
final class OparlPagination extends OparlObject
{
    /**
     * @param array<string, mixed> $additionalProperties
     */
    public function __construct(
        private readonly ?int $totalElements = null,
        private readonly ?int $elementsPerPage = null,
        private readonly ?int $currentPage = null,
        private readonly ?int $totalPages = null,
        array $additionalProperties = [],
    ) {
        parent::__construct($additionalProperties);
    }

    public static function read(PropertyReader $reader): self
    {
        return new self(
            totalElements: $reader->int('totalElements'),
            elementsPerPage: $reader->int('elementsPerPage'),
            currentPage: $reader->int('currentPage'),
            totalPages: $reader->int('totalPages'),
            additionalProperties: $reader->additionalProperties(),
        );
    }

    /**
     * Returns the total number of elements; may change until the following pages are fetched.
     */
    public function getTotalElements(): ?int
    {
        return $this->totalElements;
    }

    /**
     * Returns the number of elements per page; the same for all pages except the last one.
     */
    public function getElementsPerPage(): ?int
    {
        return $this->elementsPerPage;
    }

    /**
     * Returns the number of this page.
     */
    public function getCurrentPage(): ?int
    {
        return $this->currentPage;
    }

    /**
     * Returns the total number of pages.
     */
    public function getTotalPages(): ?int
    {
        return $this->totalPages;
    }

    protected function mappedProperties(): array
    {
        return [
            'totalElements' => $this->totalElements,
            'elementsPerPage' => $this->elementsPerPage,
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
        ];
    }
}
