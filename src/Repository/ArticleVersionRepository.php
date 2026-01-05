<?php

namespace App\Repository;

use App\Entity\ArticleVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleVersion>
 */
class ArticleVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleVersion::class);
    }

    /**
     * Get the maximum version number for an article
     */
    public function getMaxVersionNumber(int $articleId): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('MAX(v.versionNumber)')
            ->where('v.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}

