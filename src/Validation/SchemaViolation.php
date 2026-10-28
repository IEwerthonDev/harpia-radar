<?php

declare(strict_types=1);

namespace HarpiaRadar\Validation;

final readonly class SchemaViolation
{
    /**
     * @param list<string|int> $path caminho até o valor inválido, incluindo o campo ausente ou desconhecido
     */
    public function __construct(
        public array $path,
        public string $keyword,
        public string $message,
    ) {
    }
}
