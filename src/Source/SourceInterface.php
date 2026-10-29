<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use DateTimeImmutable;
use HarpiaRadar\Catalog\RepoRef;

interface SourceInterface
{
    /**
     * @throws RepositoryNotAccessible
     */
    public function fetch(RepoRef $repo, DateTimeImmutable $since): RepositoryData;
}
