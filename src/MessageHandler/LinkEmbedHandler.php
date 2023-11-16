<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Embed;
use App\Markdown\Event\BuildCacheContext;
use App\Markdown\Event\ConvertMarkdown;
use App\Message\Contracts\MessageInterface;
use App\Message\LinkEmbedMessage;
use App\Repository\EmbedRepository;
use App\Utils\Embed as EmbedFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LinkEmbedHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmbedRepository $embedRepository,
        private readonly EmbedFetcher $embed,
        private readonly CacheItemPoolInterface $markdownCache,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(LinkEmbedMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof LinkEmbedMessage)) {
            throw new \LogicException();
        }
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $message->body, $match);

        foreach ($match[0] as $url) {
            try {
                $embed = $this->embed->fetch($url)->html;
                if ($embed) {
                    $entity = new Embed($url, true);
                    $this->embedRepository->add($entity);
                }
            } catch (\Exception $e) {
                $embed = false;
            }

            if (!$embed) {
                $entity = new Embed($url, false);
                $this->embedRepository->add($entity);
            }
        }

        $cacheContext = new BuildCacheContext(new ConvertMarkdown($message->body));
        $this->dispatcher->dispatch($cacheContext);
        $this->markdownCache->deleteItem($cacheContext->getCacheKey());
    }
}
