<?php

declare(strict_types=1);

namespace HarpiaRadar\Health;

enum Sustainability: string
{
    case WellDistributed = 'well_distributed';
    case FewPeople = 'few_people';
    case NeedsHands = 'needs_hands';
    case InsufficientData = 'insufficient_data';
}
