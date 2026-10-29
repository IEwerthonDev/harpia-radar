<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\RepoRef;
use HarpiaRadar\Config\GitHubToken;
use HarpiaRadar\Source\GitHubApi;
use HarpiaRadar\Source\GitHubSource;
use HarpiaRadar\Source\RepositoryNotAccessible;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Contracts\HttpClient\HttpClientInterface;

const REPOS = ['nfephp-org/sped-nfe' => 'sped-nfe', 'renatomb/php_qrcode_pix' => 'php-qrcode-pix'];

function source(HttpClientInterface $client): GitHubSource
{
    return new GitHubSource(new GitHubApi($client, GitHubToken::fromEnvironment('test-token')));
}

function fetchRepo(HttpClientInterface $client, string $repo): HarpiaRadar\Source\RepositoryData
{
    return source($client)->fetch(RepoRef::parse($repo), utc('2026-07-30T06:00:00Z'));
}

it('collects every field of a repository with releases', function (): void {
    $data = fetchRepo(githubFixtureClient(REPOS), 'github:nfephp-org/sped-nfe');
    $latest = json_decode((string) file_get_contents(fixturePath('github/sped-nfe/commits-latest.json')), true);

    expect($data->archived)->toBeFalse()
        ->and($data->license)->toBe('NOASSERTION')
        ->and($data->openIssues)->toBeInt()
        ->and($data->lastCommitAt)->toEqual(utc($latest[0]['commit']['committer']['date']))
        ->and($data->commits)->toHaveCount(35);
});

it('uses the tag when it is newer than the last release', function (): void {
    $data = fetchRepo(githubFixtureClient(REPOS), 'github:nfephp-org/sped-nfe');

    expect($data->lastReleaseAt)->toEqual(utc('2026-10-02T12:40:30Z'));
});

it('uses the release when it is newer than the last tag', function (): void {
    $client = githubFixtureClient(REPOS, [
        'sped-nfe:release-latest' => new MockResponse('{"tag_name": "v6.0.0", "published_at": "2026-10-20T10:00:00Z"}'),
    ]);

    expect(fetchRepo($client, 'github:nfephp-org/sped-nfe')->lastReleaseAt)->toEqual(utc('2026-10-20T10:00:00Z'));
});

it('keeps login and email of each commit', function (): void {
    $data = fetchRepo(githubFixtureClient(REPOS), 'github:renatomb/php_qrcode_pix');

    expect($data->commits)->toHaveCount(2)
        ->and($data->commits[0]->login)->toBe('author-8')
        ->and($data->commits[1]->login)->toBeNull()
        ->and($data->commits[1]->email)->toBe('author-9@users.example')
        ->and($data->commits[1]->committedAt)->toEqual(utc('2026-08-18T02:26:53Z'));
});

it('leaves the release empty without releases and tags', function (): void {
    $data = fetchRepo(githubFixtureClient(REPOS), 'github:renatomb/php_qrcode_pix');

    expect($data->lastReleaseAt)->toBeNull()
        ->and($data->license)->toBe('CC0-1.0');
});

it('falls back to the most recent tag', function (): void {
    $client = githubFixtureClient(REPOS, [
        'php-qrcode-pix:tags' => new MockResponse('[{"name": "v1.2.0", "commit": {"sha": "abc123"}}]'),
        'php-qrcode-pix:tag-commit' => new MockResponse(
            '{"sha": "abc123", "commit": {"author": {"email": "x@users.example", "date": "2024-05-01T10:00:00Z"}, "committer": {"date": "2024-05-02T10:00:00Z"}}, "author": null}',
        ),
    ]);

    expect(fetchRepo($client, 'github:renatomb/php_qrcode_pix')->lastReleaseAt)->toEqual(utc('2024-05-02T10:00:00Z'));
});

it('follows pagination', function (): void {
    $commits = json_decode((string) file_get_contents(fixturePath('github/sped-nfe/commits-since-page-1.json')), true);
    $next = 'https://api.github.com/repositories/39446330/commits?since=2026-07-30T06%3A00%3A00Z&per_page=100&page=2';
    $firstPage = null;
    $client = githubFixtureClient(REPOS, [
        'sped-nfe:commits-since-page-1' => function () use ($commits, $next, &$firstPage): MockResponse {
            return $firstPage = new MockResponse((string) json_encode(array_slice($commits, 0, 20)), [
                'response_headers' => ['link' => "<{$next}>; rel=\"next\", <{$next}>; rel=\"last\""],
            ]);
        },
        'next-page' => new MockResponse((string) json_encode(array_slice($commits, 20))),
    ]);

    $data = fetchRepo($client, 'github:nfephp-org/sped-nfe');

    expect($data->commits)->toHaveCount(35)
        ->and($firstPage?->getRequestUrl())
        ->toBe('https://api.github.com/repos/nfephp-org/sped-nfe/commits?since=2026-07-30T06:00:00Z&per_page=100');
});

it('fails for a missing repository', function (): void {
    fetchRepo(githubFixtureClient(REPOS), 'github:harpia-test/gone');
})->throws(RepositoryNotAccessible::class, 'github:harpia-test/gone não encontrado');

it('fails for a repository without commits', function (): void {
    $client = githubFixtureClient(REPOS, ['sped-nfe:commits-latest' => new MockResponse('[]')]);

    fetchRepo($client, 'github:nfephp-org/sped-nfe');
})->throws(RepositoryNotAccessible::class, 'não tem commits');

it('fails for an empty repository', function (): void {
    $client = githubFixtureClient(REPOS, [
        'sped-nfe:commits-latest' => new MockResponse('{"message": "Git Repository is empty."}', ['http_code' => 409]),
    ]);

    fetchRepo($client, 'github:nfephp-org/sped-nfe');
})->throws(RepositoryNotAccessible::class, 'está vazio');

it('retries transient failures', function (): void {
    $attempts = 0;
    $client = githubFixtureClient(REPOS, [
        'sped-nfe:repo' => function () use (&$attempts): MockResponse {
            return ++$attempts === 1
                ? new MockResponse('', ['http_code' => 502])
                : new MockResponse((string) file_get_contents(fixturePath('github/sped-nfe/repo.json')));
        },
    ]);
    $retrying = new RetryableHttpClient($client, new GenericRetryStrategy(delayMs: 0), maxRetries: 2);

    expect(fetchRepo($retrying, 'github:nfephp-org/sped-nfe')->license)->toBe('NOASSERTION')
        ->and($attempts)->toBe(2);
});
