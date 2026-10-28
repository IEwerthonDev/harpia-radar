<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use DateTimeImmutable;
use HarpiaRadar\Catalog\RepoRef;

/**
 * Adaptador de uma forja (GitHub na v1; Codeberg e GitLab na v2).
 */
interface SourceInterface
{
    /**
     * @throws RepositoryNotAccessible
     */
    public function fetch(RepoRef $repo, DateTimeImmutable $since): RepositoryData;
}
