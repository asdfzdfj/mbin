<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmojiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmojiRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_domain_shortcode_idx', fields: ['apDomain', 'shortcode'])]
#[ORM\UniqueConstraint(fields: ['apId'])]
#[ORM\Index(fields: ['category'])]
#[ORM\Index(fields: ['apDomain'])]
class Emoji
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    public ?string $shortcode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $category = null;

    #[ORM\ManyToOne(targetEntity: EmojiIcon::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    public ?EmojiIcon $icon = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $iconUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $apId = null;

    /**
     * @var ?string the domain this emoji shortcode belings to.
     *
     * a special value of `local` denotes a local custom emoji
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $apDomain = 'local';

    public function __construct(
        EmojiIcon $icon,
        string $shortcode,
        string $category = null,
        string $apId = null,
        string $iconUrl = null,
    ) {
        $this->icon = $icon;
        $this->shortcode = $shortcode;
        $this->category = $category;
        $this->apId = $apId;
        $this->iconUrl = $iconUrl;

        if ($apId && $domain = parse_url($apId, PHP_URL_HOST)) {
            $this->apDomain = $domain;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIconPath(): string
    {
        return $this->icon->filePath;
    }

    public function isRemote(): bool
    {
        return !empty($this->apId);
    }

    public function formatShortcode(): string
    {
        return ":{$this->shortcode}:";
    }
}
