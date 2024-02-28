<?php

declare(strict_types=1);

namespace App\Service;

class VideoManager
{
    public const VIDEO_MIMETYPES = ['video/mp4', 'video/webm'];

    public static function isVideoUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return false;
        }

        $urlExt = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $types = array_map(fn ($type) => str_replace('video/', '', $type), self::VIDEO_MIMETYPES);

        return \in_array($urlExt, $types);
    }

    public static function isVideoType(string $mediaType): bool
    {
        return \in_array($mediaType, self::VIDEO_MIMETYPES);
    }
}
