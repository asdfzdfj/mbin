<?php

declare(strict_types=1);

namespace App\EventSubscriber\ActivityPub;

use App\Event\ActivityPub\InboxFilterEvent;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPubManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeletedUserExistSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InboxFilterEvent::class => 'validateActorSelfDelete',
        ];
    }

    public function validateActorSelfDelete(InboxFilterEvent $event)
    {
        if (!$object = $event->payload) {
            return;
        }

        if ('Delete' !== $object['type']) {
            return;
        }

        $actor = $object['actor'] ?? null;
        $target = $object['object'] ?? null;

        if ($actor !== $target) {
            return;
        }

        $entity = $this->userRepository->findOneBy(['apProfileId' => $target])
            ?? $this->magazineRepository->findOneBy(['apProfileId' => $target]);

        if ($entity) {
            return;
        }

        $event->dropMessage("no local copy of the actor {$target} to delete");
    }
}
