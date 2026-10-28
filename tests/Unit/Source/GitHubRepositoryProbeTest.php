<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\RepoRef;
use HarpiaRadar\Config\GitHubToken;
use HarpiaRadar\Source\GitHubRepositoryProbe;
use HarpiaRadar\Source\RepositoryNotAccessible;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function probeWith(MockResponse $response, ?MockHttpClient &$client = null): GitHubRepositoryProbe
{
    $client = new MockHttpClient($response);

    return new GitHubRepositoryProbe($client, GitHubToken::fromEnvironment('test-token'));
}

function probeMessage(MockResponse $response): string
{
    try {
        probeWith($response)->assertAccessible(RepoRef::parse('github:nfephp-org/sped-nfe'));
    } catch (RepositoryNotAccessible $e) {
        return $e->getMessage();
    }

    throw new RuntimeException('Esperava RepositoryNotAccessible.');
}

it('accepts an accessible repository and authenticates the request', function (): void {
    $response = githubResponse('repos-sped-nfe.json');
    probeWith($response, $client)->assertAccessible(RepoRef::parse('github:nfephp-org/sped-nfe'));

    expect($client->getRequestsCount())->toBe(1)
        ->and($response->getRequestMethod())->toBe('GET')
        ->and($response->getRequestUrl())->toBe('https://api.github.com/repos/nfephp-org/sped-nfe')
        ->and($response->getRequestOptions()['headers'])->toContain(
            'Authorization: Bearer test-token',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        );
});

it('explains each failure', function (MockResponse $response, string $message): void {
    expect(probeMessage($response))->toBe($message);
})->with([
    'not found' => fn () => [
        githubResponse('repos-not-found.json', 404),
        'Repositório github:nfephp-org/sped-nfe não encontrado ou privado (HTTP 404).',
    ],
    'bad token' => fn () => [
        githubResponse('repos-bad-credentials.json', 401),
        'O GitHub recusou o token (HTTP 401). Confira se GITHUB_TOKEN está correto e dentro da validade.',
    ],
    'rate limit' => fn () => [
        githubResponse('repos-rate-limited.json', 403, ['x-ratelimit-remaining' => '0']),
        'Limite de requisições da API do GitHub atingido (HTTP 403). Tente de novo mais tarde.',
    ],
    'forbidden' => fn () => [
        githubResponse('repos-forbidden.json', 403, ['x-ratelimit-remaining' => '4999']),
        'Acesso negado ao repositório github:nfephp-org/sped-nfe (HTTP 403).',
    ],
    'server error' => fn () => [
        new MockResponse('', ['http_code' => 502]),
        'O GitHub respondeu com erro ao consultar github:nfephp-org/sped-nfe (HTTP 502).',
    ],
    'network' => fn () => [
        new MockResponse('', ['error' => 'Idle timeout reached']),
        'Falha de rede ao consultar github:nfephp-org/sped-nfe: Idle timeout reached',
    ],
]);

it('flags failures that affect every repository', function (MockResponse $response, bool $affectsAll): void {
    try {
        probeWith($response)->assertAccessible(RepoRef::parse('github:nfephp-org/sped-nfe'));
    } catch (RepositoryNotAccessible $e) {
        expect($e->affectsAllRepositories)->toBe($affectsAll);

        return;
    }

    throw new RuntimeException('Esperava RepositoryNotAccessible.');
})->with([
    'not found' => fn () => [githubResponse('repos-not-found.json', 404), false],
    'bad token' => fn () => [githubResponse('repos-bad-credentials.json', 401), true],
    'rate limit' => fn () => [githubResponse('repos-rate-limited.json', 403, ['x-ratelimit-remaining' => '0']), true],
    'forbidden' => fn () => [githubResponse('repos-forbidden.json', 403), false],
]);
