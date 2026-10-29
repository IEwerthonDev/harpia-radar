<?php

declare(strict_types=1);

use HarpiaRadar\Site\PageBuilder;

const RADAR_URL = 'https://github.com/IEwerthonDev/harpia-radar';

/**
 * @return array<string, mixed>
 */
function page(?HarpiaRadar\Config\HealthConfig $config = null): array
{
    return (new PageBuilder())->build(siteCatalog(), siteSnapshot(), $config ?? healthConfig(), RADAR_URL);
}

/**
 * @return array<string, mixed>
 */
function projectView(string $id): array
{
    foreach (page()['categories'] as $category) {
        foreach ($category['projects'] as $project) {
            if ($project['id'] === $id) {
                return $project;
            }
        }
    }

    throw new RuntimeException('Projeto não encontrado: ' . $id);
}

it('shows the collection date', function (): void {
    expect(page()['generated_at'])->toBe(['iso' => '2026-10-28T06:00:00Z', 'date' => '28/10/2026']);
});

it('groups projects by category in catalog order', function (): void {
    $categories = array_map(
        static fn (array $c): array => [$c['name'], array_column($c['projects'], 'id')],
        page()['categories'],
    );

    expect($categories)->toBe([
        ['Fiscal e notas', ['sped-nfe', 'sped-da', 'old-lib']],
        ['Pagamentos, boleto e Pix', ['php-pix', 'php-qrcode-pix', 'openboleto', 'laravel-boleto']],
        ['Documentos e PDF', []],
    ]);
});

it('builds links to the repository and to beginner issues', function (): void {
    expect(projectView('php-qrcode-pix'))->toMatchArray([
        'url' => 'https://github.com/renatomb/php_qrcode_pix',
        'help_url' => 'https://github.com/renatomb/php_qrcode_pix/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
    ]);
});

it('labels both badges with their key and text', function (): void {
    $view = projectView('sped-nfe');

    expect($view['activity'])->toBe(['key' => 'active', 'label' => 'Ativo'])
        ->and($view['sustainability'])->toBe(['key' => 'needs_hands', 'label' => 'Precisa de mãos']);
});

it('labels every badge value', function (string $id, string $activity, string $sustainability): void {
    $view = projectView($id);

    expect($view['activity']['label'])->toBe($activity)
        ->and($view['sustainability']['label'])->toBe($sustainability);
})->with([
    ['sped-da', 'Ativo, sem release recente', 'Poucas pessoas'],
    ['old-lib', 'Arquivado', 'Precisa de mãos'],
    ['php-pix', 'Sem alterações recentes', 'Poucos dados na janela'],
]);

it('lists aggregated facts', function (): void {
    expect(projectView('sped-nfe')['facts'])->toBe([
        'Último commit em 27/10/2026',
        'Último release em 02/10/2026',
        '31 commits de 7 pessoas nos últimos 90 dias',
        'Licença não identificada automaticamente',
    ]);
});

it('shows the last change date outside the window', function (): void {
    expect(projectView('php-pix')['facts'])->toBe([
        'Última alteração em 19/05/2024',
        'Último release em 19/05/2024',
        'Nenhum commit nos últimos 90 dias',
        'Licença MIT',
    ]);
});

it('uses singular forms and handles missing release and license', function (): void {
    expect(projectView('php-qrcode-pix')['facts'])->toContain('1 commit de 1 pessoa nos últimos 90 dias', 'Nenhum release ou tag publicado')
        ->and(projectView('old-lib')['facts'])->toContain('Sem licença declarada');
});

it('explains a collection error without badges', function (): void {
    expect(projectView('openboleto'))->toMatchArray([
        'state' => 'error',
        'activity' => null,
        'sustainability' => null,
        'facts' => [],
        'message' => 'Não foi possível atualizar os dados deste projeto na última coleta.',
    ]);
});

it('marks projects not collected yet', function (): void {
    expect(projectView('laravel-boleto'))->toMatchArray([
        'state' => 'pending',
        'message' => 'Os dados deste projeto ainda não foram coletados.',
    ]);
});

it('lists where to help: needs hands and not archived', function (): void {
    expect(array_column(page()['help'], 'id'))->toBe(['sped-nfe']);
});

it('summarizes the catalog: total, active and needing hands', function (): void {
    expect(page()['summary'])->toBe(['total' => 7, 'active' => 3, 'help' => 1]);
});

it('explains the rules with the configured thresholds', function (): void {
    $rules = page(healthConfig([
        'windowDays' => 60,
        'releaseMaxAgeMonths' => 6,
        'minCommits' => 20,
        'wellDistributedBusFactor' => 4,
        'fewPeopleBusFactor' => 2,
        'maxTopAuthorShare' => 0.8,
    ]))['rules'];
    $text = implode("\n", array_column([...$rules['activity'], ...$rules['sustainability']], 'text'));

    expect($text)
        ->toContain('Commit nos últimos 60 dias e release nos últimos 6 meses.')
        ->toContain('4 pessoas ou mais')
        ->toContain('de 2 a 3 pessoas')
        ->toContain('mais de 80% dos commits')
        ->toContain('Menos de 20 commits nos últimos 60 dias')
        ->not->toContain('90 dias');
});

it('never exposes author identities', function (): void {
    expect(json_encode(page()))->not->toContain('author-')->not->toContain('login');
});
