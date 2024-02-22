<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\EmojiExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EmojiExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('emoji_option', [EmojiExtensionRuntime::class, 'getEmojiOptions']),
        ];
    }
}
