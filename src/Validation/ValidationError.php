<?php

declare(strict_types=1);

namespace HarpiaRadar\Validation;

/**
 * Um problema encontrado na lista curada, descrito para quem edita o YAML.
 */
final readonly class ValidationError
{
    private function __construct(
        public string $message,
        public ?string $project = null,
        public ?string $category = null,
        public ?string $field = null,
    ) {
    }

    public static function general(string $message): self
    {
        return new self($message);
    }

    public static function forField(?string $field, string $message): self
    {
        return new self($message, field: $field);
    }

    public static function forProject(string $project, ?string $field, string $message): self
    {
        return new self($message, project: $project, field: $field);
    }

    public static function forCategory(string $category, ?string $field, string $message): self
    {
        return new self($message, category: $category, field: $field);
    }

    public function describe(): string
    {
        $parts = [];
        if ($this->project !== null) {
            $parts[] = 'Projeto ' . $this->project;
        } elseif ($this->category !== null) {
            $parts[] = 'Categoria ' . $this->category;
        }

        if ($this->field !== null) {
            $parts[] = ($parts === [] ? 'Campo ' : 'campo ') . $this->field;
        }

        $message = str_ends_with($this->message, '.') ? $this->message : $this->message . '.';

        return $parts === [] ? $message : implode(', ', $parts) . ': ' . $message;
    }
}
