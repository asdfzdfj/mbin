<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Controller\User\ThemeSettingsController;
use App\Service\ThemeSettingsManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class ThemeSettingsExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ThemeSettingsManager $themeSettingsManager,
    ) {
    }

    public function getConstant(string $key): null|string|array
    {
        // this being unguarded of any sort is intentional
        // in real use it should never fail and if it does it should fail loudly
        return \constant(ThemeSettingsManager::class."::{$key}");
    }

    /**
     * get theme setting value by key, or return supplied default.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        // alternate: consider passing in app.cookies in here rather than using RequestStack?
        $cookies = $this->requestStack->getCurrentRequest()->cookies;

        return $cookies->get($this->getConstant($key), $default);
    }

    /**
     * get fallback/hardcoded default for a particulat theme setting key.
     */
    public function getDefault(string $key, bool $getKey = true): ?string
    {
        return $this->themeSettingsManager->getDefaultSetting($key, $getKey);
    }

    /**
     * get theme setting value for given key if set or return defaults for that key if unset.
     *
     * shorthand for `{{ theme_setting_value(key, theme_setting_default(key)) }}`.
     */
    public function getEffectiveValue(string $key): mixed
    {
        return $this->getValue($key, $this->getDefault($key));
    }

    /**
     * compare the theme setting with expected value constant.
     *
     * all parameters of this function is the name constants defined in ThemeSettingsController/ThemeSettingsManager,
     * hardcoded defaults will be assumed if setting value isn't set.
     *
     * @param string $settingKey theme setting constant name to check setting value
     * @param string $valueKey   theme setting constant name which contains expected value
     */
    public function themeSettingEquals(string $settingKey, string $valueKey): bool
    {
        $expected = $this->getConstant($valueKey);
        $actual = $this->getEffectiveValue($settingKey);

        return $expected === $actual;
    }
}
