<?php

declare(strict_types=1);

namespace App\Event\ActivityPub;

use Symfony\Contracts\EventDispatcher\Event;

class InboxFilterEvent extends Event
{
    private FilterAction $action = FilterAction::PASS;
    private array $reasons = [];

    public function __construct(
        public array $payload,
        public string $body,
        public ?array $request = null,
        public ?array $headers = null
    ) {
    }

    public function getAction(): FilterAction
    {
        return $this->action;
    }

    /** @return string[] */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    private function addReason(string $reason)
    {
        if (!\in_array($reason, $this->reasons)) {
            $this->reasons[] = $reason;
        }
    }

    public function isPassed(): bool
    {
        return FilterAction::PASS === $this->action;
    }

    public function dropMessage(string $reason)
    {
        $this->action = FilterAction::DROP;
        $this->addReason($reason);
        $this->stopPropagation();
    }

    public function rejectMessage(string $reason)
    {
        $this->action = FilterAction::REJECT;
        $this->addReason($reason);
        $this->stopPropagation();
    }
}
