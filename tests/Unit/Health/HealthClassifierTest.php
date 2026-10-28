<?php

declare(strict_types=1);

use HarpiaRadar\Health\Activity;
use HarpiaRadar\Health\HealthClassifier;
use HarpiaRadar\Health\Sustainability;
use HarpiaRadar\Metrics\Metrics;

/**
 * @param array<string, mixed> $overrides
 */
function metrics(array $overrides = []): Metrics
{
    return new Metrics(...array_merge([
        'lastCommitAt' => utc('2026-10-27T10:00:00Z'),
        'lastReleaseAt' => utc('2026-10-14T10:00:00Z'),
        'commits' => 40,
        'authors' => 6,
        'topAuthorShare' => 0.3,
        'busFactor' => 3,
        'archived' => false,
        'license' => 'MIT',
        'openIssues' => 0,
    ], $overrides));
}

function classify(Metrics $metrics, ?HarpiaRadar\Config\HealthConfig $config = null): HarpiaRadar\Health\Health
{
    return (new HealthClassifier())->classify($metrics, $config ?? healthConfig(), utc('2026-10-28T06:00:00Z'));
}

it('classifies activity', function (array $overrides, Activity $expected): void {
    expect(classify(metrics($overrides))->activity)->toBe($expected);
})->with([
    'commit and recent release' => [[], Activity::Active],
    'release exactly 12 months ago' => [['lastReleaseAt' => utc('2025-10-28T06:00:00Z')], Activity::Active],
    'release older than 12 months' => [['lastReleaseAt' => utc('2025-10-28T05:59:59Z')], Activity::ActiveNoRecentRelease],
    'never released' => [['lastReleaseAt' => null], Activity::ActiveNoRecentRelease],
    'commit exactly at window start' => [['lastCommitAt' => utc('2026-07-30T06:00:00Z')], Activity::Active],
    'commit before the window' => [['lastCommitAt' => utc('2026-07-30T05:59:59Z')], Activity::NoRecentChanges],
    'stale even with recent release' => [['lastCommitAt' => utc('2025-01-01'), 'lastReleaseAt' => utc('2026-10-01')], Activity::NoRecentChanges],
    'archived wins' => [['archived' => true], Activity::Archived],
]);

it('classifies sustainability', function (array $overrides, Sustainability $expected): void {
    expect(classify(metrics($overrides))->sustainability)->toBe($expected);
})->with([
    'bus factor 3' => [['busFactor' => 3], Sustainability::WellDistributed],
    'bus factor 5' => [['busFactor' => 5], Sustainability::WellDistributed],
    'bus factor 2' => [['busFactor' => 2, 'topAuthorShare' => 0.45], Sustainability::FewPeople],
    'bus factor 1' => [['busFactor' => 1, 'topAuthorShare' => 0.55], Sustainability::NeedsHands],
    'share exactly 75%' => [['busFactor' => 1, 'topAuthorShare' => 0.75], Sustainability::NeedsHands],
    'share above 75% wins' => [['busFactor' => 3, 'topAuthorShare' => 0.76], Sustainability::NeedsHands],
    'share at limit with bus factor 3' => [['busFactor' => 3, 'topAuthorShare' => 0.75], Sustainability::WellDistributed],
    '9 commits' => [['commits' => 9, 'busFactor' => 1, 'topAuthorShare' => 1.0], Sustainability::InsufficientData],
    '10 commits' => [['commits' => 10, 'busFactor' => 3, 'topAuthorShare' => 0.3], Sustainability::WellDistributed],
    'no commits' => [['commits' => 0, 'authors' => 0, 'busFactor' => null, 'topAuthorShare' => null], Sustainability::InsufficientData],
]);

it('reads every threshold from the config', function (): void {
    $config = healthConfig([
        'windowDays' => 30,
        'releaseMaxAgeMonths' => 1,
        'minCommits' => 50,
        'wellDistributedBusFactor' => 5,
        'fewPeopleBusFactor' => 3,
        'maxTopAuthorShare' => 0.5,
    ]);

    expect(classify(metrics(['lastCommitAt' => utc('2026-09-01')]), $config)->activity)->toBe(Activity::NoRecentChanges)
        ->and(classify(metrics(['lastReleaseAt' => utc('2026-09-01')]), $config)->activity)->toBe(Activity::ActiveNoRecentRelease)
        ->and(classify(metrics(['commits' => 49]), $config)->sustainability)->toBe(Sustainability::InsufficientData)
        ->and(classify(metrics(['commits' => 60, 'busFactor' => 4]), $config)->sustainability)->toBe(Sustainability::FewPeople)
        ->and(classify(metrics(['commits' => 60, 'busFactor' => 2]), $config)->sustainability)->toBe(Sustainability::NeedsHands)
        ->and(classify(metrics(['commits' => 60, 'busFactor' => 5, 'topAuthorShare' => 0.51]), $config)->sustainability)->toBe(Sustainability::NeedsHands);
});

it('is deterministic', function (): void {
    expect(classify(metrics()))->toEqual(classify(metrics()));
});
