<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use HarpiaRadar\Catalog\RepoRef;

/**
 * Confere se um repositório existe e é público, com GET /repos/{owner}/{repo}.
 */
final readonly class GitHubRepositoryProbe
{
    public function __construct(private GitHubApi $api)
    {
    }

    /**
     * @throws RepositoryNotAccessible
     */
    public function assertAccessible(RepoRef $repo): void
    {
        $this->api->get($repo, '');
    }
}
