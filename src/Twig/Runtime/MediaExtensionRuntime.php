<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Emoji;
use App\Entity\Image;
use App\Service\EmojiManager;
use Twig\Extension\RuntimeExtensionInterface;

class MediaExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly string $storageUrl,
        private readonly EmojiManager $emojiManager,
    ) {
    }

    public function getPublicPath(Image $image): ?string
    {
        if ($image->filePath) {
            return $this->storageUrl.'/'.$image->filePath;
        }

        return $image->sourceUrl;
    }

    public function getEmoijPath(Emoji $emoji): string
    {
        return $this->emojiManager->getUrl($emoji);
    }
}
