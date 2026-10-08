<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Fixture;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Records log messages with their placeholders replaced.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string}>
     */
    public array $records = [];

    /**
     * @param mixed $level
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = is_scalar($value) ? (string) $value : get_debug_type($value);
        }
        $this->records[] = [
            'level' => is_string($level) ? $level : get_debug_type($level),
            'message' => strtr((string) $message, $replacements),
        ];
    }

    /**
     * @return list<string>
     */
    public function messages(string $level): array
    {
        $messages = [];
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                $messages[] = $record['message'];
            }
        }
        return $messages;
    }
}
