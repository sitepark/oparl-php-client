<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use Http\Discovery\Strategy\DiscoveryStrategy;
use Psr\Http\Client\ClientInterface;

/**
 * Lets `php-http/discovery` find the {@see MockHttpClient}, as it would find an installed client.
 */
final class MockClientStrategy implements DiscoveryStrategy
{
    public static ?MockHttpClient $client = null;

    /**
     * @return list<array{class: callable, condition: string}>
     */
    public static function getCandidates($type): array
    {
        if ($type !== ClientInterface::class) {
            return [];
        }
        return [[
            'class' => static fn(): MockHttpClient => self::$client ??= new MockHttpClient(),
            'condition' => MockHttpClient::class,
        ]];
    }
}
