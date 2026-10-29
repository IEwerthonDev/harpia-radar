<?php

declare(strict_types=1);

namespace HarpiaRadar\Metrics;

use DateTimeImmutable;
use DateTimeZone;

final readonly class Metrics
{
    public function __construct(
        public DateTimeImmutable $lastCommitAt,
        public ?DateTimeImmutable $lastReleaseAt,
        public int $commits,
        public int $authors,
        public ?float $topAuthorShare,
        public ?int $busFactor,
        public bool $archived,
        public ?string $license,
        public int $openIssues,
    ) {
    }

    /**
     * As chaves seguem a spec, mesmo que a janela mude em config/health.yaml.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'last_commit_at' => self::day($this->lastCommitAt),
            'last_release_at' => $this->lastReleaseAt === null ? null : self::day($this->lastReleaseAt),
            'commits_90d' => $this->commits,
            'authors_90d' => $this->authors,
            'top_author_share' => $this->topAuthorShare,
            'bus_factor' => $this->busFactor,
            'archived' => $this->archived,
            'license' => $this->license,
            'open_issues' => $this->openIssues,
        ];
    }

    private static function day(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}
