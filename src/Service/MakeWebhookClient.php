<?php

namespace App\Service;

use App\Entity\Article;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to communicate with Make.com (Integromat) webhooks
 */
class MakeWebhookClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $webhookUrl,
    ) {
    }

    /**
     * Send an article to Make.com for writing
     * 
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function sendArticleForWriting(Article $article): void
    {
        $modules = [];
        foreach ($article->getModules() as $module) {
            $modules[] = [
                'id' => $module->getId(),
                'name' => $module->getName(),
                'slug' => $module->getSlug(),
            ];
        }

        $payload = [
            'article_id' => $article->getId(),
            'source_url' => $article->getSourceUrl(),
            'original_title' => $article->getOriginalTitle(),
            'original_description' => $article->getOriginalDescription(),
            'modules' => $modules,
        ];

        $this->logger->info('Sending article to Make.com webhook', [
            'article_id' => $article->getId(),
            'webhook_url' => $this->webhookUrl,
        ]);

        $response = $this->httpClient->request('POST', $this->webhookUrl, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        // Get status code to trigger the request (requests are lazy)
        $statusCode = $response->getStatusCode();

        $this->logger->info('Make.com webhook response', [
            'article_id' => $article->getId(),
            'status_code' => $statusCode,
        ]);
    }
}
