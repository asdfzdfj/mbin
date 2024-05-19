<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Exception\InvalidApGetException;
use App\Repository\ApActivityRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ApObjectManager
{
    public function __construct(
        private readonly ApActivityRepository $activityRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClient $apHttpClient,
        private readonly Note $note,
        private readonly Page $page,
    ) {
    }

    private function getLinkedObjectId(array $object, string $key): ?string
    {
        return ApObjectExtractor::getLinkedObjectId($object, $key);
    }

    public function findObjectOrCreate(string $apId): null|Entry|EntryComment|Post|PostComment
    {
        if ($entityInfo = $this->activityRepository->findByObjectId($apId)) {
            return $this->activityRepository->resolve($entityInfo);
        }

        if ($object = $this->apHttpClient->getActivityObject($apId)) {
            $this->createObjectsFromChain($this->resolveReplyToChain($object));

            return $this->createSingleObject($object);
        }

        return null;
    }

    /**
     * create entry/post/comment entity from supplied AP object.
     *
     * this will refuse to create entity from object that's part of a reply chain
     * but the replied to object isn't known (i.e. no corresponding object in db).
     */
    public function createSingleObject(array $object): null|Entry|EntryComment|Post|PostComment
    {
        $type = $object['type'] ?? null;
        if (!$type) {
            throw new \InvalidArgumentException('supplied object lacks type');
        }

        $inReplyTo = $this->getLinkedObjectId($object, 'inReplyTo');
        if ($inReplyTo && !$this->activityRepository->findByObjectId($inReplyTo)) {
            $this->logger->warning(
                'supplied object has parent {parent} but no existing parent object, aborting',
                ['parent' => $inReplyTo],
            );

            return null;
        }

        $entity = match ($type) {
            'Note', 'Question' => $this->note->create($object),
            'Page', 'Article' => $this->page->create($object),
            default => null,
        };

        return $entity;
    }

    /**
     * resolve the chain of replied objects of the supplied object.
     *
     * @return string[] list of AP objects making up the reply chain that isn't yet known to us,
     *                  excluding the supplied object, topmost parent first
     */
    public function resolveReplyToChain(array $object): array
    {
        $chain = [];
        $inReplyTo = $this->getLinkedObjectId($object, 'inReplyTo');

        while ($inReplyTo && !$this->activityRepository->findByObjectId($inReplyTo)) {
            $parent = $this->apHttpClient->getActivityObject($inReplyTo);
            if (!$parent) {
                throw new InvalidApGetException("fetching {$inReplyTo} returned no results");
            } elseif (!\is_array($parent)) {
                $actualType = \gettype($parent);
                throw new InvalidApGetException("fetching {$inReplyTo} array expected, {$actualType} returned");
            }

            $chain[] = $parent['id'];
            $inReplyTo = $this->getLinkedObjectId($parent, 'inReplyTo');
        }

        $chain = array_reverse($chain);

        return $chain;
    }

    /**
     * create all objects (entries/posts) from a supplied list of AP objects.
     *
     * it'll try its best to make sure that all objects are processed.
     *
     * @param string[] $chain list of AP objects to create entities
     */
    public function createObjectsFromChain(array $chain)
    {
        foreach ($chain as $objectId) {
            $object = $this->apHttpClient->getActivityObject($objectId);
            if (!$object) {
                $this->logger->error('failed to fetch object {id} in chain', ['id' => $objectId, 'chain' => $chain]);

                throw new \RuntimeException("failed to fetch object in the chain {$objectId}");
            }

            $entity = $this->createSingleObject($object);
            if (!$entity) {
                $this->logger->error('failed to create all objects in chain {id}', ['id' => $objectId, 'chain' => $chain]);

                throw new \RuntimeException("failed to create all objects in the chain {$objectId}");
            }
        }
    }
}
