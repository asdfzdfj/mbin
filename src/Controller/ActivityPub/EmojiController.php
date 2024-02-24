<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Entity\Emoji;
use App\Factory\ActivityPub\EmojiFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmojiController extends AbstractController
{
    public function __construct(private readonly EmojiFactory $emojiFactory)
    {
    }

    public function __invoke(
        #[MapEntity(expr: 'repository.findOneByShortcode(shortcode)')]
        Emoji $emoji,
        Request $request
    ): JsonResponse {
        $response = new JsonResponse($this->emojiFactory->create($emoji, true));

        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}
