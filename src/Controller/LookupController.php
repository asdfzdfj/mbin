<?php

declare(strict_types=1);

namespace App\Controller;

use App\ActivityPub\ActorHandle;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Service\ActivityPubManager;
use App\Service\LookupManager;
use App\Service\SearchManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LookupController extends AbstractController
{
    public function __construct(
        private readonly LookupManager $lookupManager,
        private readonly SearchManager $searchManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $query = $request->query->get('q') ? trim($request->query->get('q')) : null;

        if (!$query) {
            return $this->render(
                'search/lookup.html.twig',
                [
                    'objects' => [],
                    'q' => '',
                ]
            );
        }

        $this->logger->debug('looking up {query}', ['query' => $query]);

        $results = [];
        $fetchAllowed = $this->isFetchAllowed();

        try {
            if ($handle = ActorHandle::parse($query)) {
                $results = $this->lookupManager->lookupActorByHandle($handle, $fetchAllowed);
            } elseif ($url = filter_var($query, FILTER_VALIDATE_URL)) {
                $results = $this->lookupManager->lookupByApId($url, $fetchAllowed);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'error while looking up {query}: {msg}',
                ['query' => $query, 'msg' => $e->getMessage(), 'ex' => $e]
            );

            $this->addFlash('error', 'flash_lookup_error_occurred');
        }

        $this->logger->debug('lookup result', [
            'result' => array_map(
                fn ($obj) => $obj?->getId() ? [\get_class($obj), $obj->getId()] : $obj,
                $results
            ),
        ]);

        return $this->render(
            'search/lookup.html.twig',
            [
                'objects' => $this->formatObjectResult($results),
                'q' => $request->query->get('q'),
            ]
        );
    }

    private function isFetchAllowed(): bool
    {
        return $this->searchManager->isFederateSearchAllowed($this->getUser());
    }

    private function formatObjectResult(array $resultObjects): array
    {
        $objectClass = [Entry::class, EntryComment::class, Post::class, PostComment::class];

        return array_map(
            fn ($object) => match (true) {
                $object instanceof User => ['type' => 'user', 'object' => $object],
                $object instanceof Magazine => ['type' => 'magazine', 'object' => $object],
                \in_array(\get_class($object), $objectClass) => $object,
                default => null,
            },
            $resultObjects
        );
    }
}
