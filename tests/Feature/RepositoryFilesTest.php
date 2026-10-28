<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Catalog\Project;
use HarpiaRadar\Validation\SchemaValidator;
use Symfony\Component\Yaml\Yaml;

it('ships projects.yaml with the initial list from the spec', function (): void {
    $catalog = (new CatalogLoader(projectRoot('schema/projects.schema.json')))->load(projectRoot('projects.yaml'));

    expect(array_map(static fn (Project $p): array => [$p->id, (string) $p->repo, $p->category], $catalog->projects))
        ->toBe([
            ['sped-nfe', 'github:nfephp-org/sped-nfe', 'fiscal'],
            ['sped-da', 'github:nfephp-org/sped-da', 'fiscal'],
            ['sped-cte', 'github:nfephp-org/sped-cte', 'fiscal'],
            ['sped-mdfe', 'github:nfephp-org/sped-mdfe', 'fiscal'],
            ['nfse-php', 'github:nfse-nacional/nfse-php', 'fiscal'],
            ['laravel-boleto', 'github:eduardokum/laravel-boleto', 'payments'],
            ['openboleto', 'github:openboleto/openboleto', 'payments'],
            ['php-pix', 'github:piggly-dev/php-pix', 'payments'],
            ['php-qrcode-pix', 'github:renatomb/php_qrcode_pix', 'payments'],
        ])
        ->and($catalog->categories)->toHaveCount(6);
});

it('ships a health.yaml that follows its schema', function (): void {
    $data = Yaml::parseFile(projectRoot('config/health.yaml'));

    expect((new SchemaValidator())->validate($data, projectRoot('schema/health.schema.json')))->toBe([])
        ->and($data['window_days'])->toBe(90);
});

it('rejects a health.yaml with an invalid threshold', function (): void {
    $data = Yaml::parseFile(projectRoot('config/health.yaml'));
    $data['sustainability']['max_top_author_share'] = 1.5;

    expect((new SchemaValidator())->validate($data, projectRoot('schema/health.schema.json')))->toHaveCount(1);
});

it('accepts the example snapshot', function (): void {
    $data = json_decode((string) file_get_contents(fixturePath('snapshot/example.json')), true);

    expect((new SchemaValidator())->validate($data, projectRoot('schema/snapshot.schema.json')))->toBe([]);
});

it('rejects inconsistent snapshot entries', function (string $case): void {
    $data = json_decode((string) file_get_contents(fixturePath('snapshot/example.json')), true);
    match ($case) {
        'ok without metrics' => $data['projects'][0]['metrics'] = null,
        'error without message' => $data['projects'][2]['error'] = null,
        'error with metrics' => $data['projects'][2]['metrics'] = $data['projects'][0]['metrics'],
        'unknown activity' => $data['projects'][0]['health']['activity'] = 'dead',
        'share above one' => $data['projects'][0]['metrics']['top_author_share'] = 1.2,
        'datetime as date' => $data['projects'][0]['metrics']['last_commit_at'] = '2026-10-27T10:00:00Z',
    };

    expect((new SchemaValidator())->validate($data, projectRoot('schema/snapshot.schema.json')))->not->toBe([]);
})->with([
    'ok without metrics',
    'error without message',
    'error with metrics',
    'unknown activity',
    'share above one',
    'datetime as date',
]);
