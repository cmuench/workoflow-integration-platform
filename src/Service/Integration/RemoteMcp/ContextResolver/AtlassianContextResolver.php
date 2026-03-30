<?php

namespace App\Service\Integration\RemoteMcp\ContextResolver;

use App\Service\Integration\RemoteMcpService;
use Psr\Log\LoggerInterface;

class AtlassianContextResolver implements RemoteMcpContextResolverInterface
{
    public function __construct(
        private RemoteMcpService $remoteMcpService,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $serverUrl): bool
    {
        return str_contains($serverUrl, 'atlassian.com');
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function resolveContext(array $credentials): ?string
    {
        try {
            $result = $this->remoteMcpService->executeTool(
                $credentials,
                'getAccessibleAtlassianResources',
                [],
            );

            if (!isset($result['content']) || !is_array($result['content'])) {
                return null;
            }

            $sites = [];
            foreach ($result['content'] as $content) {
                if (($content['type'] ?? '') === 'text' && !empty($content['text'])) {
                    $data = json_decode($content['text'], true);
                    if (is_array($data)) {
                        foreach ($data as $resource) {
                            if (isset($resource['name'])) {
                                $sites[] = $resource['name'];
                            } elseif (isset($resource['url'])) {
                                $sites[] = parse_url($resource['url'], PHP_URL_HOST) ?? $resource['url'];
                            }
                        }
                    }
                }
            }

            if (empty($sites)) {
                return null;
            }

            return implode(', ', $sites);
        } catch (\Throwable $e) {
            $this->logger->warning('Atlassian context resolution failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
