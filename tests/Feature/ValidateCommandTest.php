<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Console\ValidateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @param list<MockResponse> $responses
 */
function runValidate(string $file, array $responses = [], ?string $token = 'test-token', ?MockHttpClient &$client = null): CommandTester
{
    $client = new MockHttpClient($responses);
    $command = new ValidateCommand(
        new CatalogLoader(projectRoot('schema/projects.schema.json')),
        $client,
        $token,
    );
    $tester = new CommandTester($command);
    $tester->execute(['file' => $file]);

    return $tester;
}

/**
 * @return list<MockResponse>
 */
function allAccessible(int $count): array
{
    return array_map(static fn (): MockResponse => githubResponse('repos-sped-nfe.json'), range(1, $count));
}

it('accepts a valid catalog with accessible repositories', function (): void {
    $tester = runValidate(fixturePath('catalog/valid.yaml'), allAccessible(3), client: $client);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($client->getRequestsCount())->toBe(3)
        ->and($tester->getDisplay())->toContain('3 projetos em 2 categorias');
});

it('fails before any request without a token', function (?string $token): void {
    $tester = runValidate(fixturePath('catalog/valid.yaml'), allAccessible(3), $token, $client);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(0)
        ->and($tester->getDisplay())->toContain('GITHUB_TOKEN');
})->with(['unset' => null, 'empty' => '']);

it('says which project and field are missing', function (string $field): void {
    $data = validCatalogData();
    unset($data['projects'][2][$field]);

    $tester = runValidate(writeCatalog($data), client: $client);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(0)
        ->and($tester->getDisplay())->toContain("Projeto php-pix, campo {$field}: campo obrigatório ausente.");
})->with(['name', 'repo', 'category', 'description']);

it('lists every problem at once', function (): void {
    $data = validCatalogData();
    unset($data['projects'][0]['description']);
    $data['projects'][1]['category'] = 'boletos';
    $data['projects'][2]['id'] = 'laravel-boleto';

    $display = runValidate(writeCatalog($data))->getDisplay();

    expect($display)
        ->toContain('3 problemas encontrados')
        ->toContain('Projeto sped-nfe, campo description: campo obrigatório ausente.')
        ->toContain('Projeto laravel-boleto, campo category: a categoria "boletos" não está declarada em categories.')
        ->toContain('Projeto laravel-boleto (#3), campo id: o id "laravel-boleto" já é usado pelo projeto #2.');
});

it('reports an inaccessible repository and keeps checking the others', function (): void {
    $tester = runValidate(fixturePath('catalog/valid.yaml'), [
        githubResponse('repos-sped-nfe.json'),
        githubResponse('repos-not-found.json', 404),
        githubResponse('repos-sped-nfe.json'),
    ], client: $client);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(3)
        ->and($tester->getDisplay())
        ->toContain('1 problema encontrado')
        ->toContain('Projeto laravel-boleto, campo repo: Repositório github:eduardokum/laravel-boleto não encontrado ou privado (HTTP 404).');
});

it('stops checking when the failure affects every repository', function (): void {
    $tester = runValidate(fixturePath('catalog/valid.yaml'), [
        githubResponse('repos-bad-credentials.json', 401),
    ], client: $client);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(1)
        ->and($tester->getDisplay())->toContain('O GitHub recusou o token (HTTP 401).');
});
