<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

final readonly class Catalog
{
    /**
     * @param list<Category> $categories
     * @param list<Project> $projects
     */
    public function __construct(
        public array $categories,
        public array $projects,
    ) {
    }
}
