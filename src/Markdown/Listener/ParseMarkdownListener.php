<?php

declare(strict_types=1);

namespace App\Markdown\Listener;

use App\Markdown\Event\ParseMarkdown;
use App\Markdown\Factory\EnvironmentFactory;
use League\CommonMark\Parser\MarkdownParser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ParseMarkdownListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EnvironmentFactory $environmentFactory,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ParseMarkdown::class => ['onParseMarkdown'],
        ];
    }

    public function onParseMarkdown(ParseMarkdown $event): void
    {
        $environment = $this->environmentFactory->createEnvironment(
            $event->getRenderTarget(),
            ['emoji' => $event->getAttribute('emoji')],
        );

        $parser = new MarkdownParser($environment);
        $doc = $parser->parse($event->getMarkdown());

        $event->setDocument($doc);
    }
}
