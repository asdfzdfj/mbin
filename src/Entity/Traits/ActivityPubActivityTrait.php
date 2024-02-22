<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait ActivityPubActivityTrait
{
    #[Column(type: 'string', unique: true, nullable: true)]
    public ?string $apId = null;

    public function getApDomain(): ?string
    {
        return $this->apId ? parse_url($this->apId, PHP_URL_HOST) : null;
    }
}
