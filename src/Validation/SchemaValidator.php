<?php

declare(strict_types=1);

namespace HarpiaRadar\Validation;

use JsonException;
use Opis\JsonSchema\Errors\ValidationError as OpisError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use RuntimeException;

final class SchemaValidator
{
    private readonly Validator $validator;

    /** @var array<string, string> arquivo do schema => $id registrado */
    private array $registered = [];

    public function __construct()
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(100);
        $this->validator->setStopAtFirstError(false);
    }

    /**
     * @return list<SchemaViolation>
     */
    public function validate(mixed $data, string $schemaFile): array
    {
        $error = $this->validator->validate(Helper::toJSON($data), $this->schemaId($schemaFile))->error();
        if ($error === null) {
            return [];
        }

        $violations = [];
        $this->collect($error, $violations);

        return $violations;
    }

    private function schemaId(string $schemaFile): string
    {
        if (isset($this->registered[$schemaFile])) {
            return $this->registered[$schemaFile];
        }

        try {
            $schema = json_decode((string) file_get_contents($schemaFile), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Invalid JSON schema %s: %s', $schemaFile, $e->getMessage()), 0, $e);
        }

        if (!is_array($schema) || !is_string($schema['$id'] ?? null)) {
            throw new RuntimeException(sprintf('JSON schema %s has no $id.', $schemaFile));
        }

        $this->validator->resolver()?->registerFile($schema['$id'], $schemaFile);

        return $this->registered[$schemaFile] = $schema['$id'];
    }

    /**
     * @param list<SchemaViolation> $violations
     */
    private function collect(OpisError $error, array &$violations): void
    {
        if ($error->subErrors() !== []) {
            foreach ($error->subErrors() as $subError) {
                $this->collect($subError, $violations);
            }

            return;
        }

        /** @var list<string|int> $path */
        $path = $error->data()->fullPath();
        $keyword = $error->keyword();
        $args = $error->args();

        if ($keyword === 'required' || $keyword === 'additionalProperties') {
            /** @var list<string> $fields */
            $fields = $keyword === 'required' ? $args['missing'] : $args['properties'];
            if ($keyword === 'additionalProperties') {
                // O opis também acusa aqui os campos declarados cujo valor falhou em "properties".
                $fields = array_values(array_diff($fields, self::declaredProperties($error)));
            }

            foreach ($fields as $field) {
                $violations[] = new SchemaViolation(
                    [...$path, $field],
                    $keyword,
                    $keyword === 'required' ? 'campo obrigatório ausente' : 'campo desconhecido',
                );
            }

            return;
        }

        $violations[] = new SchemaViolation($path, $keyword, self::message($keyword, $args));
    }

    /**
     * @return list<string>
     */
    private static function declaredProperties(OpisError $error): array
    {
        $schema = $error->schema()->info()->data();
        $properties = is_object($schema) ? ($schema->properties ?? null) : null;

        return is_object($properties) ? array_keys(get_object_vars($properties)) : [];
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function message(string $keyword, array $args): string
    {
        return match ($keyword) {
            'type' => sprintf('tipo inválido (esperado: %s)', implode(' ou ', (array) $args['expected'])),
            'pattern' => 'formato inválido',
            'format' => sprintf('formato inválido (esperado: %s)', self::scalar($args['format'] ?? '')),
            'minLength' => $args['min'] === 1
                ? 'não pode ficar vazio'
                : sprintf('precisa ter pelo menos %s caracteres', self::scalar($args['min'])),
            'minItems' => sprintf('precisa ter pelo menos %s item(ns)', self::scalar($args['min'])),
            'uniqueItems' => 'tem itens repetidos',
            'enum', 'const' => 'valor não permitido',
            'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum' => 'valor fora do intervalo permitido',
            default => sprintf('valor inválido (%s)', $keyword),
        };
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
