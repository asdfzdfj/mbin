<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Controller\User\ThemeSettingsController;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class ThemeSettingsExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getConstant(string $key): null|string|array
    {
        // this being unguarded of any sort is intentional
        // in real use it should never fail and if it does it should fail loudly
        return \constant(ThemeSettingsController::class."::{$key}");
    }

    public function themeSettingsValue(string $key, mixed $default = null): mixed
    {
        // alternate: consider passing in app.cookies in here rather than using RequestStack?
        $cookies = $this->requestStack->getCurrentRequest()->cookies;

        return $cookies->get($this->getConstant($key), $default);
    }
}
