<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use DateTimeImmutable;

final readonly class Commit
{
    public function __construct(
        public ?string $login,
        public ?string $email,
        public DateTimeImmutable $committedAt,
    ) {
    }
}
