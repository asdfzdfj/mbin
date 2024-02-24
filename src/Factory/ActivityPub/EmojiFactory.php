<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Emoji;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\EmojiManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmojiFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly EmojiManager $emojiManager,
    ) {
    }

    public function create(Emoji $emoji, bool $context = true): array
    {
        if ($context) {
            $object['@context'] = $this->contextProvider->referencedContexts();
        }

        $object = array_merge(
            $object ?? [],
            [
                'id' => $this->urlGenerator->generate(
                    'ap_emoji',
                    ['shortcode' => $emoji->shortcode],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'type' => 'Emoji',
                'name' => $emoji->formatShortcode(),
                'icon' => [
                    'type' => 'Image',
                    'mediaType' => $this->emojiManager->getMimetype($emoji),
                    'url' => $this->emojiManager->getUrl($emoji),
                ],
            ]
        );

        return $object;
    }
}
