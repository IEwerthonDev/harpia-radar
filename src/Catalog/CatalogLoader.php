<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

use HarpiaRadar\Validation\CatalogValidator;
use HarpiaRadar\Validation\SchemaValidator;
use HarpiaRadar\Validation\SchemaViolation;
use HarpiaRadar\Validation\ValidationError;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lê projects.yaml, valida contra o schema e as regras do catálogo e devolve um Catalog.
 */
final readonly class CatalogLoader
{
    public function __construct(
        private string $schemaFile,
        private SchemaValidator $schemaValidator = new SchemaValidator(),
        private CatalogValidator $catalogValidator = new CatalogValidator(),
    ) {
    }

    /**
     * @throws InvalidCatalog
     */
    public function load(string $path): Catalog
    {
        if (!is_file($path)) {
            throw new InvalidCatalog([ValidationError::general(sprintf('Arquivo não encontrado: %s', $path))]);
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new InvalidCatalog([ValidationError::general('YAML inválido: ' . $e->getMessage())]);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidCatalog([ValidationError::general('O arquivo precisa ter as chaves categories e projects.')]);
        }

        $errors = [
            ...array_map(
                fn (SchemaViolation $violation): ValidationError => $this->toError($violation, $data),
                $this->schemaValidator->validate($data, $this->schemaFile),
            ),
            ...$this->catalogValidator->validate($data),
        ];

        if ($errors !== []) {
            throw new InvalidCatalog($errors);
        }

        /** @var array{categories: list<array{id: string, name: string}>, projects: list<array{id: string, name: string, repo: string, category: string, description: string, language?: string, replaces?: string, tags?: list<string>}>} $data */
        return new Catalog(
            array_map(
                static fn (array $category): Category => new Category($category['id'], $category['name']),
                $data['categories'],
            ),
            array_map(
                static fn (array $project): Project => new Project(
                    $project['id'],
                    $project['name'],
                    RepoRef::parse($project['repo']),
                    $project['category'],
                    $project['description'],
                    $project['language'] ?? null,
                    $project['replaces'] ?? null,
                    $project['tags'] ?? [],
                ),
                $data['projects'],
            ),
        );
    }

    /**
     * @param array<mixed> $data
     */
    private function toError(SchemaViolation $violation, array $data): ValidationError
    {
        $path = $violation->path;
        $field = $path === [] ? null : (string) end($path);
        $message = match (true) {
            $violation->keyword === 'pattern' && in_array($field, ['id', 'category'], true)
                => 'use letras minúsculas, números e hífens (ex.: sped-nfe)',
            $violation->keyword === 'pattern' && $field === 'repo'
                => 'use o formato github:dono/repositório',
            default => $violation->message,
        };

        if (count($path) >= 2 && is_int($path[1])) {
            $entry = is_array($data[$path[0]] ?? null) ? ($data[$path[0]][$path[1]] ?? null) : null;
            $rest = array_slice($path, 2);
            $subfield = $rest === [] ? null : implode('.', $rest);

            if ($path[0] === 'projects') {
                return ValidationError::forProject(CatalogValidator::projectLabel($entry, $path[1]), $subfield, $message);
            }

            if ($path[0] === 'categories') {
                return ValidationError::forCategory('#' . ($path[1] + 1), $subfield, $message);
            }
        }

        return ValidationError::forField($path === [] ? null : implode('.', $path), $message);
    }
}
