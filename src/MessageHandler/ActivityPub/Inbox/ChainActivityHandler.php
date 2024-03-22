<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ChainActivityHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ApHttpClient $client,
        private readonly MessageBusInterface $bus,
        private readonly ApActivityRepository $repository,
        private readonly Note $note,
        private readonly Page $page
    ) {
    }

    public function __invoke(ChainActivityMessage $message): void
    {
        // build the full chain
        $chain = $message->chain;

        // old handler compat: if parent is set then skip chain building
        if (!$message->parent) {
            $chain = $this->buildChain($message->chain);

            $last = end($chain);
            $message->parent = !empty($last['id'])
                ? $this->repository->findByObjectId($last['id'])
                : null;
        }

        if (!$chain) {
            $this->logger->debug('activity chain is empty, not processing');

            return;
        }

        $this->logger->debug('complete chain', ['chain' => array_map(fn ($o) => $o['id'] ?? null, $chain)]);

        // make entities from chain
        while ($object = array_pop($chain)) {
            $entity = $this->processObject($object);

            if (!$entity) {
                $this->logger->debug('unable to process object found in chain, dropping chain', ['object' => $object]);

                return;
            }
        }

        // redispatch root message of the chain after all parents have been processed
        // ($chain should now be empty)
        if (!$chain) {
            $this->redispatchRootMessage($message);
        }
    }

    private function buildChain(array $chain): array
    {
        $object = end($chain);
        if (!$object) {
            return [];
        }

        $inReplyTo = $object['inReplyTo'] ?? null;
        $existed = $inReplyTo ? $this->repository->findByObjectId($inReplyTo) : null;

        while ($inReplyTo && !$existed) {
            $this->logger->debug(
                'fetching unknown parent {parent} for object {object}',
                ['parent' => $inReplyTo, 'object' => $object['id']]
            );

            $object = $this->client->getActivityObject($inReplyTo);
            $chain[] = $object;

            $inReplyTo = $object['inReplyTo'] ?? null;
            $existed = $inReplyTo ? $this->repository->findByObjectId($inReplyTo) : null;
        }

        return $chain;
    }

    private function isProcessable(mixed $object): bool
    {
        return !empty($object)
            && \is_array($object)
            && !array_is_list($object)
            && $this->getType($object);
    }

    private function processObject(array $object): null|Entry|EntryComment|Post|PostComment
    {
        if (!$this->isProcessable($object)) {
            return null;
        }

        $type = $this->getType($object);

        $entity = match ($type) {
            'Note', 'Question' => $this->note->create($object),
            'Page', 'Article' => $this->page->create($object),
            default => null,
        };

        return $entity;
    }

    private function redispatchRootMessage(ChainActivityMessage $message)
    {
        // only one of $announce, $like and $dislike should ever be set
        if ($message->announce) {
            $this->bus->dispatch(new AnnounceMessage($message->announce));
        } elseif ($message->like) {
            $this->bus->dispatch(new LikeMessage($message->like));
        } elseif ($message->dislike) {
            $this->bus->dispatch(new DislikeMessage($message->dislike));
        }
    }

    private function getType(array $object): string
    {
        if (isset($object['object']) && \is_array($object['object'])) {
            return $object['object']['type'] ?? null;
        }

        return $object['type'] ?? null;
    }
}
