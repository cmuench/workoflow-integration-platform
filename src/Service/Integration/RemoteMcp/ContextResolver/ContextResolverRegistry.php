<?php

namespace App\Service\Integration\RemoteMcp\ContextResolver;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ContextResolverRegistry
{
    /** @var RemoteMcpContextResolverInterface[] */
    private array $resolvers;

    /**
     * @param iterable<RemoteMcpContextResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator('app.remote_mcp_context_resolver')]
        iterable $resolvers,
    ) {
        $this->resolvers = iterator_to_array($resolvers);
    }

    /**
     * Try all registered resolvers and return the first successful context.
     *
     * @param array<string, mixed> $credentials
     */
    public function resolve(string $serverUrl, array $credentials): ?string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($serverUrl)) {
                $context = $resolver->resolveContext($credentials);
                if ($context !== null) {
                    return $context;
                }
            }
        }

        return null;
    }
}
