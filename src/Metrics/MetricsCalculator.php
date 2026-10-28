<?php

declare(strict_types=1);

namespace HarpiaRadar\Metrics;

use DateInterval;
use DateTimeImmutable;
use HarpiaRadar\Config\HealthConfig;
use HarpiaRadar\Source\Commit;
use HarpiaRadar\Source\RepositoryData;

/**
 * Calcula as métricas de um repositório. Puro: sem I/O e sem ler o relógio.
 */
final readonly class MetricsCalculator
{
    public function calculate(RepositoryData $data, HealthConfig $config, DateTimeImmutable $now): Metrics
    {
        $windowStart = $now->sub(new DateInterval(sprintf('P%dD', $config->windowDays)));
        $botLogins = array_map(strtolower(...), $config->botLogins);
        $botSuffix = strtolower($config->botSuffix);

        /** @var array<string, int> $commitsByAuthor */
        $commitsByAuthor = [];
        foreach ($data->commits as $commit) {
            if ($commit->committedAt < $windowStart || self::isBot($commit, $botSuffix, $botLogins)) {
                continue;
            }

            $author = self::authorKey($commit);
            $commitsByAuthor[$author] = ($commitsByAuthor[$author] ?? 0) + 1;
        }

        $total = array_sum($commitsByAuthor);
        $empty = $commitsByAuthor === [];

        return new Metrics(
            lastCommitAt: $data->lastCommitAt,
            lastReleaseAt: $data->lastReleaseAt,
            commits: $total,
            authors: count($commitsByAuthor),
            topAuthorShare: $empty ? null : round(max($commitsByAuthor) / $total, 4),
            busFactor: $empty ? null : self::busFactor($commitsByAuthor, $total),
            archived: $data->archived,
            license: $data->license,
            openIssues: $data->openIssues,
        );
    }

    /**
     * Menor número de autores que somam pelo menos metade dos commits.
     *
     * @param array<string, int> $commitsByAuthor
     */
    private static function busFactor(array $commitsByAuthor, int $total): int
    {
        $counts = array_values($commitsByAuthor);
        rsort($counts);

        $sum = 0;
        foreach ($counts as $index => $count) {
            $sum += $count;
            if ($sum * 2 >= $total) {
                return $index + 1;
            }
        }

        return count($counts);
    }

    /**
     * @param list<string> $botLogins em minúsculas
     */
    private static function isBot(Commit $commit, string $botSuffix, array $botLogins): bool
    {
        if ($commit->login === null) {
            return false;
        }

        $login = strtolower($commit->login);

        return str_ends_with($login, $botSuffix) || in_array($login, $botLogins, true);
    }

    /**
     * Login do GitHub quando existe; senão, o e-mail em minúsculas. O prefixo evita colisão entre os dois.
     */
    private static function authorKey(Commit $commit): string
    {
        return $commit->login !== null
            ? 'login:' . $commit->login
            : 'email:' . strtolower($commit->email ?? '');
    }
}
