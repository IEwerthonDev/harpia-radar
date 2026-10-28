<?php

declare(strict_types=1);

namespace HarpiaRadar\Health;

enum Activity: string
{
    case Active = 'active';
    case ActiveNoRecentRelease = 'active_no_recent_release';
    case NoRecentChanges = 'no_recent_changes';
    case Archived = 'archived';
}
