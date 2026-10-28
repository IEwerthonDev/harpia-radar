<?php

declare(strict_types=1);

use HarpiaRadar\Config\HealthConfigLoader;
use HarpiaRadar\Config\InvalidConfig;
use Symfony\Component\Yaml\Yaml;

function healthLoader(): HealthConfigLoader
{
    return new HealthConfigLoader(projectRoot('schema/health.schema.json'));
}

it('loads the repository config', function (): void {
    expect(healthLoader()->load(projectRoot('config/health.yaml')))->toEqual(healthConfig());
});

it('rejects an invalid config naming the field', function (): void {
    $data = Yaml::parseFile(projectRoot('config/health.yaml'));
    $data['window_days'] = 0;
    $path = tempnam(sys_get_temp_dir(), 'radar-health-');
    file_put_contents($path, Yaml::dump($data));

    healthLoader()->load($path);
})->throws(InvalidConfig::class, 'window_days');

it('rejects a missing file', function (): void {
    healthLoader()->load('/nao/existe.yaml');
})->throws(InvalidConfig::class, 'Arquivo não encontrado');
