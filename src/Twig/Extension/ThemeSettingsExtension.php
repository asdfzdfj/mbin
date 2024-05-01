<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\ThemeSettingsExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeSettingsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_setting_constant', [ThemeSettingsExtensionRuntime::class, 'getConstant']),
            new TwigFunction('theme_setting_value', [ThemeSettingsExtensionRuntime::class, 'themeSettingsValue']),
        ];
    }
}
