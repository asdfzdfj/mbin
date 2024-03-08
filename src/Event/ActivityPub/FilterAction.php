<?php

declare(strict_types=1);

namespace App\Event\ActivityPub;

enum FilterAction
{
    case PASS;
    case DROP;
    case REJECT;
    case MODIFIED;
}
