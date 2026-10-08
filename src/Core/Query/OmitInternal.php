<?php

declare(strict_types=1);

namespace SP\OparlClient\Core\Query;

/**
 * Asks the server to omit internal lists, e.g. `auxiliaryFile` of papers or `agendaItem` of
 * meetings, via the URL parameter `omit_internal`. Reduces the size of list pages considerably,
 * e.g. when updating a local copy.
 */
final class OmitInternal extends QueryParam
{
    public const PARAM_NAME = 'omit_internal';

    private function __construct(string $value)
    {
        parent::__construct(self::PARAM_NAME, $value);
    }

    /**
     * `omit_internal=true`: internal lists are omitted.
     */
    public static function true(): self
    {
        return new self('true');
    }

    /**
     * `omit_internal=false`: internal lists are included, the default of the specification.
     */
    public static function false(): self
    {
        return new self('false');
    }
}
