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

                // workaround: the embed extractor part doesn't seems to update its data properly if the url redirects
                // the crawler follows redirects fine, so refetch embed again with (hopefully) resolved url
                try {
                    $fetcher = new BaseEmbed();
                    $embed = $fetcher->get($url);
                    if ($url !== (string) $embed->url && $embed->getResponse()->getHeaderLine('Location')) {
                        $embed = $fetcher->get((string) $embed->url);
                    }
                    $oembed = $embed->getOEmbed();
                } catch (\Exception $e) {
                    $this->logger->debug('EmbedFetcher: fetch fail: '.$e->getMessage(), [
                        'url' => $url,
                    ]);

                    return new Embed();
                }

                $data = new Embed(
                    (string) $embed->url,
                    $embed->title,
                    $embed->description,
                    (string) $embed->image,
                );

                if ($oembed->html('html')) {
                    $data->useHTML($oembed->html('html'));
                } elseif (!$data->html && $embed->code) {
                    $data->useHTML($embed->code->html);
                }

                $this->logger->debug('EmbedFetcher: fetch success: ', [
                    'url' => ['in' => $url, 'out' => $data->url],
                    'title' => $data->title,
                    'description' => $data->description,
                    'image' => $data->image,
                    'html' => $this->firstTag($data->html),
                ]);

                return $data;
            }
        );
    }

    private function firstTag(?string $html): string
    {
        preg_match('/^<[^>]+>/', $html ?? '', $firstTag);

        return $firstTag[0] ?? '';
    }
}
