<?php

declare(strict_types=1);

namespace HarpiaRadar\Config;

final readonly class GitHubToken
{
    private function __construct(public string $value)
    {
    }

    /**
     * @param string|null $value conteúdo da variável GITHUB_TOKEN
     *
     * @throws MissingGitHubToken
     */
    public static function fromEnvironment(?string $value): self
    {
        $value = trim($value ?? '');
        if ($value === '') {
            throw new MissingGitHubToken();
        }

        return new self($value);
    }
}
