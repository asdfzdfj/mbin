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
            new TwigFunction('theme_setting_value', [ThemeSettingsExtensionRuntime::class, 'getValue']),
            // the following functions have knowledge of central default value
            new TwigFunction('theme_setting_default', [ThemeSettingsExtensionRuntime::class, 'getDefault']),
            new TwigFunction('theme_setting_get', [ThemeSettingsExtensionRuntime::class, 'getEffectiveValue']),
            new TwigFunction('theme_setting_is', [ThemeSettingsExtensionRuntime::class, 'themeSettingEquals']),
        ];
    }
}
