<?php

namespace App\Controller\Api;

use App\Entity\Module;
use App\Repository\ArticleRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/modules')]
class ModuleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModuleRepository $moduleRepository,
        private ArticleRepository $articleRepository,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Create a new module
     */
    #[Route('', name: 'api_modules_create', methods: ['POST'])]
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
        
        $constraints = new Assert\Collection([
            'name' => [
                new Assert\NotBlank(),
                new Assert\Length(max: 120),
            ],
            'slug' => [
                new Assert\NotBlank(),
                new Assert\Length(max: 120),
                new Assert\Regex(
                    pattern: '/^[a-z0-9\-]+$/',
                    message: 'Slug must contain only lowercase letters, numbers, and hyphens'
                ),
            ],
            'active' => new Assert\Optional([
                new Assert\Type(type: 'bool'),
            ]),
        ]);

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

        // Check for unique slug
        $existingModule = $this->moduleRepository->findOneBy(['slug' => $data['slug']]);
        if ($existingModule) {
            return $this->json([
                'error' => 'conflict',
                'details' => ['slug' => 'A module with this slug already exists']
            ], Response::HTTP_CONFLICT);
        }

        // Create module
        $module = new Module();
        $module->setName($data['name']);
        $module->setSlug($data['slug']);
        $module->setActive($data['active'] ?? true);

        $this->entityManager->persist($module);
        $this->entityManager->flush();

        return $this->json($this->serializeModule($module), Response::HTTP_CREATED);
    }

    /**
     * Update a module
     */
    #[Route('/{id}', name: 'api_modules_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $module = $this->moduleRepository->find($id);

        if (!$module) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Module not found']
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'error' => 'invalid_json',
                'details' => ['message' => 'Invalid JSON body']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate fields (all optional for update)
        $constraints = new Assert\Collection(
            fields: [
                'name' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                ]),
                'slug' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 120),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9\-]+$/',
                        message: 'Slug must contain only lowercase letters, numbers, and hyphens'
                    ),
                ]),
                'active' => new Assert\Optional([
                    new Assert\Type(type: 'bool'),
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

        // Check for unique slug if changing
        if (isset($data['slug']) && $data['slug'] !== $module->getSlug()) {
            $existingModule = $this->moduleRepository->findOneBy(['slug' => $data['slug']]);
            if ($existingModule) {
                return $this->json([
                    'error' => 'conflict',
                    'details' => ['slug' => 'A module with this slug already exists']
                ], Response::HTTP_CONFLICT);
            }
        }

        // Update module
        if (isset($data['name'])) {
            $module->setName($data['name']);
        }
        if (isset($data['slug'])) {
            $module->setSlug($data['slug']);
        }
        if (isset($data['active'])) {
            $module->setActive($data['active']);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeModule($module));
    }

    /**
     * Get articles for a module with pagination
     */
    #[Route('/{id}/articles', name: 'api_modules_articles', methods: ['GET'])]
    public function articles(int $id, Request $request): JsonResponse
    {
        $module = $this->moduleRepository->find($id);

        if (!$module) {
            return $this->json([
                'error' => 'not_found',
                'details' => ['message' => 'Module not found']
            ], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $result = $this->articleRepository->findByModulePaginated($id, $page, $limit);

        return $this->json([
            'items' => array_map(fn($a) => [
                'id' => $a->getId(),
                'source_url' => $a->getSourceUrl(),
                'original_title' => $a->getOriginalTitle(),
                'suggested_title' => $a->getSuggestedTitle(),
                'status' => $a->getStatus(),
                'score' => $a->getScore(),
                'created_at' => $a->getCreatedAt()?->format('c'),
            ], $result['items']),
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
        ]);
    }

    /**
     * Serialize module to array
     */
    private function serializeModule(Module $module): array
    {
        return [
            'id' => $module->getId(),
            'name' => $module->getName(),
            'slug' => $module->getSlug(),
            'active' => $module->isActive(),
            'created_at' => $module->getCreatedAt()?->format('c'),
        ];
    }
}
