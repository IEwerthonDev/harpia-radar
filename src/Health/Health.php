<?php

declare(strict_types=1);

namespace HarpiaRadar\Health;

final readonly class Health
{
    public function __construct(
        public Activity $activity,
        public Sustainability $sustainability,
    ) {
    }

    /**
     * @return array{activity: string, sustainability: string}
     */
    public function toArray(): array
    {
        return ['activity' => $this->activity->value, 'sustainability' => $this->sustainability->value];
    }
}
