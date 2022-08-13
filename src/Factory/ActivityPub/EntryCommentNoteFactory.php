<?php declare(strict_types=1);

namespace App\Factory\ActivityPub;

use ApiPlatform\Core\Api\UrlGeneratorInterface;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\EntryComment;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Wrapper\ImageWrapper;
use App\Service\ActivityPub\Wrapper\MentionsWrapper;
use App\Service\ActivityPub\Wrapper\TagsWrapper;
use App\Service\ActivityPubManager;
use DateTimeInterface;

class EntryCommentNoteFactory
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private GroupFactory $groupFactory,
        private ImageWrapper $imageWrapper,
        private TagsWrapper $tagsWrapper,
        private MentionsWrapper $mentionsWrapper,
        private EntryPageFactory $pageFactory,
        private ApHttpClient $client,
        private ActivityPubManager $activityPubManager
    ) {
    }

    public function create(EntryComment $comment, bool $context = false): array
    {
        if ($context) {
            $note['@context'] = [
                ActivityPubActivityInterface::CONTEXT_URL,
                ActivityPubActivityInterface::SECURITY_URL,
                PostNoteFactory::getContext(),
            ];
        }

        $note = [
            'type'         => 'Note',
            '@context'     => [ActivityPubActivityInterface::CONTEXT_URL, ActivityPubActivityInterface::SECURITY_URL],
            'id'           => $this->getActivityPubId($comment),
            'attributedTo' => $this->activityPubManager->getActorProfileId($comment->user),
            'inReplyTo'    => $comment->apId ??
                $comment->parent ? $this->getActivityPubId($comment->parent) : $this->pageFactory->getActivityPubId($comment->entry),
            'to'           => [
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc'           => [
                $this->groupFactory->getActivityPubId($comment->magazine),
                $comment->apId
                    ? $this->client->getActorObject($comment->user->apProfileId)['followers']
                    : $this->urlGenerator->generate('ap_user_followers', ['username' => $comment->user->username], UrlGeneratorInterface::ABS_URL),
            ],
            'content'      => $comment->body,
            'mediaType'    => 'text/html',
            'url'          => $this->getActivityPubId($comment),
            'tag'          => $this->tagsWrapper->build($comment->tags) + $this->mentionsWrapper->build($comment->mentions),
            'published'    => $comment->createdAt->format(DateTimeInterface::ISO8601),
        ];

        if ($comment->image) {
            $note = $this->imageWrapper->build($note, $comment->image, $comment->getShortTitle());
        }

        return $note;
    }

    public function getActivityPubId(EntryComment $comment): string
    {
        return $this->urlGenerator->generate(
            'ap_entry_comment',
            ['magazine_name' => $comment->magazine->name, 'entry_id' => $comment->entry->getId(), 'comment_id' => $comment->getId()],
            UrlGeneratorInterface::ABS_URL
        );
    }
}