<?php

namespace App\Service\Integration\RemoteMcp\ContextResolver;

interface RemoteMcpContextResolverInterface
{
    /**
     * Whether this resolver supports the given MCP server URL.
     */
    public function supports(string $serverUrl): bool;

    /**
     * Attempt to resolve instance context after OAuth authorization.
     * Returns a human-readable context string, or null if resolution failed.
     *
     * @param array<string, mixed> $credentials The decrypted credentials including OAuth tokens
     */
    public function resolveContext(array $credentials): ?string;
}
