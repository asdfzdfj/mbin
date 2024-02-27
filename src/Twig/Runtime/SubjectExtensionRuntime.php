<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class SubjectExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        // Inject dependencies if needed
    }

    /**
     * @param iterable<Entry|Post|EntryComment|PostComment> $replies
     */
    public function sortAuthorFirst(
        iterable $replies,
        Entry|Post|EntryComment|PostComment $root,
        bool $enabled = true
    ): iterable {
        if (!$enabled) {
            return $replies;
        }

        $authorReplies = [];
        $otherReplies = [];

        foreach ($replies as $reply) {
            if ($reply->user === $root->user) {
                $authorReplies[] = $reply;
            } else {
                $otherReplies[] = $reply;
            }
        }

        return array_merge($authorReplies, $otherReplies);
    }
}
