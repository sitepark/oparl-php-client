<?php

declare(strict_types=1);

namespace SP\OparlClient\V1\Objects;

/**
 * Maps the `type` URLs of OParl objects to the classes representing them.
 */
final class OparlTypes
{
    /**
     * Namespace of the OParl 1.1 object types.
     */
    public const NAMESPACE = 'https://schema.oparl.org/1.1/';

    /**
     * Type URLs of OParl 1.0 and 1.1, e.g. `https://schema.oparl.org/1.1/Body`.
     */
    private const TYPE_PATTERN = '~^https?://schema\.oparl\.org/1\.[01]/(\w+)/?$~';

    /**
     * @var array<string, class-string<OparlObjectV1>>
     */
    private const CLASSES = [
        'AgendaItem' => OparlAgendaItem::class,
        'Body' => OparlBody::class,
        'Consultation' => OparlConsultation::class,
        'File' => OparlFile::class,
        'LegislativeTerm' => OparlLegislativeTerm::class,
        'Location' => OparlLocation::class,
        'Meeting' => OparlMeeting::class,
        'Membership' => OparlMembership::class,
        'Organization' => OparlOrganization::class,
        'Paper' => OparlPaper::class,
        'Person' => OparlPerson::class,
        'System' => OparlSystem::class,
    ];

    private function __construct() {}

    /**
     * Returns the class for the given `type` URL. Type URLs of OParl 1.0 and 1.1 are accepted.
     *
     * @return class-string<OparlObjectV1>|null the class, or `null` if the type is unknown
     */
    public static function classOf(?string $type): ?string
    {
        if ($type === null || preg_match(self::TYPE_PATTERN, trim($type), $match) !== 1) {
            return null;
        }
        return self::CLASSES[$match[1]] ?? null;
    }

    /**
     * Returns the OParl 1.1 `type` URL for the given class.
     *
     * @return string|null the type URL, or `null` if the class represents no OParl object type
     */
    public static function typeOf(string $class): ?string
    {
        $name = array_search($class, self::CLASSES, true);
        return $name !== false ? self::NAMESPACE . $name : null;
    }
}
