<?php

declare(strict_types=1);

namespace SP\OparlClient\Core\Query;

use InvalidArgumentException;

/**
 * Requests a maximum number of objects per list page via the URL parameter `limit`.
 *
 * According to the OParl specification, a server is not obliged to respect it, so clients must
 * not rely on the page size.
 */
final readonly class Limit extends QueryParam
{
    public const PARAM_NAME = 'limit';

    private function __construct(int $limit)
    {
        parent::__construct(self::PARAM_NAME, (string) $limit);
    }

    /**
     * @param int $limit the maximum number of objects per page
     * @throws InvalidArgumentException if the limit is zero or negative
     */
    public static function of(int $limit): self
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('limit must be positive: ' . $limit);
        }
        return new self($limit);
    }
}
