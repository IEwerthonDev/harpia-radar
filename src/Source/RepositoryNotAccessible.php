<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use RuntimeException;

final class RepositoryNotAccessible extends RuntimeException
{
    /**
     * @param bool $affectsAllRepositories true quando o problema não é do repositório (token, limite da API)
     */
    public function __construct(string $message, public readonly bool $affectsAllRepositories = false)
    {
        parent::__construct($message);
    }
}
