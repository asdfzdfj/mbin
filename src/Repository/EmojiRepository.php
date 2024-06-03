<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Emoji;
use App\PageView\EmojiPageView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends ServiceEntityRepository<Emoji>
 *
 * @method Emoji|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emoji|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emoji[]    findAll()
 * @method Emoji[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method Emoji|null findOneByApId(string $apId)
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
     * lookup emojis by list of shortcodes scoped to a domain.
     *
     * @param string $domain the domain to search for this shortcode,
     *                       using special value `local` to search for local custom emojis
     *
     * @return Emoji[] found emojis with matching shortcodes for that domain
     */
    public function findByShortcodes(array $shortcodes, string $domain = 'local'): array
    {
        return $this->findBy(['apDomain' => $domain, 'shortcode' => $shortcodes]);
    }

    /**
     * lookup a single emoji by shortcode scoped to a domain.
     *
     * @param string $shortcode the emoji shortcode to search for, without enclosing `:`
     * @param string $domain    the domain to search for this shortcode,
     *                          using special value `local` to search for local custom emojis
     */
    public function findOneByShortcode(string $shortcode, string $domain = 'local'): ?Emoji
    {
        $emojis = $this->findByShortcodes([$shortcode], $domain);

        return array_pop($emojis);
    }

    /**
     * get a mapping of all known emojis by domain.
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

    /**
     * get a mapping of all known emojis on local instance.
     *
     * @return array<string, Emoji> an arrray mapping of shortcode => Emoji entity
     */
    public function findAllLocal(): array
    {
        return $this->findAllByDomain('local');
    }

    public function getCategories(): array
    {
        return $this->_em
            ->createQuery(
                'SELECT DISTINCT e.category
                FROM '.Emoji::class.' e
                WHERE e.category IS NOT NULL
                ORDER BY e.category ASC'
            )
            ->getSingleColumnResult();
    }

    public function getDomains(): array
    {
        return $this->_em
            ->createQuery(
                'SELECT DISTINCT e.apDomain
                FROM '.Emoji::class." e
                WHERE e.apDomain != 'local'
                ORDER BY e.apDomain ASC"
            )
            ->getSingleColumnResult();
    }

    public function findPaginated(EmojiPageView $criteria): PagerfantaInterface
    {
        $qb = $this->createQueryBuilder('e');

        if ($criteria->domain) {
            $qb->andWhere('e.apDomain = :domain')
                ->setParameter('domain', $criteria->domain);
        }

        if ($criteria->category) {
            if (EmojiPageView::CATEGORY_UNCATEGORIZED === $criteria->category) {
                $qb->andWhere('e.category IS NULL');
            } else {
                $qb->andWhere('e.category = :cat')
                   ->setParameter('cat', trim($criteria->category));
            }
        }

        if ($criteria->query) {
            $qb->andWhere('LOWER(e.shortcode) LIKE :query')
               ->setParameter('query', '%'.addcslashes(trim($criteria->query), '%_\\').'%');
        }

        $qb->addOrderBy('e.shortcode', 'ASC');

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));

        try {
            $pagerfanta->setMaxPerPage($criteria->perPage ?? EmojiPageView::PER_PAGE);
            $pagerfanta->setCurrentPage($criteria->page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }
}
