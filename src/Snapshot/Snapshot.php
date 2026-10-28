<?php

declare(strict_types=1);

namespace HarpiaRadar\Snapshot;

use DateTimeImmutable;
use DateTimeZone;

final readonly class Snapshot
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param list<ProjectResult> $projects
     */
    public function __construct(
        public DateTimeImmutable $generatedAt,
        public int $windowDays,
        public array $projects,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'schema_version' => self::SCHEMA_VERSION,
            'window_days' => $this->windowDays,
            'projects' => array_map(static fn (ProjectResult $p): array => $p->toArray(), $this->projects),
        ];
    }
}
