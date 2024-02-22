<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\Emoji;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class EmojiParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\B:([\pL\pN\pM_+-]+):\B');
    }

    public function parse(InlineParserContext $ctx): bool
    {
        $cursor = $ctx->getCursor();
        $cursor->advanceBy($ctx->getFullMatchLength());

        $full = $ctx->getFullMatch();
        $matches = $ctx->getSubMatches();
        $shortcode = $matches['0'];

        $ctx->getContainer()->appendChild(new Emoji($full, ['shortcode' => $shortcode]));

        return true;
    }
}
