<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\ActivityPubInboxInterface;

class CreateMessage implements ActivityPubInboxInterface
{
    public function __construct(public array $payload, public ?bool $stickyIt = false)
    {
    }
}
