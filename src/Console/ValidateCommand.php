<?php

declare(strict_types=1);

namespace HarpiaRadar\Console;

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Catalog\InvalidCatalog;
use HarpiaRadar\Config\GitHubToken;
use HarpiaRadar\Config\MissingGitHubToken;
use HarpiaRadar\Source\GitHubRepositoryProbe;
use HarpiaRadar\Source\RepositoryNotAccessible;
use HarpiaRadar\Validation\ValidationError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'validate',
    description: 'Valida a lista curada: schema, ids únicos, categorias e repositórios acessíveis',
)]
final class ValidateCommand extends Command
{
    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $githubToken,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Arquivo da lista curada', 'projects.yaml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        try {
            $token = GitHubToken::fromEnvironment($this->githubToken);
        } catch (MissingGitHubToken $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->text(sprintf('Validando %s…', $file));
        try {
            $catalog = $this->loader->load($file);
        } catch (InvalidCatalog $e) {
            return $this->fail($io, $e->errors);
        }

        $io->text('Conferindo se os repositórios estão acessíveis…');
        $probe = new GitHubRepositoryProbe($this->httpClient, $token);
        $errors = [];
        foreach ($catalog->projects as $project) {
            try {
                $probe->assertAccessible($project->repo);
            } catch (RepositoryNotAccessible $e) {
                if ($e->affectsAllRepositories) {
                    return $this->fail($io, [ValidationError::general($e->getMessage())]);
                }

                $errors[] = ValidationError::forProject($project->id, 'repo', $e->getMessage());
            }
        }

        if ($errors !== []) {
            return $this->fail($io, $errors);
        }

        $io->success(sprintf(
            '%d projetos em %d categorias, todos com repositório acessível.',
            count($catalog->projects),
            count($catalog->categories),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function fail(SymfonyStyle $io, array $errors): int
    {
        $io->listing(array_map(static fn (ValidationError $error): string => $error->describe(), $errors));
        $io->error(count($errors) === 1 ? '1 problema encontrado.' : sprintf('%d problemas encontrados.', count($errors)));

        return Command::FAILURE;
    }
}
