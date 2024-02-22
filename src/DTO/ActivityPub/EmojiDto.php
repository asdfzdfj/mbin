<?php

declare(strict_types=1);

namespace App\DTO\ActivityPub;

use App\Entity\Emoji;
use Symfony\Component\Validator\Constraints as Assert;

class EmojiDto
{
    #[Assert\NotBlank]
    public string $shortcode;

    public ?string $category;

    #[Assert\Url]
    public ?string $apId;

    #[Assert\When(expression: 'null === this.apId', constraints: [new Assert\EqualTo('local')])]
    #[Assert\When(expression: 'null !== this.apId', constraints: [new Assert\Hostname()])]
    public string $apDomain;

    /**
     * emoji icon _source_ file path.
     *
     * the file must exists if set
     */
    #[Assert\File]
    public ?string $sourceFile;

    /** emoji icon source url */
    #[Assert\Url]
    public ?string $sourceUrl;

    public function __construct(
        string $shortcode,
        string $category = null,
        string $apId = null,
        string $sourceFile = null,
        string $sourceUrl = null,
    ) {
        $this->shortcode = $shortcode;
        $this->category = $category;
        $this->apId = $apId;
        $this->sourceFile = $sourceFile;
        $this->sourceUrl = $sourceUrl;

        $this->updateApDomain($apId);
    }

    public function updateApDomain(?string $apId)
    {
        if (!$apId) {
            $this->apDomain = 'local';
        } elseif ($domain = parse_url($apId, PHP_URL_HOST)) {
            $this->apDomain = $domain;
        } else {
            throw new \InvalidArgumentException("Invalid supplied apId '{$apId}' to update apDomain");
        }

        return $this;
    }

    public static function createFromEntity(Emoji $emoji)
    {
        return new self(
            $emoji->shortcode,
            $emoji->category,
            $emoji->apId,
            sourceUrl: $emoji->iconUrl,
        );
    }
}
