<?php

declare(strict_types=1);

namespace HarpiaRadar\Console;

use Closure;
use DateInterval;
use DateTimeImmutable;
use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Catalog\InvalidCatalog;
use HarpiaRadar\Config\GitHubToken;
use HarpiaRadar\Config\HealthConfigLoader;
use HarpiaRadar\Config\InvalidConfig;
use HarpiaRadar\Config\MissingGitHubToken;
use HarpiaRadar\Health\HealthClassifier;
use HarpiaRadar\Metrics\MetricsCalculator;
use HarpiaRadar\Snapshot\ProjectResult;
use HarpiaRadar\Snapshot\Snapshot;
use HarpiaRadar\Snapshot\SnapshotWriter;
use HarpiaRadar\Source\GitHubApi;
use HarpiaRadar\Source\GitHubSource;
use HarpiaRadar\Source\RepositoryNotAccessible;
use HarpiaRadar\Validation\ValidationError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'collect',
    description: 'Coleta os dados de cada projeto e grava o snapshot',
)]
final class CollectCommand extends Command
{
    /**
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private readonly CatalogLoader $catalogLoader,
        private readonly HealthConfigLoader $configLoader,
        private readonly SnapshotWriter $writer,
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $githubToken,
        private readonly Closure $clock,
        private readonly MetricsCalculator $calculator = new MetricsCalculator(),
        private readonly HealthClassifier $classifier = new HealthClassifier(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('catalog', null, InputOption::VALUE_REQUIRED, 'Arquivo da lista curada', 'projects.yaml')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Arquivo de limiares', 'config/health.yaml')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Onde gravar o snapshot', 'data/snapshot.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $token = GitHubToken::fromEnvironment($this->githubToken);
            $catalog = $this->catalogLoader->load((string) $input->getOption('catalog'));
            $config = $this->configLoader->load((string) $input->getOption('config'));
        } catch (MissingGitHubToken | InvalidConfig $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidCatalog $e) {
            $io->listing(array_map(static fn (ValidationError $error): string => $error->describe(), $e->errors));
            $io->error('A lista de projetos tem problemas. Rode bin/radar validate.');

            return Command::FAILURE;
        }

        $now = ($this->clock)();
        $since = $now->sub(new DateInterval(sprintf('P%dD', $config->windowDays)));
        $source = new GitHubSource(new GitHubApi($this->httpClient, $token));

        $results = [];
        foreach ($catalog->projects as $project) {
            try {
                $data = $source->fetch($project->repo, $since);
                $metrics = $this->calculator->calculate($data, $config, $now);
                $results[] = ProjectResult::ok($project->id, $metrics, $this->classifier->classify($metrics, $config, $now));
                $io->text(sprintf('✓ %s', $project->id));
            } catch (RepositoryNotAccessible $e) {
                if ($e->affectsAllRepositories) {
                    $io->error($e->getMessage() . ' Nenhum arquivo foi gravado.');

                    return Command::FAILURE;
                }

                $results[] = ProjectResult::error($project->id, $e->getMessage());
                $io->text(sprintf('✗ %s: %s', $project->id, $e->getMessage()));
            }
        }

        $collected = count(array_filter($results, static fn (ProjectResult $r): bool => $r->isOk()));
        if ($collected === 0) {
            $io->error('Nenhum projeto foi coletado. O snapshot anterior foi mantido.');

            return Command::FAILURE;
        }

        $path = (string) $input->getOption('output');
        $this->writer->write(new Snapshot($now, $config->windowDays, $results), $path);
        $io->success(sprintf('%d de %d projetos coletados. Snapshot gravado em %s.', $collected, count($results), $path));

        return Command::SUCCESS;
    }
}
