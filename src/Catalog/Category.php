<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

final readonly class Category
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
