<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class EmojiExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
    }

    public function getEmojiOptions(ActivityPubActivityInterface|ActivityPubActorInterface $entity): array
    {
        return [
            'enable' => true,
            'domain' => $entity->getApDomain() ?? 'local',
        ];
    }
}
