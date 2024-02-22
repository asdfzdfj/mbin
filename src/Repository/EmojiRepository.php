<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Emoji;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Emoji>
 *
 * @method Emoji|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emoji|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emoji[]    findAll()
 * @method Emoji[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmojiRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Emoji::class);
    }

    public function save(Emoji $emoji, bool $flush = true)
    {
        $this->_em->persist($emoji);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * lookup a single emoji by shortcode scoped to a domain.
     *
     * @param string $shortcode the emoji shortcode (without enclosing `:`) to search for
     * @param string $domain    the domain to search for this shortcode,
     *                          using special value `local` to search for local custom emojis
     */
    public function findOneByShortcode(string $shortcode, string $domain = 'local'): ?Emoji
    {
        $emojis = $this->findByShortcodes([$shortcode], $domain);

        return array_pop($emojis);
    }

    /**
     * get a list of all known emojis for a domain.
     *
     * @param string $domain the domain to search for this shortcode,
     *                       using special value `local` to search for local custom emojis
     *
     * @return array<string, Emoji> an arrray mapping of shortcode => Emoji entity
     */
    public function findAllByDomain(string $domain = 'local'): array
    {
        $query = $this
            ->createQueryBuilder('e', 'e.shortcode')
            ->andWhere('e.apDomain = :domain')
            ->setParameter('domain', $domain);

        return $query->getQuery()->getResult();
    }

    public function findAllLocal(): array
    {
        return $this->findAllByDomain('local');
    }
}
