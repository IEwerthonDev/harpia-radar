<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Config\HealthConfigLoader;
use HarpiaRadar\Console\CollectCommand;
use HarpiaRadar\Snapshot\SnapshotWriter;
use HarpiaRadar\Validation\SchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

const COLLECT_REPOS = ['nfephp-org/sped-nfe' => 'sped-nfe', 'renatomb/php_qrcode_pix' => 'php-qrcode-pix'];

function runCollect(MockHttpClient $client, string $output, ?string $token = 'test-token', string $catalog = 'catalog/collect.yaml'): CommandTester
{
    $command = new CollectCommand(
        new CatalogLoader(projectRoot('schema/projects.schema.json')),
        new HealthConfigLoader(projectRoot('schema/health.schema.json')),
        new SnapshotWriter(projectRoot('schema/snapshot.schema.json')),
        $client,
        $token,
        static fn (): DateTimeImmutable => utc('2026-10-28T06:00:00Z'),
    );
    $tester = new CommandTester($command);
    $tester->execute([
        '--catalog' => fixturePath($catalog),
        '--config' => projectRoot('config/health.yaml'),
        '--output' => $output,
    ]);

    return $tester;
}

function outputPath(): string
{
    $dir = sys_get_temp_dir() . '/radar-' . bin2hex(random_bytes(4));

    return $dir . '/data/snapshot.json';
}

/**
 * @return array<string, mixed>
 */
function readSnapshot(string $path): array
{
    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

it('writes a snapshot with every project of the catalog', function (): void {
    $output = outputPath();
    $tester = runCollect(githubFixtureClient(COLLECT_REPOS), $output);
    $snapshot = readSnapshot($output);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($snapshot['generated_at'])->toBe('2026-10-28T06:00:00Z')
        ->and($snapshot['schema_version'])->toBe(1)
        ->and($snapshot['window_days'])->toBe(90)
        ->and(array_column($snapshot['projects'], 'id'))->toBe(['sped-nfe', 'gone', 'php-qrcode-pix'])
        ->and((new SchemaValidator())->validate($snapshot, projectRoot('schema/snapshot.schema.json')))->toBe([]);
});

it('computes metrics and health from the recorded responses', function (): void {
    $output = outputPath();
    runCollect(githubFixtureClient(COLLECT_REPOS), $output);
    [$spedNfe, , $qrcode] = readSnapshot($output)['projects'];

    expect($spedNfe['status'])->toBe('ok')
        ->and($spedNfe['metrics']['commits_90d'])->toBe(35)
        ->and($spedNfe['metrics']['license'])->toBe('NOASSERTION')
        ->and($spedNfe['metrics']['last_release_at'])->toBe('2026-10-02')
        ->and($spedNfe['health']['activity'])->toBe('active')
        ->and($qrcode['metrics'])->toMatchArray([
            'last_commit_at' => '2026-08-18',
            'last_release_at' => null,
            'commits_90d' => 2,
            'authors_90d' => 2,
            'top_author_share' => 0.5,
            'bus_factor' => 1,
            'license' => 'CC0-1.0',
        ])
        ->and($qrcode['health'])->toBe(['activity' => 'active_no_recent_release', 'sustainability' => 'insufficient_data']);
});

it('marks a failing project as error and keeps the others', function (): void {
    $output = outputPath();
    $tester = runCollect(githubFixtureClient(COLLECT_REPOS), $output);
    $gone = readSnapshot($output)['projects'][1];

    expect($gone)->toBe([
        'id' => 'gone',
        'status' => 'error',
        'error' => 'Repositório github:harpia-test/gone não encontrado ou privado (HTTP 404).',
        'metrics' => null,
        'health' => null,
    ])->and($tester->getDisplay())->toContain('2 de 3 projetos coletados');
});

it('produces the same snapshot for the same responses', function (): void {
    $first = outputPath();
    $second = outputPath();
    runCollect(githubFixtureClient(COLLECT_REPOS), $first);
    runCollect(githubFixtureClient(COLLECT_REPOS), $second);

    expect(file_get_contents($second))->toBe(file_get_contents($first));
});

it('keeps the previous snapshot when every project fails', function (): void {
    $output = outputPath();
    mkdir(dirname($output), recursive: true);
    file_put_contents($output, '{"previous": true}');

    $tester = runCollect(githubFixtureClient([]), $output);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and(file_get_contents($output))->toBe('{"previous": true}')
        ->and($tester->getDisplay())->toContain('Nenhum projeto foi coletado');
});

it('stops without writing when the token is refused', function (): void {
    $output = outputPath();
    $client = new MockHttpClient([githubResponse('repos-bad-credentials.json', 401)]);

    $tester = runCollect($client, $output);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(1)
        ->and(file_exists($output))->toBeFalse()
        ->and($tester->getDisplay())->toContain('O GitHub recusou o token');
});

it('fails before any request without a token', function (): void {
    $client = new MockHttpClient([]);

    $tester = runCollect($client, outputPath(), token: null);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($client->getRequestsCount())->toBe(0)
        ->and($tester->getDisplay())->toContain('GITHUB_TOKEN');
});

it('fails on an invalid catalog', function (): void {
    $tester = runCollect(new MockHttpClient([]), outputPath(), catalog: 'catalog/invalid-syntax.yaml');

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('YAML inválido');
});
