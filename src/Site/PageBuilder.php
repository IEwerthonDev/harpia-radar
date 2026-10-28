<?php

declare(strict_types=1);

namespace HarpiaRadar\Site;

use DateTimeImmutable;
use DateTimeZone;
use HarpiaRadar\Catalog\Catalog;
use HarpiaRadar\Catalog\Project;
use HarpiaRadar\Config\HealthConfig;
use HarpiaRadar\Health\Activity;
use HarpiaRadar\Health\Sustainability;

/**
 * Monta os dados da página a partir do catálogo, do snapshot e da configuração. Puro: sem I/O.
 *
 * Só números agregados chegam aqui; o snapshot não tem nomes nem logins.
 */
final readonly class PageBuilder
{
    /** O ícone de cada selo fica no template, desenhado a partir da chave. */
    private const array ACTIVITY = [
        'active' => 'Ativo',
        'active_no_recent_release' => 'Ativo, sem release recente',
        'no_recent_changes' => 'Sem alterações recentes',
        'archived' => 'Arquivado',
    ];

    private const array SUSTAINABILITY = [
        'well_distributed' => 'Bem distribuído',
        'few_people' => 'Poucas pessoas',
        'needs_hands' => 'Precisa de mãos',
        'insufficient_data' => 'Poucos dados na janela',
    ];

    /**
     * @param array<string, mixed> $snapshot snapshot já validado contra o schema
     * @return array<string, mixed>
     */
    public function build(Catalog $catalog, array $snapshot, HealthConfig $config, string $repositoryUrl): array
    {
        /** @var array{generated_at: string, window_days: int, projects: list<array<string, mixed>>} $snapshot */
        $results = array_column($snapshot['projects'], null, 'id');
        $windowDays = $snapshot['window_days'];

        $views = [];
        foreach ($catalog->projects as $project) {
            $views[$project->id] = $this->project($project, $results[$project->id] ?? null, $windowDays);
        }

        $categories = [];
        foreach ($catalog->categories as $category) {
            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'projects' => array_values(array_filter(
                    $views,
                    static fn (array $view): bool => $view['category'] === $category->id,
                )),
            ];
        }

        $generatedAt = new DateTimeImmutable($snapshot['generated_at']);

        return [
            'generated_at' => [
                'iso' => $snapshot['generated_at'],
                'date' => $generatedAt->setTimezone(new DateTimeZone('UTC'))->format('d/m/Y'),
            ],
            'window_days' => $windowDays,
            'help' => array_values(array_filter(
                $views,
                static fn (array $view): bool => ($view['sustainability']['key'] ?? null) === 'needs_hands'
                    && ($view['activity']['key'] ?? null) !== 'archived',
            )),
            'categories' => $categories,
            'rules' => $this->rules($config),
            'bots' => $config->botLogins,
            'bot_suffix' => $config->botSuffix,
            'repository_url' => rtrim($repositoryUrl, '/'),
        ];
    }

    /**
     * @param array<string, mixed>|null $result
     * @return array<string, mixed>
     */
    private function project(Project $project, ?array $result, int $windowDays): array
    {
        $url = sprintf('https://github.com/%s/%s', $project->repo->owner, $project->repo->name);
        $view = [
            'id' => $project->id,
            'name' => $project->name,
            'category' => $project->category,
            'description' => $project->description,
            'language' => $project->language,
            'replaces' => $project->replaces,
            'tags' => $project->tags,
            'url' => $url,
            'help_url' => $url . '/issues?' . http_build_query(['q' => 'is:issue is:open label:"good first issue"']),
            'state' => 'ok',
            'activity' => null,
            'sustainability' => null,
            'facts' => [],
            'message' => null,
        ];

        if ($result === null) {
            return ['state' => 'pending', 'message' => 'Os dados deste projeto ainda não foram coletados.'] + $view;
        }

        if ($result['status'] !== 'ok') {
            return ['state' => 'error', 'message' => 'Não foi possível atualizar os dados deste projeto na última coleta.'] + $view;
        }

        /** @var array{metrics: array{last_commit_at: string, last_release_at: string|null, commits_90d: int, authors_90d: int, license: string|null}, health: array{activity: string, sustainability: string}} $result */
        $metrics = $result['metrics'];
        $activity = Activity::from($result['health']['activity']);
        $sustainability = Sustainability::from($result['health']['sustainability']);

        return [
            'activity' => self::badge(self::ACTIVITY, $activity->value),
            'sustainability' => self::badge(self::SUSTAINABILITY, $sustainability->value),
            'facts' => [
                ($activity === Activity::NoRecentChanges ? 'Última alteração em ' : 'Último commit em ')
                    . self::date($metrics['last_commit_at']),
                $metrics['last_release_at'] === null
                    ? 'Nenhum release ou tag publicado'
                    : 'Último release em ' . self::date($metrics['last_release_at']),
                self::commits($metrics['commits_90d'], $metrics['authors_90d'], $windowDays),
                self::license($metrics['license']),
            ],
        ] + $view;
    }

    /**
     * @return array{activity: list<array{badge: array{key: string, label: string}, text: string}>, sustainability: list<array{badge: array{key: string, label: string}, text: string}>}
     */
    private function rules(HealthConfig $config): array
    {
        $window = $config->windowDays;
        $months = $config->releaseMaxAgeMonths;
        $few = $config->fewPeopleBusFactor;
        $well = $config->wellDistributedBusFactor;
        $share = (int) round($config->maxTopAuthorShare * 100);

        return [
            'activity' => [
                self::rule(self::ACTIVITY, 'active', sprintf('Commit nos últimos %d dias e release nos últimos %d meses.', $window, $months)),
                self::rule(self::ACTIVITY, 'active_no_recent_release', sprintf(
                    'Commit nos últimos %d dias, mas o último release ou tag tem mais de %d meses, ou não existe.',
                    $window,
                    $months,
                )),
                self::rule(self::ACTIVITY, 'no_recent_changes', sprintf(
                    'Nenhum commit nos últimos %d dias; a página mostra a data da última alteração. '
                    . 'Projetos estáveis, que mudam pouco, aparecem aqui, e isso não é demérito.',
                    $window,
                )),
                self::rule(self::ACTIVITY, 'archived', 'Quem mantém o projeto marcou o repositório como arquivado.'),
            ],
            'sustainability' => [
                self::rule(self::SUSTAINABILITY, 'well_distributed', sprintf(
                    'Metade dos commits vem de %d pessoas ou mais.',
                    $well,
                )),
                self::rule(self::SUSTAINABILITY, 'few_people', $well - 1 > $few
                    ? sprintf('Metade dos commits vem de %d a %d pessoas.', $few, $well - 1)
                    : sprintf('Metade dos commits vem de %d pessoas.', $few)),
                self::rule(self::SUSTAINABILITY, 'needs_hands', sprintf(
                    '%s, ou uma pessoa fez mais de %d%% dos commits. Uma ajuda aqui faz muita diferença.',
                    $few === 2 ? 'Uma pessoa faz metade dos commits ou mais' : sprintf('Menos de %d pessoas fazem metade dos commits', $few),
                    $share,
                )),
                self::rule(self::SUSTAINABILITY, 'insufficient_data', sprintf(
                    'Menos de %d commits nos últimos %d dias: amostra pequena demais para medir.',
                    $config->minCommits,
                    $window,
                )),
            ],
        ];
    }

    /**
     * @param array<string, string> $labels
     * @return array{badge: array{key: string, label: string}, text: string}
     */
    private static function rule(array $labels, string $key, string $text): array
    {
        return ['badge' => self::badge($labels, $key), 'text' => $text];
    }

    /**
     * @param array<string, string> $labels
     * @return array{key: string, label: string}
     */
    private static function badge(array $labels, string $key): array
    {
        return ['key' => $key, 'label' => $labels[$key]];
    }

    private static function date(string $day): string
    {
        [$year, $month, $date] = explode('-', $day);

        return sprintf('%s/%s/%s', $date, $month, $year);
    }

    private static function commits(int $commits, int $authors, int $windowDays): string
    {
        if ($commits === 0) {
            return sprintf('Nenhum commit nos últimos %d dias', $windowDays);
        }

        return sprintf(
            '%d %s de %d %s nos últimos %d dias',
            $commits,
            $commits === 1 ? 'commit' : 'commits',
            $authors,
            $authors === 1 ? 'pessoa' : 'pessoas',
            $windowDays,
        );
    }

    private static function license(?string $license): string
    {
        return match ($license) {
            null => 'Sem licença declarada',
            'NOASSERTION' => 'Licença não identificada automaticamente',
            default => 'Licença ' . $license,
        };
    }
}
