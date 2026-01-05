<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Find articles with pagination and filters
     * 
     * @return array{items: Article[], total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?int $moduleId = null,
        ?string $query = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($moduleId !== null) {
            $qb->innerJoin('a.modules', 'm')
                ->andWhere('m.id = :moduleId')
                ->setParameter('moduleId', $moduleId);
        }

        if ($query !== null && $query !== '') {
            $qb->andWhere('a.originalTitle LIKE :query OR a.suggestedTitle LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        // Clone for count
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(DISTINCT a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Apply pagination
        $items = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => (int) $total,
        ];
    }

    /**
     * Find articles by module with pagination
     * 
     * @return array{items: Article[], total: int}
     */
    public function findByModulePaginated(int $moduleId, int $page = 1, int $limit = 20): array
    {
        return $this->findPaginated($page, $limit, null, $moduleId);
    }
}

