<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub;

use App\Message\ActivityPub\UpdateActorMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPubManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateActorHandler
{
    public function __construct(
        private readonly ActivityPubManager $manager,
        private readonly LockFactory $lockFactory,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateActorMessage $message): void
    {
        $actorUrl = $message->actorUrl;
        if ($key = $message->retrieveKey()) {
            $lock = $this->lockFactory->createLockFromKey($key, 60);
        } else {
            $key = new Key('update_actor_'.hash('sha256', $actorUrl));
            $lock = $this->lockFactory->createLockFromKey($key, 60);
            $lock->acquire();
        }

        if (!$lock->isAcquired()) {
            $this->logger->debug(
                'not updating actor at {url}: ongoing actor update is already in progress',
                ['url' => $actorUrl]
            );

            return;
        }

        $actor = $this->userRepository->findOneBy(['apProfileId' => $actorUrl])
            ?? $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);

        if ($actor) {
            if ($message->force || $actor->apFetchedAt < (new \DateTime())->modify('-1 hour')) {
                $this->manager->updateActor($actorUrl);
            } else {
                $this->logger->debug('not updating actor {url}: last updated is recent: {fetched}', [
                    'url' => $actorUrl,
                    'fetched' => $actor->apFetchedAt,
                ]);
            }
        }

        $lock->release();
    }
}
