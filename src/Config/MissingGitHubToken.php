<?php

declare(strict_types=1);

namespace HarpiaRadar\Config;

use RuntimeException;

final class MissingGitHubToken extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'A variável de ambiente GITHUB_TOKEN não está definida. O Radar sempre usa um token: '
            . 'sem autenticação, o limite da API do GitHub é baixo demais. Gere um token de leitura em '
            . 'https://github.com/settings/personal-access-tokens/new e rode: export GITHUB_TOKEN=seu_token',
        );
    }
}
