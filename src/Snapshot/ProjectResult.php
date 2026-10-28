<?php

declare(strict_types=1);

namespace HarpiaRadar\Snapshot;

use HarpiaRadar\Health\Health;
use HarpiaRadar\Metrics\Metrics;

final readonly class ProjectResult
{
    private function __construct(
        public string $id,
        public ?Metrics $metrics,
        public ?Health $health,
        public ?string $error,
    ) {
    }

    public static function ok(string $id, Metrics $metrics, Health $health): self
    {
        return new self($id, $metrics, $health, null);
    }

    public static function error(string $id, string $message): self
    {
        return new self($id, null, null, $message);
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->metrics === null || $this->health === null) {
            return ['id' => $this->id, 'status' => 'error', 'error' => $this->error, 'metrics' => null, 'health' => null];
        }

        return [
            'id' => $this->id,
            'status' => 'ok',
            'metrics' => $this->metrics->toArray(),
            'health' => $this->health->toArray(),
        ];
    }
}
