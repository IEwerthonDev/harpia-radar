<?php

declare(strict_types=1);

use HarpiaRadar\Metrics\MetricsCalculator;
use HarpiaRadar\Source\Commit;
use HarpiaRadar\Source\RepositoryData;

const NOW = '2026-10-28T06:00:00Z';

/**
 * @param list<Commit> $commits
 */
function repoData(array $commits, ?string $lastCommit = '2026-10-27T10:00:00Z', ?string $lastRelease = null): RepositoryData
{
    return new RepositoryData(
        archived: false,
        license: 'MIT',
        openIssues: 4,
        lastCommitAt: $lastCommit === null ? null : utc($lastCommit),
        lastReleaseAt: $lastRelease === null ? null : utc($lastRelease),
        commits: $commits,
    );
}

/**
 * @return list<Commit>
 */
function commitsBy(string $login, int $count, string $date = '2026-10-01T00:00:00Z'): array
{
    return array_map(static fn (): Commit => new Commit($login, null, utc($date)), range(1, $count));
}

function calculate(RepositoryData $data): HarpiaRadar\Metrics\Metrics
{
    return (new MetricsCalculator())->calculate($data, healthConfig(), utc(NOW));
}

it('counts commits and distinct authors', function (): void {
    $metrics = calculate(repoData([...commitsBy('ana', 6), ...commitsBy('bia', 3), ...commitsBy('caio', 1)]));

    expect($metrics->commits)->toBe(10)
        ->and($metrics->authors)->toBe(3)
        ->and($metrics->topAuthorShare)->toBe(0.6)
        ->and($metrics->busFactor)->toBe(1);
});

it('finds the smallest group of authors with half of the commits', function (array $counts, int $busFactor): void {
    $commits = [];
    foreach ($counts as $i => $count) {
        $commits = [...$commits, ...commitsBy("author-{$i}", $count)];
    }

    expect(calculate(repoData($commits))->busFactor)->toBe($busFactor);
})->with([
    'one dominant' => [[51, 49], 1],
    'exactly half' => [[5, 5], 1],
    'two needed' => [[4, 3, 3], 2],
    'three needed' => [[3, 2, 2, 2, 2], 3],
    'everyone equal' => [[1, 1, 1, 1, 1, 1], 3],
]);

it('ignores bots by suffix and by configured login', function (): void {
    $metrics = calculate(repoData([
        ...commitsBy('ana', 2),
        ...commitsBy('dependabot[bot]', 5),
        ...commitsBy('github-actions[bot]', 5),
        ...commitsBy('Renovate', 5),
    ]));

    expect($metrics->commits)->toBe(2)
        ->and($metrics->authors)->toBe(1);
});

it('identifies authors without login by lowercase email', function (): void {
    $metrics = calculate(repoData([
        new Commit(null, 'Ana@Example.com', utc('2026-10-01')),
        new Commit(null, 'ana@example.com', utc('2026-10-02')),
        new Commit('ana', 'ana@example.com', utc('2026-10-03')),
    ]));

    expect($metrics->authors)->toBe(2);
});

it('only counts commits inside the window', function (): void {
    $metrics = calculate(repoData([
        new Commit('ana', null, utc('2026-07-30T06:00:00Z')),
        new Commit('ana', null, utc('2026-07-30T05:59:59Z')),
    ]));

    expect($metrics->commits)->toBe(1);
});

it('leaves share and bus factor empty without commits in the window', function (): void {
    $metrics = calculate(repoData([], lastCommit: '2025-03-02T10:00:00Z'));

    expect($metrics->commits)->toBe(0)
        ->and($metrics->authors)->toBe(0)
        ->and($metrics->topAuthorShare)->toBeNull()
        ->and($metrics->busFactor)->toBeNull()
        ->and($metrics->lastCommitAt?->format('Y-m-d'))->toBe('2025-03-02');
});

it('rounds the share to four decimals', function (): void {
    expect(calculate(repoData([...commitsBy('ana', 2), ...commitsBy('bia', 1)]))->topAuthorShare)->toBe(0.6667);
});

it('gives the same result for any commit order', function (): void {
    $commits = [...commitsBy('ana', 4), ...commitsBy('bia', 4), ...commitsBy('caio', 2)];
    $reversed = array_reverse($commits);

    expect(calculate(repoData($reversed)))->toEqual(calculate(repoData($commits)));
});

it('serializes to the snapshot format', function (): void {
    $metrics = calculate(repoData(
        [...commitsBy('ana', 6), ...commitsBy('bia', 4)],
        lastCommit: '2026-10-27T23:30:00-03:00',
        lastRelease: '2026-10-14T12:00:00Z',
    ));

    expect($metrics->toArray())->toBe([
        'last_commit_at' => '2026-10-28',
        'last_release_at' => '2026-10-14',
        'commits_90d' => 10,
        'authors_90d' => 2,
        'top_author_share' => 0.6,
        'bus_factor' => 1,
        'archived' => false,
        'license' => 'MIT',
        'open_issues' => 4,
    ]);
});
