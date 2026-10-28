<?php

declare(strict_types=1);

namespace HarpiaRadar\Snapshot;

use HarpiaRadar\Validation\SchemaValidator;
use HarpiaRadar\Validation\SchemaViolation;
use JsonException;

final readonly class SnapshotReader
{
    public function __construct(
        private string $schemaFile,
        private SchemaValidator $schemaValidator = new SchemaValidator(),
    ) {
    }

    /**
     * @return array<string, mixed> o snapshot decodificado, já validado contra o schema
     *
     * @throws InvalidSnapshot
     */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidSnapshot(sprintf('Snapshot não encontrado: %s. Rode bin/radar collect antes.', $path));
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidSnapshot(sprintf('Snapshot inválido em %s: %s.', $path, $e->getMessage()));
        }

        $violations = $this->schemaValidator->validate($data, $this->schemaFile);
        if ($violations !== [] || !is_array($data)) {
            throw new InvalidSnapshot(sprintf('Snapshot inválido em %s: %s.', $path, implode('; ', array_map(
                static fn (SchemaViolation $v): string => implode('.', $v->path) . ': ' . $v->message,
                $violations,
            ))));
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
