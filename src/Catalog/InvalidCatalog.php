<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

use HarpiaRadar\Validation\ValidationError;
use RuntimeException;

final class InvalidCatalog extends RuntimeException
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", array_map(
            static fn (ValidationError $error): string => $error->describe(),
            $errors,
        )));
    }
}
