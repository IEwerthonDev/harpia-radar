<?php

declare(strict_types=1);

namespace HarpiaRadar\Console;

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Catalog\InvalidCatalog;
use HarpiaRadar\Config\HealthConfigLoader;
use HarpiaRadar\Config\InvalidConfig;
use HarpiaRadar\Site\PageBuilder;
use HarpiaRadar\Site\SiteBuilder;
use HarpiaRadar\Snapshot\InvalidSnapshot;
use HarpiaRadar\Snapshot\SnapshotReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'build',
    description: 'Gera a página estática a partir do snapshot',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly CatalogLoader $catalogLoader,
        private readonly HealthConfigLoader $configLoader,
        private readonly SnapshotReader $snapshotReader,
        private readonly SiteBuilder $siteBuilder,
        private readonly string $repositoryUrl,
        private readonly PageBuilder $pageBuilder = new PageBuilder(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('catalog', null, InputOption::VALUE_REQUIRED, 'Arquivo da lista curada', 'projects.yaml')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Arquivo de limiares', 'config/health.yaml')
            ->addOption('snapshot', null, InputOption::VALUE_REQUIRED, 'Snapshot gerado por collect', 'data/snapshot.json')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Diretório da página', 'public');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $snapshotPath = (string) $input->getOption('snapshot');
        $outputDir = (string) $input->getOption('output');

        try {
            $catalog = $this->catalogLoader->load((string) $input->getOption('catalog'));
            $config = $this->configLoader->load((string) $input->getOption('config'));
            $snapshot = $this->snapshotReader->read($snapshotPath);
        } catch (InvalidCatalog) {
            $io->error('A lista de projetos tem problemas. Rode bin/radar validate.');

            return Command::FAILURE;
        } catch (InvalidConfig | InvalidSnapshot $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $page = $this->pageBuilder->build($catalog, $snapshot, $config, $this->repositoryUrl);
        $bytes = $this->siteBuilder->build($page, $snapshotPath, $outputDir);
        $io->success(sprintf('Página gerada em %s/index.html (%.1f KB).', $outputDir, $bytes / 1024));

        return Command::SUCCESS;
    }
}
