<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmojiIconRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmojiIconRepository::class)]
#[ORM\UniqueConstraint(name: 'emoji_icon_file_name_idx', fields: ['fileName'])]
#[ORM\UniqueConstraint(name: 'emoji_icon_sha256_idx', fields: ['sha256'])]
class EmojiIcon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    public ?string $fileName = null;

    #[ORM\Column(type: Types::TEXT)]
    public ?string $filePath = null;

    #[ORM\Column(type: Types::BINARY)]
    public $sha256;

    public function __construct(
        string $fileName,
        string $sha256,
    ) {
        $this->updateFilename($fileName);
        $this->updateSha256($sha256);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * sets fileName *and* filePath from a given filename.
     *
     * @param string $fileName new filename to use
     */
    public function updateFilename(string $fileName): static
    {
        $this->fileName = $fileName;
        $this->filePath = sprintf('%s/%s/%s', substr($fileName, 0, 2), substr($fileName, 2, 2), $fileName);

        return $this;
    }

    /**
     * helper methos to set sha256 field value.
     *
     * @param string $sha256 sha256 in either hex string or raw binary form
     *
     * @throws InvalidArgumentException if supplied input makformed or cannot be converted to valid binary value
     */
    public function updateSha256($sha256): static
    {
        error_clear_last();

        $sha256bin = match (\strlen($sha256)) {
            64 => @hex2bin($sha256),
            32 => $sha256,
            default => throw new \InvalidArgumentException('supplied sha256 is neither in binary nor hex form'),
        };

        if (false === $sha256bin) {
            throw new \InvalidArgumentException('supplied sha256 appears to be invalid '.error_get_last()['message']);
        }

        $this->sha256 = $sha256bin;

        return $this;
    }
}
