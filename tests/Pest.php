<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;

function fixturePath(string $path): string
{
    return __DIR__ . '/fixtures/' . $path;
}

function projectRoot(string $path = ''): string
{
    return dirname(__DIR__) . ($path === '' ? '' : '/' . $path);
}

/**
 * @return array<string, mixed>
 */
function validCatalogData(): array
{
    /** @var array<string, mixed> */
    return Yaml::parseFile(fixturePath('catalog/valid.yaml'));
}

/**
 * Grava um catálogo num arquivo temporário e devolve o caminho.
 *
 * @param array<string, mixed> $data
 */
function writeCatalog(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'radar-catalog-') . '.yaml';
    file_put_contents($path, Yaml::dump($data, 4));

    return $path;
}

/**
 * @param array<string, string|list<string>> $headers
 */
function githubResponse(string $fixture, int $status = 200, array $headers = []): MockResponse
{
    return new MockResponse(
        (string) file_get_contents(fixturePath('github/' . $fixture)),
        ['http_code' => $status, 'response_headers' => $headers],
    );
}

/**
 * Configuração de saúde com os valores da spec, para os testes não dependerem do arquivo do repositório.
 *
 * @param array<string, mixed> $overrides
 */
function healthConfig(array $overrides = []): HarpiaRadar\Config\HealthConfig
{
    return new HarpiaRadar\Config\HealthConfig(...array_merge([
        'windowDays' => 90,
        'releaseMaxAgeMonths' => 12,
        'minCommits' => 10,
        'wellDistributedBusFactor' => 3,
        'fewPeopleBusFactor' => 2,
        'maxTopAuthorShare' => 0.75,
        'botSuffix' => '[bot]',
        'botLogins' => ['dependabot', 'renovate'],
    ], $overrides));
}

function utc(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('UTC'));
}

/**
 * Responde às requisições do GitHub a partir das fixtures de tests/fixtures/github/<projeto>/.
 * Repositórios fora do mapa respondem 404.
 *
 * @param array<string, string> $repos "dono/nome" => pasta da fixture
 * @param array<string, MockResponse|callable(): MockResponse> $overrides "pasta:rota" => resposta; "next-page" para links de paginação
 */
function githubFixtureClient(array $repos, array $overrides = []): Symfony\Component\HttpClient\MockHttpClient
{
    return new Symfony\Component\HttpClient\MockHttpClient(
        static function (string $method, string $url) use ($repos, $overrides): MockResponse {
            $parts = parse_url($url);
            if (str_starts_with($parts['path'] ?? '', '/repositories/')) {
                $next = $overrides['next-page'] ?? throw new RuntimeException('Rota inesperada: ' . $url);

                return $next instanceof MockResponse ? $next : $next();
            }

            if (preg_match('~^/repos/([^/]+/[^/]+)(.*)$~', $parts['path'] ?? '', $matches) !== 1) {
                throw new RuntimeException('Rota inesperada: ' . $url);
            }

            [, $repo, $path] = $matches;
            parse_str($parts['query'] ?? '', $query);
            $project = $repos[$repo] ?? null;
            if ($project === null) {
                return githubResponse('repos-not-found.json', 404);
            }

            $route = match (true) {
                $path === '' => 'repo',
                $path === '/commits' && isset($query['since']) => 'commits-since-page-' . ($query['page'] ?? '1'),
                $path === '/commits' => 'commits-latest',
                $path === '/releases/latest' => 'release-latest',
                $path === '/tags' => 'tags',
                str_starts_with($path, '/commits/') => 'tag-commit',
                default => throw new RuntimeException('Rota inesperada: ' . $url),
            };

            $override = $overrides["{$project}:{$route}"] ?? null;
            if ($override !== null) {
                return $override instanceof MockResponse ? $override : $override();
            }

            $file = fixturePath("github/{$project}/{$route}.json");
            if (!is_file($file)) {
                throw new RuntimeException('Fixture ausente: ' . $file);
            }

            $body = (string) file_get_contents($file);
            $status = str_contains($body, '"message": "Not Found"') ? 404 : 200;

            return new MockResponse($body, ['http_code' => $status]);
        },
        'https://api.github.com',
    );
}
