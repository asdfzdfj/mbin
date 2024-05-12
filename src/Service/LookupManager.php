<?php

declare(strict_types=1);

namespace App\Service;

use App\ActivityPub\ActorHandle;
use App\Entity\User;
use App\Repository\ApActivityRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\ApObjectManager;
use Psr\Log\LoggerInterface;

class LookupManager
{
    public function __construct(
        private readonly MagazineRepository $magazineRepository,
        private readonly UserRepository $userRepository,
        private readonly ApActivityRepository $activityRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApObjectManager $apObjectManager,
        private readonly ApHttpClient $apHttpClient,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isLocalActorHandle(ActorHandle $handle): bool
    {
        return $this->settingsManager->get('KBIN_DOMAIN') === $handle->getDomain()
            || !$handle->getDomain();
    }

    private function filterResult(array $result): array
    {
        return array_values(
            array_filter($result, fn ($i) => !\is_null($i))
        );
    }

    // entrypoint function

    public function lookupActorByHandle(ActorHandle $handle, bool $fetchAllowed = false): array
    {
        $this->logger->debug('looking up handle {handle}', ['handle' => $handle]);
        $result = $this->lookupLocalHandle($handle);

        if (!$result && $fetchAllowed) {
            $this->logger->debug(
                'no local copy found, looking up handle {handle} from remote sources',
                ['handle' => $handle]
            );

            $result = $this->lookupRemoteHandle($handle);
        }

        return $result;
    }

    public function lookupByApId(string $apId, bool $fetchAllowed = false): array
    {
        $this->logger->debug('looking up ap id {apId}', ['apId' => $apId]);

        $result = $this->lookupLocalApId($apId);
        if ($result) {
            return $result;
        }

        $effectiveApId = $this->resolveAlternateApId($apId);
        if ($effectiveApId !== $apId) {
            $this->logger->debug('found alternate id {altApId} for {apId}', ['apId' => $apId, 'altApId' => $effectiveApId]);
        }

        $this->logger->debug('looking up alternate ap id {apId}', ['apId' => $effectiveApId]);

        $result = $this->lookupLocalApId($effectiveApId);
        if ($result) {
            return $result;
        }

        if ($fetchAllowed) {
            $this->logger->debug(
                'no local copy found, looking up id {apId} from remote sources',
                ['apId' => $effectiveApId]
            );

            $result = $this->lookupRemoteApId($effectiveApId);
        }

        return $result;
    }

    // NOTE: Local for this context means whether we have local copy of data in db or not
    //       Remote means fetching from remote instances, and will most likely results in new local object(s)

    public function lookupLocalHandle(ActorHandle $handle): array
    {
        if ($this->isLocalActorHandle($handle)) {
            $user = $this->userRepository->findOneByUsername($handle->name);
            $magazine = $this->magazineRepository->findOneByName($handle->name);
        } else {
            $user = $this->userRepository->findOneBy(['apId' => $handle->plainHandle()]);
            $magazine = $this->magazineRepository->findOneBy(['apId' => $handle->plainHandle()]);
        }

        $result = '!' === $handle->prefix
            ? [$magazine]
            : [$user, $magazine];

        return $this->filterResult($result);
    }

    public function lookupRemoteHandle(ActorHandle $handle): array
    {
        $objects = [];
        $name = $handle->plainHandle();

        try {
            $webfinger = $this->activityPubManager->webfinger($name);
            foreach ($webfinger->getProfileIds() as $profileId) {
                $this->logger->debug('Found "{profileId}" at "{name}"', ['profileId' => $profileId, 'name' => $name]);

                if ($actor = $this->activityPubManager->findActorOrCreate($profileId)) {
                    $objects[] = $actor;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                'an error occurred during webfinger lookup of "{handle}": {exceptionClass}: {message}',
                [
                    'handle' => $name,
                    'exceptionClass' => \get_class($e),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }

        return $objects;
    }

    /**
     * search local repo/db for any object matching supplied ap id.
     *
     * this should only ever result in either empty or one element array.
     */
    public function lookupLocalApId(string $apId): array
    {
        $results = [
            $this->userRepository->findOneBy(['apProfileId' => $apId]),
            $this->magazineRepository->findOneBy(['apProfileId' => $apId]),
            $this->activityRepository->resolve(
                $this->activityRepository->findByObjectId($apId),
            ),
        ];

        return $this->filterResult($results);
    }

    public function lookupRemoteApId(string $apId): array
    {
        $object = $this->apHttpClient->getActivityObject($apId);

        $entityTypes = ['Article', 'Page', 'Note', 'Question'];
        $actorType = array_merge(User::USER_TYPES, ['Group']);

        $type = $object['type'] ?? null;
        // this should ever results in one distinct entity type being returned
        $result = match (true) {
            \in_array($type, $actorType) => $this->activityPubManager->findActorOrCreate($apId),
            \in_array($type, $entityTypes) => $this->apObjectManager->findObjectOrCreate($apId),
            default => null,
        };

        return $this->filterResult([$result]);
    }

    public function resolveAlternateApId(string $apId): string
    {
        $object = $this->apHttpClient->getActivityObject($apId);
        $fetchedApId = $object['id'];

        if ($apId === $fetchedApId) {
            return $apId;
        }

        $refetchObject = $this->apHttpClient->getActivityObject($fetchedApId);
        $refetchApId = $refetchObject['id'];

        if (!\in_array($refetchApId, [$apId, $fetchedApId])) {
            throw new \RuntimeException("something sus with fetching from {$apId} -> {$fetchedApId} -> {$refetchApId}");
        }

        return $refetchApId;
    }
}
