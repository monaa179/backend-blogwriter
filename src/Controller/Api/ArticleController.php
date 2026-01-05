<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\ModuleRepository;
use App\Service\ArticleService;
use App\Service\MakeWebhookClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articleRepository,
        private ModuleRepository $moduleRepository,
        private ArticleService $articleService,
        private ValidatorInterface $validator,
        private string $webhookSecret,
    ) {
    }

    /**
     * List articles with pagination and filters
     */
    #[Route('', name: 'api_articles_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $status = $request->query->get('status');
        $moduleId = $request->query->get('module_id') ? (int) $request->query->get('module_id') : null;
        $query = $request->query->get('q');

        $result = $this->articleRepository->findPaginated($page, $limit, $status, $moduleId, $query);

        return $this->json([
            'items' => array_map(fn(Article $a) => $this->serializeArticle($a, false), $result['items']),
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
        ]);
    }

    /**
     * Create a new article
     */
    #[Route('', name: 'api_articles_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'error' => 'invalid_json',
                'details' => ['message' => 'Invalid JSON body']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $constraints = new Assert\Collection(
            fields: [
                'source_url' => [
                    new Assert\NotBlank(),
                    new Assert\Url(),
                    new Assert\Length(max: 500),
                ],
                'original_title' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                ],
                'original_description' => [
                    new Assert\NotBlank(),
                ],
                'modules' => new Assert\Optional([
                    new Assert\Type(type: 'array'),
                ]),
            ],
            allowExtraFields: false
        );

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = trim($violation->getPropertyPath(), '[]');
                $errors[$field] = $violation->getMessage();
            }
            return $this->json([
                'error' => 'validation_error',
                'details' => $errors
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate module IDs exist
        $moduleIds = $data['modules'] ?? [];
        $modules = [];
        if (!empty($moduleIds)) {
            $modules = $this->moduleRepository->findBy(['id' => $moduleIds]);
            $foundIds = array_map(fn($m) => $m->getId(), $modules);
            $missingIds = array_diff($moduleIds, $foundIds);
            if (!empty($missingIds)) {
                return $this->json([
                    'error' => 'validation_error',
                    'details' => ['modules' => 'Module IDs not found: ' . implode(', ', $missingIds)]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Create article
        $article = new Article();
        $article->setSourceUrl($data['source_url']);
        $article->setOriginalTitle($data['original_title']);
        $article->setOriginalDescription($data['original_description']);

        foreach ($modules as $module) {
            $article->addModule($module);
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $this->json($this->serializeArticle($article), Response::HTTP_CREATED);
    }

    /**
     * Get a single article
     */
    #[Route('/{id}', name: 'api_articles_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeArticle($article, true));
    }

    /**
     * Delete an article
     */
    #[Route('/{id}', name: 'api_articles_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($article);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Trigger article writing via Make.com webhook
     */
    #[Route('/{id}/write', name: 'api_articles_write', methods: ['POST'])]
    public function write(int $id, MakeWebhookClient $webhookClient): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        // Update status to writing
        $article->setStatus(Article::STATUS_WRITING);
        $this->entityManager->flush();

        // Send to Make.com webhook
        try {
            $webhookClient->sendArticleForWriting($article);
        } catch (\Exception $e) {
            // Log error but still return accepted (async operation)
            // The Make.com scenario will handle the actual processing
        }

        return $this->json([
            'message' => 'writing_started',
            'article_id' => $article->getId(),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Callback endpoint called by Make.com when article is written
     * Secured by X-WEBHOOK-SECRET header
     */
    #[Route('/{id}/write/callback', name: 'api_articles_write_callback', methods: ['POST'])]
    public function writeCallback(int $id, Request $request): JsonResponse
    {
        // Verify webhook secret
        $providedSecret = $request->headers->get('X-WEBHOOK-SECRET');
        if ($providedSecret !== $this->webhookSecret) {
            return $this->json([
                'error' => 'unauthorized',
                'details' => ['message' => 'Invalid webhook secret']
            ], Response::HTTP_UNAUTHORIZED);
        }

        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['content']) || empty($data['content'])) {
            return $this->json([
                'error' => 'validation_error',
                'details' => ['content' => 'Content is required']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Process the callback
        $this->articleService->processWriteCallback(
            $article,
            $data['content'],
            $data['suggested_title'] ?? null,
            $data['suggested_description'] ?? null,
            isset($data['score']) ? (int) $data['score'] : null
        );

        return $this->json($this->serializeArticle($article, true));
    }

    /**
     * Validate an article
     */
    #[Route('/{id}/validate', name: 'api_articles_validate', methods: ['POST'])]
    public function validateArticle(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if article has at least one version
        if (!$article->hasVersion()) {
            return $this->json([
                'error' => 'validation_error',
                'details' => ['message' => 'Article must have at least one version before validation']
            ], Response::HTTP_BAD_REQUEST);
        }

        $article->setStatus(Article::STATUS_VALIDATED);
        $this->entityManager->flush();

        return $this->json($this->serializeArticle($article));
    }

    /**
     * Publish an article
     */
    #[Route('/{id}/published', name: 'api_articles_publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Article not found']
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if article is validated
        if ($article->getStatus() !== Article::STATUS_VALIDATED) {
            return $this->json([
                'error' => 'validation_error',
                'details' => ['message' => 'Article must be validated before publishing']
            ], Response::HTTP_BAD_REQUEST);
        }

        $article->setStatus(Article::STATUS_PUBLISHED);
        $this->entityManager->flush();

        return $this->json($this->serializeArticle($article));
    }

    /**
     * Serialize article to array
     */
    private function serializeArticle(Article $article, bool $includeVersions = true): array
    {
        $modules = [];
        foreach ($article->getModules() as $module) {
            $modules[] = [
                'id' => $module->getId(),
                'name' => $module->getName(),
                'slug' => $module->getSlug(),
                'active' => $module->isActive(),
            ];
        }

        $data = [
            'id' => $article->getId(),
            'source_url' => $article->getSourceUrl(),
            'original_title' => $article->getOriginalTitle(),
            'original_description' => $article->getOriginalDescription(),
            'suggested_title' => $article->getSuggestedTitle(),
            'suggested_description' => $article->getSuggestedDescription(),
            'score' => $article->getScore(),
            'status' => $article->getStatus(),
            'created_at' => $article->getCreatedAt()?->format('c'),
            'updated_at' => $article->getUpdatedAt()?->format('c'),
            'modules' => $modules,
        ];

        if ($includeVersions) {
            $latestVersion = $article->getLatestVersion();
            $data['latest_version'] = $latestVersion ? [
                'id' => $latestVersion->getId(),
                'version_number' => $latestVersion->getVersionNumber(),
                'content' => $latestVersion->getContent(),
                'created_at' => $latestVersion->getCreatedAt()?->format('c'),
            ] : null;

            $data['versions'] = [];
            foreach ($article->getArticleVersions() as $version) {
                $data['versions'][] = [
                    'id' => $version->getId(),
                    'version_number' => $version->getVersionNumber(),
                    'created_at' => $version->getCreatedAt()?->format('c'),
                ];
            }
        }

        return $data;
    }
}
