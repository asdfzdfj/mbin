<?php

declare(strict_types=1);

namespace App\Message\ActivityPub;

use App\Message\Contracts\ActivityPubInboxInterface;

class CreateEmojiMessage implements ActivityPubInboxInterface
{
    public function __construct(public array $object)
    {
    }
}
