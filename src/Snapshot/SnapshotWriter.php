<?php

declare(strict_types=1);

namespace HarpiaRadar\Snapshot;

use HarpiaRadar\Validation\SchemaValidator;
use HarpiaRadar\Validation\SchemaViolation;
use LogicException;
use RuntimeException;

final readonly class SnapshotWriter
{
    public function __construct(
        private string $schemaFile,
        private SchemaValidator $schemaValidator = new SchemaValidator(),
    ) {
    }

    public function write(Snapshot $snapshot, string $path): void
    {
        $data = $snapshot->toArray();
        $violations = $this->schemaValidator->validate($data, $this->schemaFile);
        if ($violations !== []) {
            throw new LogicException('Snapshot does not match its schema: ' . implode('; ', array_map(
                static fn (SchemaViolation $v): string => implode('.', $v->path) . ' ' . $v->keyword,
                $violations,
            )));
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ) . "\n";

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, recursive: true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório %s.', $dir));
        }

        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, $json) === false || !rename($temporary, $path)) {
            throw new RuntimeException(sprintf('Não foi possível gravar %s.', $path));
        }
    }
}
