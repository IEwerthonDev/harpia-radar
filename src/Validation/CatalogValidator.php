<?php

declare(strict_types=1);

namespace HarpiaRadar\Validation;

/**
 * Regras do catálogo que o JSON Schema não expressa: ids únicos e categorias declaradas.
 *
 * Trabalha sobre os dados crus e ignora valores que o schema já rejeita, para que todos os
 * problemas apareçam de uma vez.
 */
final readonly class CatalogValidator
{
    public const string ID_PATTERN = '~^[a-z0-9]+(-[a-z0-9]+)*$~';

    /**
     * Como um projeto é chamado nas mensagens: pelo id, se for válido; senão, pela posição.
     */
    public static function projectLabel(mixed $entry, int $index): string
    {
        $id = is_array($entry) ? ($entry['id'] ?? null) : null;

        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1 ? $id : '#' . ($index + 1);
    }

    /**
     * @param array<mixed> $data
     * @return list<ValidationError>
     */
    public function validate(array $data): array
    {
        $categories = self::entries($data['categories'] ?? null);
        $projects = self::entries($data['projects'] ?? null);
        $errors = [];

        foreach (self::duplicates($categories) as [$index, $id, $firstIndex]) {
            $errors[] = ValidationError::forCategory(
                '#' . ($index + 1),
                'id',
                sprintf('o id "%s" já é usado pela categoria #%d', $id, $firstIndex + 1),
            );
        }

        foreach (self::duplicates($projects) as [$index, $id, $firstIndex]) {
            $errors[] = ValidationError::forProject(
                sprintf('%s (#%d)', $id, $index + 1),
                'id',
                sprintf('o id "%s" já é usado pelo projeto #%d', $id, $firstIndex + 1),
            );
        }

        $declared = self::categoryIds($categories);
        if ($declared === null) {
            return $errors;
        }

        foreach ($projects as $index => $project) {
            $category = $project['category'] ?? null;
            if (is_string($category) && !in_array($category, $declared, true)) {
                $errors[] = ValidationError::forProject(
                    self::projectLabel($project, $index),
                    'category',
                    sprintf('a categoria "%s" não está declarada em categories', $category),
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array<mixed>>
     */
    private static function entries(mixed $list): array
    {
        if (!is_array($list) || !array_is_list($list)) {
            return [];
        }

        return array_filter($list, is_array(...));
    }

    /**
     * @param array<int, array<mixed>> $entries
     * @return list<array{int, string, int}> [posição, id repetido, posição da primeira ocorrência]
     */
    private static function duplicates(array $entries): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($entries as $index => $entry) {
            $id = $entry['id'] ?? null;
            if (!is_string($id)) {
                continue;
            }

            if (isset($seen[$id])) {
                $duplicates[] = [$index, $id, $seen[$id]];
            } else {
                $seen[$id] = $index;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<int, array<mixed>> $categories
     * @return list<string>|null null quando algum id é inválido e não dá para saber o que foi declarado
     */
    private static function categoryIds(array $categories): ?array
    {
        if ($categories === []) {
            return null;
        }

        $ids = [];
        foreach ($categories as $category) {
            $id = $category['id'] ?? null;
            if (!is_string($id)) {
                return null;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
