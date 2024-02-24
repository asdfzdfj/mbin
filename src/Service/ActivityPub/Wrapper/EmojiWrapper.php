<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Emoji;
use App\Factory\ActivityPub\EmojiFactory;
use App\Repository\EmojiRepository;
use App\Service\EmojiManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmojiWrapper
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EmojiRepository $emojiRepository,
        private readonly EmojiFactory $emojiFactory,
        private readonly EmojiManager $emojiManager,
    ) {
    }

    public function build(?array $shortcodes, string $body = null): array
    {
        $shortcodes = array_unique(array_merge(
            $shortcodes ?? [],
            $this->emojiManager->extractFromBody($body)
        ));

        $emojis = $this->emojiRepository->findByShortcodes($shortcodes);

        return array_map(
            fn (Emoji $emoji) => $this->emojiFactory->create($emoji, false),
            $emojis
        );
    }
}
