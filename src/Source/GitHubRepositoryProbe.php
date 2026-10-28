<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use HarpiaRadar\Catalog\RepoRef;
use HarpiaRadar\Config\GitHubToken;
use InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Confere se um repositório existe e é público, com GET /repos/{owner}/{repo}.
 */
final readonly class GitHubRepositoryProbe
{
    public function __construct(
        private HttpClientInterface $client,
        private GitHubToken $token,
        private string $baseUrl = 'https://api.github.com',
    ) {
    }

    /**
     * @throws RepositoryNotAccessible
     */
    public function assertAccessible(RepoRef $repo): void
    {
        if ($repo->forge !== 'github') {
            throw new InvalidArgumentException(sprintf('Not a GitHub repository: %s.', $repo));
        }

        try {
            $response = $this->client->request('GET', sprintf(
                '%s/repos/%s/%s',
                $this->baseUrl,
                rawurlencode($repo->owner),
                rawurlencode($repo->name),
            ), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token->value,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'harpia-radar',
                ],
                'timeout' => 10,
            ]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
        } catch (TransportExceptionInterface $e) {
            throw new RepositoryNotAccessible(sprintf('Falha de rede ao consultar %s: %s', $repo, $e->getMessage()));
        }

        $rateLimited = $status === 429 || ($status === 403 && ($headers['x-ratelimit-remaining'][0] ?? null) === '0');

        match (true) {
            $status >= 200 && $status < 300 => null,
            $status === 401 => throw new RepositoryNotAccessible(
                'O GitHub recusou o token (HTTP 401). Confira se GITHUB_TOKEN está correto e dentro da validade.',
                affectsAllRepositories: true,
            ),
            $rateLimited => throw new RepositoryNotAccessible(
                sprintf('Limite de requisições da API do GitHub atingido (HTTP %d). Tente de novo mais tarde.', $status),
                affectsAllRepositories: true,
            ),
            $status === 403 => throw new RepositoryNotAccessible(
                sprintf('Acesso negado ao repositório %s (HTTP 403).', $repo),
            ),
            $status === 404 => throw new RepositoryNotAccessible(
                sprintf('Repositório %s não encontrado ou privado (HTTP 404).', $repo),
            ),
            $status >= 500 => throw new RepositoryNotAccessible(
                sprintf('O GitHub respondeu com erro ao consultar %s (HTTP %d).', $repo, $status),
            ),
            default => throw new RepositoryNotAccessible(
                sprintf('Resposta inesperada do GitHub ao consultar %s (HTTP %d).', $repo, $status),
            ),
        };
    }
}
