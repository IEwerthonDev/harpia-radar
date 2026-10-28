<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use HarpiaRadar\Catalog\RepoRef;
use HarpiaRadar\Config\GitHubToken;
use InvalidArgumentException;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GET autenticado na API REST do GitHub, com as falhas traduzidas em RepositoryNotAccessible.
 */
final readonly class GitHubApi
{
    public function __construct(
        private HttpClientInterface $client,
        private GitHubToken $token,
        private string $baseUrl = 'https://api.github.com',
    ) {
    }

    /**
     * @param string $path caminho dentro do repositório ("", "/commits"…) ou URL absoluta de paginação
     * @param array<string, string|int> $query
     *
     * @throws RepositoryNotAccessible
     */
    public function get(RepoRef $repo, string $path, array $query = [], bool $allowNotFound = false): GitHubResponse
    {
        if ($repo->forge !== 'github') {
            throw new InvalidArgumentException(sprintf('Not a GitHub repository: %s.', $repo));
        }

        $url = str_starts_with($path, 'https://')
            ? $path
            : sprintf('%s/repos/%s/%s%s', $this->baseUrl, rawurlencode($repo->owner), rawurlencode($repo->name), $path);

        try {
            $response = $this->client->request('GET', $url, [
                'query' => $query,
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
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new RepositoryNotAccessible(sprintf('Falha de rede ao consultar %s: %s', $repo, $e->getMessage()));
        }

        if ($status === 404 && $allowNotFound) {
            return new GitHubResponse($status, null, null);
        }

        self::assertSuccessful($repo, $status, $headers);

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RepositoryNotAccessible(sprintf('O GitHub devolveu uma resposta inválida para %s.', $repo));
        }

        return new GitHubResponse($status, $data, self::nextUrl($headers['link'][0] ?? null));
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @throws RepositoryNotAccessible
     */
    private static function assertSuccessful(RepoRef $repo, int $status, array $headers): void
    {
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
            $status === 409 => throw new RepositoryNotAccessible(
                sprintf('O repositório %s está vazio (HTTP 409).', $repo),
            ),
            $status >= 500 => throw new RepositoryNotAccessible(
                sprintf('O GitHub respondeu com erro ao consultar %s (HTTP %d).', $repo, $status),
            ),
            default => throw new RepositoryNotAccessible(
                sprintf('Resposta inesperada do GitHub ao consultar %s (HTTP %d).', $repo, $status),
            ),
        };
    }

    private static function nextUrl(?string $link): ?string
    {
        if ($link === null || preg_match('~<([^>]+)>;\s*rel="next"~', $link, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
