<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleVersion;
use App\Repository\ArticleVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Business logic for articles: versioning, status transitions
 */
class ArticleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleVersionRepository $articleVersionRepository,
    ) {
    }

    /**
     * Create a new version for an article
     */
    public function createVersion(Article $article, string $content): ArticleVersion
    {
        $maxVersion = $this->articleVersionRepository->getMaxVersionNumber($article->getId());
        $newVersionNumber = $maxVersion + 1;

        $version = new ArticleVersion();
        $version->setArticle($article);
        $version->setContent($content);
        $version->setVersionNumber($newVersionNumber);

        $article->addArticleVersion($version);

        $this->entityManager->persist($version);

        return $version;
    }

    /**
     * Check if a status transition is valid
     */
    public function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            Article::STATUS_PROPOSED => [Article::STATUS_WRITING],
            Article::STATUS_WRITING => [Article::STATUS_WRITTEN, Article::STATUS_PROPOSED],
            Article::STATUS_WRITTEN => [Article::STATUS_VALIDATED, Article::STATUS_WRITING],
            Article::STATUS_VALIDATED => [Article::STATUS_PUBLISHED, Article::STATUS_WRITING],
            Article::STATUS_PUBLISHED => [Article::STATUS_WRITING], // Allow re-writing
        ];

        if (!isset($validTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $validTransitions[$currentStatus], true);
    }

    /**
     * Process the callback from Make.com webhook
     */
    public function processWriteCallback(
        Article $article,
        string $content,
        ?string $suggestedTitle = null,
        ?string $suggestedDescription = null,
        ?int $score = null
    ): ArticleVersion {
        // Create new version
        $version = $this->createVersion($article, $content);

        // Update article with suggested values
        if ($suggestedTitle !== null) {
            $article->setSuggestedTitle($suggestedTitle);
        }
        if ($suggestedDescription !== null) {
            $article->setSuggestedDescription($suggestedDescription);
        }
        if ($score !== null) {
            $article->setScore($score);
        }

        // Update status to written
        $article->setStatus(Article::STATUS_WRITTEN);

        $this->entityManager->flush();

        return $version;
    }
}
