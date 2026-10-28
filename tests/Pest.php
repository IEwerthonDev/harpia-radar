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
