<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\AsyncApMessageInterface;

class ChainActivityMessage implements AsyncApMessageInterface
{
    /**
     * @param array[] $chain    list of chained message objects
     * @param ?array  $parent   sentinel value marking that known parent exist and has been found
     *                          somewhat unused
     * @param ?array  $announce announce activity that starts this chain
     * @param ?array  $like     like activity that starts this chain
     * @param ?array  $dislike  dislike activity that starts this chain
     */
    public function __construct(
        public array $chain,
        public ?array $parent = null,
        public ?array $announce = null,
        public ?array $like = null,
        public ?array $dislike = null,
    ) {
    }
}
