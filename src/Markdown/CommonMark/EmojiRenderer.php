<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\Emoji;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Repository\EmojiRepository;
use App\Service\EmojiManager;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use Psr\Log\LoggerInterface;

class EmojiRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
    private ConfigurationInterface $config;

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function __construct(
        private readonly EmojiRepository $emojiRepository,
        private readonly EmojiManager $emojiManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param Emoji $node
     */
    public function render(
        Node $node,
        ChildNodeRendererInterface $childRenderer
    ): HtmlElement|string {
        Emoji::assertInstanceOf($node);

        $config = $this->config->get('kbin');
        $renderTarget = $config[MarkdownConverter::RENDER_TARGET];

        $enable = $this->config->get('kbin.emoji.enable');
        $domain = $this->config->get('kbin.emoji.domain');

        $shortcode = $node->data->get('shortcode');

        // disable emoji rendering when rendering to AP, explicitly disabled
        // or missing required data to render
        if (RenderTarget::ActivityPub === $renderTarget || !$enable || !$domain || !$shortcode) {
            return $node->getLiteral();
        }

        $emojis = $this->emojiRepository->findAllByDomain($domain);
        $this->logger->debug('available emoji for {domain}:', [
            'shortcode' => $shortcode,
            'domain' => $domain,
            'emojis' => \count($emojis) <= 50 ? array_keys($emojis) : \count($emojis),
        ]);

        if ($emoji = $emojis[$shortcode] ?? null) {
            $iconPath = $this->emojiManager->getUrl($emoji);
            $this->logger->debug('selected emoji for {shortcode}:', [
                'shortcode' => $shortcode,
                'emoji' => $emoji,
                'iconPath' => $iconPath,
            ]);

            return new HtmlElement(
                'img',
                [
                    'class' => 'emoji',
                    'title' => $node->getLiteral(),
                    'src' => $iconPath,
                    'loading' => 'lazy',
                ],
                '',
                true
            );
        } else {
            $this->logger->debug('no emoji found for {shortcode} at {domain}:', [
                'shortcode' => $shortcode,
                'domain' => $domain,
            ]);

            return $node->getLiteral();
        }
    }
}
