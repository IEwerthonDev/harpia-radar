<?php

declare(strict_types=1);

namespace HarpiaRadar\Health;

use DateInterval;
use DateTimeImmutable;
use HarpiaRadar\Config\HealthConfig;
use HarpiaRadar\Metrics\Metrics;

final readonly class HealthClassifier
{
    public function classify(Metrics $metrics, HealthConfig $config, DateTimeImmutable $now): Health
    {
        return new Health($this->activity($metrics, $config, $now), $this->sustainability($metrics, $config));
    }

    private function activity(Metrics $metrics, HealthConfig $config, DateTimeImmutable $now): Activity
    {
        if ($metrics->archived) {
            return Activity::Archived;
        }

        $windowStart = $now->sub(new DateInterval(sprintf('P%dD', $config->windowDays)));
        if ($metrics->lastCommitAt < $windowStart) {
            return Activity::NoRecentChanges;
        }

        $releaseCutoff = $now->sub(new DateInterval(sprintf('P%dM', $config->releaseMaxAgeMonths)));

        return $metrics->lastReleaseAt !== null && $metrics->lastReleaseAt >= $releaseCutoff
            ? Activity::Active
            : Activity::ActiveNoRecentRelease;
    }

    private function sustainability(Metrics $metrics, HealthConfig $config): Sustainability
    {
        if ($metrics->commits < $config->minCommits || $metrics->busFactor === null) {
            return Sustainability::InsufficientData;
        }

        if ($metrics->topAuthorShare > $config->maxTopAuthorShare || $metrics->busFactor < $config->fewPeopleBusFactor) {
            return Sustainability::NeedsHands;
        }

        return $metrics->busFactor >= $config->wellDistributedBusFactor
            ? Sustainability::WellDistributed
            : Sustainability::FewPeople;
    }
}
