<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmojiIcon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<EmojiIcon>
 *
 * @method EmojiIcon|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmojiIcon|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmojiIcon|null findOneBySha256($sha256)
 * @method EmojiIcon[]    findAll()
 * @method EmojiIcon[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmojiIconRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, EmojiIcon::class);
    }

    public function save(EmojiIcon $icon, bool $flush = true)
    {
        $this->_em->persist($icon);
        if ($flush) {
            $this->_em->flush();
        }
    }
}
