<?php

declare(strict_types=1);

namespace HarpiaRadar\Source;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use HarpiaRadar\Catalog\RepoRef;

final readonly class GitHubSource implements SourceInterface
{
    /** Teto de páginas de commits por projeto (100 commits cada). */
    private const int MAX_PAGES = 50;

    public function __construct(private GitHubApi $api)
    {
    }

    public function fetch(RepoRef $repo, DateTimeImmutable $since): RepositoryData
    {
        $info = $this->api->get($repo, '')->data;
        $license = self::at($info, 'license', 'spdx_id');
        $openIssues = self::at($info, 'open_issues_count');

        $latest = $this->api->get($repo, '/commits', ['per_page' => 1])->data;
        if (!is_array($latest) || $latest === []) {
            throw new RepositoryNotAccessible(sprintf('O repositório %s não tem commits.', $repo));
        }

        return new RepositoryData(
            archived: self::at($info, 'archived') === true,
            license: is_string($license) ? $license : null,
            openIssues: is_int($openIssues) ? $openIssues : 0,
            lastCommitAt: self::commitDate($repo, $latest[0]),
            lastReleaseAt: $this->lastRelease($repo),
            commits: $this->commitsSince($repo, $since),
        );
    }

    /**
     * Data mais recente entre o último release e a última tag; sem nenhum dos dois, nulo.
     *
     * Muitos projetos PHP publicam versões só como tags (via Packagist) e têm releases antigos
     * no GitHub, então olhar só para o release os marcaria como "sem release recente".
     */
    private function lastRelease(RepoRef $repo): ?DateTimeImmutable
    {
        $release = $this->api->get($repo, '/releases/latest', allowNotFound: true);
        $releaseDate = $release->data === null ? null : self::date($repo, self::at($release->data, 'published_at'));

        $tags = $this->api->get($repo, '/tags', ['per_page' => 1])->data;
        $sha = self::at($tags, 0, 'commit', 'sha');
        $tagDate = is_string($sha)
            ? self::commitDate($repo, $this->api->get($repo, '/commits/' . rawurlencode($sha))->data)
            : null;

        return $releaseDate === null || ($tagDate !== null && $tagDate > $releaseDate) ? $tagDate : $releaseDate;
    }

    /**
     * @return list<Commit>
     */
    private function commitsSince(RepoRef $repo, DateTimeImmutable $since): array
    {
        $response = $this->api->get($repo, '/commits', [
            'since' => $since->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'per_page' => 100,
        ]);

        $commits = [];
        for ($page = 1; ; ++$page) {
            foreach (is_array($response->data) ? $response->data : [] as $item) {
                $login = self::at($item, 'author', 'login');
                $email = self::at($item, 'commit', 'author', 'email');
                $commits[] = new Commit(
                    is_string($login) ? $login : null,
                    is_string($email) ? $email : null,
                    self::commitDate($repo, $item),
                );
            }

            if ($response->nextUrl === null || $page >= self::MAX_PAGES) {
                return $commits;
            }

            $response = $this->api->get($repo, $response->nextUrl);
        }
    }

    private static function commitDate(RepoRef $repo, mixed $commit): DateTimeImmutable
    {
        $date = self::date($repo, self::at($commit, 'commit', 'committer', 'date'));
        if ($date === null) {
            throw new RepositoryNotAccessible(sprintf('O GitHub devolveu um commit sem data para %s.', $repo));
        }

        return $date;
    }

    private static function date(RepoRef $repo, mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new RepositoryNotAccessible(sprintf('O GitHub devolveu uma data inválida para %s: %s.', $repo, $value));
        }
    }

    private static function at(mixed $data, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }
}
