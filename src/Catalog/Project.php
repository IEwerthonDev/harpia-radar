<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

final readonly class Project
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public RepoRef $repo,
        public string $category,
        public string $description,
        public ?string $language = null,
        public ?string $replaces = null,
        public array $tags = [],
    ) {
    }
}
