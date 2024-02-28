<?php

declare(strict_types=1);

namespace App\PageView;

class EmojiPageView
{
    public const PER_PAGE = 60;
    public const CATEGORY_UNCATEGORIZED = '-';

    public ?string $query = null;

    public function __construct(
        public int $page,
        public ?string $category = null,
        public ?int $perPage = null,
    ) {
    }
}
