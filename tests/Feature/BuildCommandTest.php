<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Config\HealthConfigLoader;
use HarpiaRadar\Console\BuildCommand;
use HarpiaRadar\Site\SiteBuilder;
use HarpiaRadar\Snapshot\SnapshotReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function runBuild(string $snapshot, string $output): CommandTester
{
    $tester = new CommandTester(new BuildCommand(
        new CatalogLoader(projectRoot('schema/projects.schema.json')),
        new HealthConfigLoader(projectRoot('schema/health.schema.json')),
        new SnapshotReader(projectRoot('schema/snapshot.schema.json')),
        new SiteBuilder(projectRoot('templates')),
        'https://github.com/IEwerthonDev/harpia-radar',
    ));
    $tester->execute([
        '--catalog' => fixturePath('catalog/site.yaml'),
        '--config' => projectRoot('config/health.yaml'),
        '--snapshot' => $snapshot,
        '--output' => $output,
    ]);

    return $tester;
}

it('builds the page from the snapshot', function (): void {
    $output = sys_get_temp_dir() . '/radar-build-' . bin2hex(random_bytes(4));

    $tester = runBuild(fixturePath('snapshot/site.json'), $output);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($output . '/index.html')->toBeFile()
        ->and($tester->getDisplay())->toContain('Página gerada');
});

it('fails clearly without a snapshot', function (): void {
    $tester = runBuild('/nao/existe/snapshot.json', sys_get_temp_dir() . '/radar-build-x');

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Snapshot não encontrado')->toContain('collect');
});

it('fails clearly with an invalid snapshot', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'radar-snapshot-');
    file_put_contents($path, '{"schema_version": 2}');

    $tester = runBuild($path, sys_get_temp_dir() . '/radar-build-y');

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Snapshot inválido');
});
