---
name: api-test
description: Run API tests or write new tests for the Workoflow platform. Use when the user says "run tests", "test the API", "write a test", "api test", "phpunit", "test this endpoint", or wants to verify API behavior. Also triggers when the user mentions test users, X-Test-Auth-Email, or puppeteer tests.
---

# API Testing

## Running tests

```bash
# All tests
docker-compose exec frankenphp ./vendor/bin/phpunit

# Specific suite
docker-compose exec frankenphp ./vendor/bin/phpunit --testsuite=Unit
docker-compose exec frankenphp ./vendor/bin/phpunit --testsuite=Integration
docker-compose exec frankenphp ./vendor/bin/phpunit --testsuite=Smoke

# Specific test file
docker-compose exec frankenphp ./vendor/bin/phpunit tests/Integration/Api/McpApiControllerTest.php

# Specific test method
docker-compose exec frankenphp ./vendor/bin/phpunit --filter testGetToolsWithValidToken
```

## Code quality (run after every code change)

```bash
docker-compose exec frankenphp composer code-check   # PHPStan + PHPCS
docker-compose exec frankenphp composer phpstan       # Static analysis (level 6)
docker-compose exec frankenphp composer phpcs         # Coding standards (PSR-12)
docker-compose exec frankenphp composer phpcbf        # Auto-fix coding standards
```

## Test environment

**Config:** `phpunit.dist.xml`
**Env file:** `.env.test`

Key test env vars:
```
APP_ENV=test
ENCRYPTION_KEY=12345678901234567890123456789012
API_AUTH_USER=test-api-user
API_AUTH_PASSWORD=test-api-password
```

Optional `.env.test.local` for real API testing (not committed):
```
TEST_JIRA_URL=https://your-company.atlassian.net
TEST_JIRA_EMAIL=your-email@company.com
TEST_JIRA_TOKEN=your-actual-jira-api-token
```

## Authentication methods in tests

### 1. X-Test-Auth-Email (browser/UI testing)

Works in dev/test environments only. Auto-creates user if not found.

```
http://localhost:3979/?X-Test-Auth-Email=puppeteer.test1@example.com
```

**Preconfigured test users:**
- `puppeteer.test1@example.com` — Admin
- `puppeteer.test2@example.com` — Member

### 2. Basic Auth (Integration API)

For `/api/integrations/*` and `/api/skills/*` endpoints:

```php
$basicAuthHeader = 'Basic ' . base64_encode('test-api-user:test-api-password');
$this->client->request('GET', '/api/integrations/' . $orgUuid, [], [], [
    'HTTP_AUTHORIZATION' => $basicAuthHeader,
]);
```

### 3. X-Prompt-Token (MCP API)

For `/api/mcp/*` endpoints:

```php
$this->client->request('GET', '/api/mcp/tools', [], [], [
    'HTTP_X_PROMPT_TOKEN' => $validToken,
]);
```

Token is the `personalAccessToken` from `UserOrganisation` entity.

## Writing new tests

### Base class

Extend `AbstractIntegrationTestCase` for integration tests:

```php
namespace App\Tests\Integration\Api;

use App\Tests\Integration\AbstractIntegrationTestCase;

class MyNewApiControllerTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser('admin@test.example.com');
    }

    public function testMyEndpoint(): void
    {
        $this->client->request('GET', '/api/my-endpoint', [], [], [
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('test-api-user:test-api-password'),
        ]);
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('results', $data);
    }
}
```

**Available helpers from AbstractIntegrationTestCase:**
- `loginUser(email, ?orgId)` — login and set current org
- `setCurrentOrganisation(id)` — switch org context
- `createTestIntegrationConfig()` — create test integration config
- `$this->client` — KernelBrowser
- `$this->entityManager` — Doctrine EntityManager
- `$this->currentUser` — logged-in User entity
- `$this->currentOrganisation` — active Organisation entity

### Mock external APIs

Use `TestHttpClientFactory` to mock HTTP responses:

```php
// tests/Mock/TestHttpClientFactory.php
// Prevents real API calls to Jira, Confluence, etc. during tests
```

## Test file structure

```
tests/
├── bootstrap.php
├── Integration/
│   ├── AbstractIntegrationTestCase.php    # Base class with helpers
│   ├── Api/
│   │   ├── McpApiControllerTest.php       # /api/mcp/* tests
│   │   ├── SkillsApiControllerTest.php    # /api/skills/* tests
│   │   ├── PromptApiControllerTest.php    # /api/prompts/* tests
│   │   └── IntegrationApiControllerTest.php
│   └── Controller/
│       └── McpIntegrationTest.php         # Remote MCP tests
├── Unit/
│   └── Service/Integration/               # Service unit tests
└── Mock/
    └── TestHttpClientFactory.php          # HTTP mock for external APIs
```

## Quick smoke test with curl

```bash
# MCP tools list
curl -s -H "X-Prompt-Token: <token>" http://localhost:3979/api/mcp/tools | jq .

# Integration tools list (Basic Auth)
curl -s -u workoflow:workoflow "http://localhost:3979/api/integrations/<org-uuid>?workflow_user_id=<id>&tool_type=personal" | jq .

# Execute a tool
curl -s -X POST -H "X-Prompt-Token: <token>" -H "Content-Type: application/json" \
  -d '{"tool_name":"web_search","parameters":{"query":"test"}}' \
  http://localhost:3979/api/mcp/execute | jq .
```
