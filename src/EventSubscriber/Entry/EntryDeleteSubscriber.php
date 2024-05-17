<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryBeforePurgeEvent;
use App\Event\Entry\EntryDeletedEvent;
use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Repository\EntryRepository;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use App\Service\NotificationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class EntryDeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntryRepository $entryRepository,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly NotificationManager $notificationManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryDeletedEvent::class => 'onEntryDeleted',
            EntryBeforePurgeEvent::class => 'onEntryBeforePurge',
        ];
    }

    public function onEntryDeleted(EntryDeletedEvent $event): void
    {
        $this->notificationManager->sendDeleted($event->entry);
    }

    public function onEntryBeforePurge(EntryBeforePurgeEvent $event): void
    {
        $event->entry->magazine->entryCount = $this->entryRepository->countEntriesByMagazine($event->entry->magazine) - 1;

        $this->notificationManager->sendDeleted($event->entry);

        if (!$event->entry->apId) {
            $this->bus->dispatch(
                new DeleteMessage(
                    $this->deleteWrapper->build($event->entry, Uuid::v4()->toRfc4122()),
                    $event->entry->user->getId(),
                    $event->entry->magazine->getId()
                )
            );
        }
    }
}
