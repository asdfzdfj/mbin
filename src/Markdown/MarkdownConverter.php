<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Markdown\Event\ConvertMarkdown;
use App\Markdown\Event\ParseMarkdown;
use League\CommonMark\Node\Block\Document;
use Psr\EventDispatcher\EventDispatcherInterface;

class MarkdownConverter
{
    public const RENDER_TARGET = 'render_target';

    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function convertToHtml(string $markdown, array $context = []): string
    {
        $event = new ConvertMarkdown($markdown);
        $event->mergeAttributes($context);

        $this->dispatcher->dispatch($event);

        return (string) $event->getRenderedContent();
    }

    public function parse(string $markdown, array $context = []): Document
    {
        $event = new ParseMarkdown($markdown);
        $event->mergeAttributes($context);

        $this->dispatcher->dispatch($event);

        return $event->getDocument();
    }
}
