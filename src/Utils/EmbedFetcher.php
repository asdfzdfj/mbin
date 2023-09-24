<?php

declare(strict_types=1);

namespace App\Utils;

use App\Service\SettingsManager;
use Embed\Embed as BaseEmbed;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class EmbedFetcher
{
    public function __construct(
        private CacheInterface $cache,
        private SettingsManager $settings,
        private LoggerInterface $logger,
    ) {
    }

    public function fetch($url): Embed
    {
        if ($this->settings->isLocalUrl($url)) {
            // shouldn't we fill some data in here?
            return new Embed($url);
        }

        return $this->cache->get(
            'embed_'.md5($url),
            function (ItemInterface $item) use ($url): Embed {
                $item->expiresAfter(3600);

                try {
                    $embed = (new BaseEmbed())->get($url);
                    $oembed = $embed->getOEmbed();
                } catch (\Exception $e) {
                    $this->logger->debug('EmbedFetcher: fetch fail: '.$e->getMessage(), [
                        'url' => $url,
                    ]);

                    return new Embed();
                }

                $c = new Embed(
                    (string) $embed->url,
                    $embed->title,
                    $embed->description,
                    (string) $embed->image,
                );

                if ($oembed->html('html')) {
                    $c->html = $this->cleanIframe($oembed->html('html'));
                } elseif (!$c->html && $embed->code) {
                    $c->html = $this->cleanIframe($embed->code->html);
                }

                $this->logger->debug('EmbedFetcher: fetch success: ', [
                    'url' => ['in' => $url, 'out' => $c->url],
                    'title' => $c->title,
                    'description' => $c->description,
                    'image' => $c->image,
                    'html' => $this->firstTag($c->html),
                ]);

                return $c;
            }
        );
    }

    private function firstTag(?string $html): string
    {
        preg_match('/^<[^>]+>/', $html ?? '', $firstTag);

        return $firstTag[0] ?? '';
    }

    private function cleanIframe(?string $html): ?string
    {
        if (!$html || str_contains($html, 'wp-embedded-content')) {
            return null;
        }

        return $html;
    }
}
