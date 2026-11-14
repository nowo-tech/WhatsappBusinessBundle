<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that keeps every record, so tests can assert what was logged and that no secret was.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    /**
     * Stores the record.
     *
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * Returns `[level, message]` for every record.
     *
     * @return list<array{mixed, string}>
     */
    public function levelsAndMessages(): array
    {
        $out = [];
        foreach ($this->records as $record) {
            $out[] = [$record['level'], $record['message']];
        }

        return $out;
    }
}
