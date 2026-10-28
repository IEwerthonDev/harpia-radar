<?php

declare(strict_types=1);

namespace HarpiaRadar\Config;

use HarpiaRadar\Validation\SchemaValidator;
use HarpiaRadar\Validation\SchemaViolation;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class HealthConfigLoader
{
    public function __construct(
        private string $schemaFile,
        private SchemaValidator $schemaValidator = new SchemaValidator(),
    ) {
    }

    /**
     * @throws InvalidConfig
     */
    public function load(string $path): HealthConfig
    {
        if (!is_file($path)) {
            throw new InvalidConfig(sprintf('Arquivo não encontrado: %s.', $path));
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new InvalidConfig(sprintf('YAML inválido em %s: %s', $path, $e->getMessage()));
        }

        $violations = $this->schemaValidator->validate($data, $this->schemaFile);
        if ($violations !== []) {
            throw new InvalidConfig(sprintf('Configuração inválida em %s: %s.', $path, implode('; ', array_map(
                static fn (SchemaViolation $v): string => implode('.', $v->path) . ': ' . $v->message,
                $violations,
            ))));
        }

        /** @var array{window_days: int, activity: array{release_max_age_months: int}, sustainability: array{min_commits: int, well_distributed_bus_factor: int, few_people_bus_factor: int, max_top_author_share: int|float}, bots: array{suffix: string, logins: list<string>}} $data */
        return new HealthConfig(
            windowDays: $data['window_days'],
            releaseMaxAgeMonths: $data['activity']['release_max_age_months'],
            minCommits: $data['sustainability']['min_commits'],
            wellDistributedBusFactor: $data['sustainability']['well_distributed_bus_factor'],
            fewPeopleBusFactor: $data['sustainability']['few_people_bus_factor'],
            maxTopAuthorShare: (float) $data['sustainability']['max_top_author_share'],
            botSuffix: $data['bots']['suffix'],
            botLogins: $data['bots']['logins'],
        );
    }
}
