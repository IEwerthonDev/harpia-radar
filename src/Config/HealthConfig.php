<?php

declare(strict_types=1);

namespace HarpiaRadar\Config;

/**
 * Janela de análise, limiares dos selos e bots, lidos de config/health.yaml.
 */
final readonly class HealthConfig
{
    /**
     * @param list<string> $botLogins
     */
    public function __construct(
        public int $windowDays,
        public int $releaseMaxAgeMonths,
        public int $minCommits,
        public int $wellDistributedBusFactor,
        public int $fewPeopleBusFactor,
        public float $maxTopAuthorShare,
        public string $botSuffix,
        public array $botLogins,
    ) {
    }
}
