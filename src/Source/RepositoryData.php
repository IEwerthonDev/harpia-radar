<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use DateTimeImmutable;

/**
 * Dados brutos de um repositório, como vieram da forja.
 */
final readonly class RepositoryData
{
    /**
     * @param list<Commit> $commits commits desde o início da janela
     */
    public function __construct(
        public bool $archived,
        public ?string $license,
        public int $openIssues,
        public DateTimeImmutable $lastCommitAt,
        public ?DateTimeImmutable $lastReleaseAt,
        public array $commits,
    ) {
    }
}
