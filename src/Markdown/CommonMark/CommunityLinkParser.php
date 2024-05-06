<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\ActorSearchLink;
use App\Markdown\CommonMark\Node\CommunityLink;
use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Repository\MagazineRepository;
use App\Service\SettingsManager;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommunityLinkParser implements InlineParserInterface
{
    public function __construct(
        private readonly MagazineRepository $magazineRepository,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\B!(\w+)(@(?:[\pL\pN\pS\pM\-\_]++\.)+[a-z0-9\-\_]++|[\pL\pN\pM]++)?');
    }

    public function parse(InlineParserContext $ctx): bool
    {
        $cursor = $ctx->getCursor();
        $cursor->advanceBy($ctx->getFullMatchLength());

        $matches = $ctx->getSubMatches();
        $handle = $matches['0'];
        $domain = $this->extractDomain($matches['1']) ?? $this->settingsManager->get('KBIN_DOMAIN');

        $fullHandle = $handle.'@'.$domain;
        $isRemote = $this->isRemoteCommunity($domain);
        $magazine = $this->magazineRepository->findOneByName($isRemote ? $fullHandle : $handle);

        if ($magazine) {
            $ctx->getContainer()->appendChild(
                new CommunityLink(
                    $this->urlGenerator->generate('front_magazine', ['name' => $magazine->name]),
                    '!'.$handle,
                    '!'.($isRemote ? $magazine->apId : $magazine->name),
                    $isRemote ? $magazine->apId : $magazine->name,
                    $isRemote ? MentionType::RemoteMagazine : MentionType::Magazine,
                ),
            );

            return true;
        }

        if ($isRemote) {
            $ctx->getContainer()->appendChild(
                new ActorSearchLink(
                    $this->urlGenerator->generate('search', ['q' => $fullHandle], UrlGeneratorInterface::ABSOLUTE_URL),
                    '!'.$handle,
                    '!'.$fullHandle,
                )
            );

            return true;
        }

        // unable to resolve a local '!' link so don't even try.
        $ctx->getContainer()->appendChild(new UnresolvableLink('!'.$handle));

        return true;
    }

    private function extractDomain(?string $match): ?string
    {
        if (!$match || !str_starts_with($match, '@')) {
            return null;
        }

        return ltrim($match, '@');
    }

    private function isRemoteCommunity(?string $domain): bool
    {
        return $domain !== $this->settingsManager->get('KBIN_DOMAIN');
    }
}
