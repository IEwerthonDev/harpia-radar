<?php

declare(strict_types=1);

namespace HarpiaRadar\Catalog;

use InvalidArgumentException;
use Stringable;

/**
 * Referência a um repositório numa forja, no formato "github:dono/nome".
 */
final readonly class RepoRef implements Stringable
{
    private const string PATTERN = '~^(github):([A-Za-z0-9][A-Za-z0-9-]*)/([A-Za-z0-9._-]+)$~';

    public function __construct(
        public string $forge,
        public string $owner,
        public string $name,
    ) {
    }

    public static function parse(string $value): self
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid repository reference: "%s".', $value));
        }

        return new self($matches[1], $matches[2], $matches[3]);
    }

    public function __toString(): string
    {
        return sprintf('%s:%s/%s', $this->forge, $this->owner, $this->name);
    }
}
