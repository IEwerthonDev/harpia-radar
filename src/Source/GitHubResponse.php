<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

final readonly class GitHubResponse
{
    public function __construct(
        public int $status,
        public mixed $data,
        public ?string $nextUrl,
    ) {
    }
}
