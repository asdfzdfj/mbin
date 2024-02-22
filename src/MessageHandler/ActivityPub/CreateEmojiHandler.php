<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub;

use App\Message\ActivityPub\CreateEmojiMessage;
use App\Service\EmojiManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateEmojiHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmojiManager $emojiManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateEmojiMessage $message): void
    {
        $emojis = $message->object;
        if (!$emojis) {
            return;
        }

        $this->logger->debug('got new create emoji message:', ['msg' => $message]);

        foreach ($emojis as $emojiObject) {
            $entity = $this->emojiManager->createEmojiFromObject($emojiObject, null);
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
