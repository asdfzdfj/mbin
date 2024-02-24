<?php

declare(strict_types=1);

namespace App\Markdown\Event;

use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use League\CommonMark\Node\Block\Document;
use Symfony\Contracts\EventDispatcher\Event;

class ParseMarkdown extends Event
{
    private Document $document;
    private array $attributes = [];

    public function __construct(private string $markdown)
    {
    }

    public function getMarkdown(): string
    {
        return $this->markdown;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): void
    {
        $this->document = $document;
    }

    public function getRenderTarget(): RenderTarget
    {
        return $this->getAttribute(MarkdownConverter::RENDER_TARGET) ?? RenderTarget::Page;
    }

    /**
     * @return mixed|null
     */
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function addAttribute(string $key, $data): void
    {
        $this->attributes[$key] = $data;
    }

    public function mergeAttributes(array $attributes): void
    {
        $this->attributes = array_replace($this->attributes, $attributes);
    }

    public function removeAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }
}
