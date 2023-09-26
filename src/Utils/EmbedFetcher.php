<?php

declare(strict_types=1);

namespace App\Utils;

use App\Service\SettingsManager;
use Embed\Embed as BaseEmbed;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbedFetcher
{
    public function __construct(
        private CacheInterface $cache,
        private SettingsManager $settings,
        private HttpClientInterface $httpClient,
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

                // workaround: the embed extractor part doesn't seems to update its data properly
                // if the url redirects, resolve the url first before fetching embed
                try {
                    $resp = $this->httpClient->request('GET', $url, [
                        'timeout' => 5,
                        'max_duration' => 10,
                    ]);
                    $resp->getHeaders();
                    $resp->cancel();
                    $resolvedUrl = $resp->getInfo('url');

                    if ($resp->getInfo('redirect_count')) {
                        $this->logger->debug('EmbedFetcher:fetch: redirected:', [
                            $url => $resolvedUrl,
                        ]);
                    }

                    $fetcher = new BaseEmbed();
                    $embed = $fetcher->get($resolvedUrl);
                    $oembed = $embed->getOEmbed();
                } catch (\Exception $e) {
                    $this->logger->debug('EmbedFetcher:fetch: fetch fail: '.$e->getMessage(), [
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
                } elseif ($embed->code) {
                    $data->useHTML($embed->code->html);
                }

                $this->logger->debug('EmbedFetcher:fetch: fetch success: ', [
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
