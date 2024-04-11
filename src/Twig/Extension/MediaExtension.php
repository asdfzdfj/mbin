<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\MediaExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MediaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('uploaded_asset', [MediaExtensionRuntime::class, 'getPublicPath']),
            new TwigFunction('emoji_asset', [MediaExtensionRuntime::class, 'getEmoijPath']),
        ];
    }
}
