---
name: add-integration
description: Add a new skill (integration) to the Workoflow platform. Use when the user asks to "add a skill", "add an integration", "create a new skill", "new integration for X", "connect X service", or wants to extend the platform with a new external service or system tool.
---

# Add New Skill (Integration)

The platform calls integrations "skills" in the UI. There are two types:

| Type | Interface | Directory | Credentials | System Prompt |
|------|-----------|-----------|-------------|---------------|
| **Personalized Skill** | `PersonalizedSkillInterface` | `src/Integration/UserIntegrations/` | Yes (OAuth/API keys) | Yes (Twig XML) |
| **Platform Skill** | `PlatformSkillInterface` | `src/Integration/SystemTools/` | No | No |

## Step-by-Step

### 1. Decide the type

- Connects to an **external service** (Jira, Slack, etc.)? → `PersonalizedSkillInterface` in `UserIntegrations/`
- **Platform-internal** functionality (file ops, search, etc.)? → `PlatformSkillInterface` in `SystemTools/`

### 2. Create the integration class

**Location:** `src/Integration/UserIntegrations/MyNewIntegration.php` or `src/Integration/SystemTools/MyNewIntegration.php`

**IntegrationInterface methods** (all integrations must implement):

```php
public function getType(): string;                    // Unique ID: 'myservice' or 'system.myservice'
public function getName(): string;                    // Display name: 'My Service'
public function getTools(): array;                    // Returns ToolDefinition[]
public function executeTool(string $toolName, array $parameters, ?array $credentials = null): array;
public function requiresCredentials(): bool;          // true for Personalized, false for Platform
public function validateCredentials(array $credentials): bool;
public function getCredentialFields(): array;         // Returns CredentialField[]
public function isExperimental(): bool;               // Show beta badge
public function getSetupInstructions(): ?string;      // Optional HTML help text
public function getLogoPath(): string;                // '/images/logos/myservice-icon.svg'
```

**PersonalizedSkillInterface** adds:
```php
public function getSystemPrompt(?IntegrationConfig $config = null): string;
```

### 3. Define tools with ToolDefinition

```php
use App\Integration\ToolDefinition;
use App\Integration\ToolCategory;

new ToolDefinition(
    'myservice_search',                              // Tool name (snake_case, prefixed)
    'Search for items in My Service...',             // Description (AI agents read this)
    [
        ['name' => 'query', 'type' => 'string', 'required' => true, 'description' => 'Search query'],
        ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max results'],
    ],
    ToolCategory::READ                               // READ, WRITE, or DELETE
);
```

**ToolCategory** determines access control:
- `READ` — allowed in Read Only mode
- `WRITE` — requires Standard or Full mode
- `DELETE` — requires Full mode

### 4. Define credential fields (Personalized only)

```php
use App\Integration\CredentialField;

public function getCredentialFields(): array {
    return [
        // API key auth:
        new CredentialField('url', 'url', 'Instance URL', 'https://myservice.example.com', true, 'Your My Service instance URL'),
        new CredentialField('api_token', 'password', 'API Token', null, true, 'Generate at Settings > API'),

        // OAuth auth:
        new CredentialField('oauth', 'oauth', 'Connect with My Service', null, true, 'Authenticate with your account'),

        // Select field:
        new CredentialField('region', 'select', 'Region', null, true, null, ['eu' => 'Europe', 'us' => 'United States']),

        // Conditional field (shown only when another field has a specific value):
        new CredentialField('custom_url', 'url', 'Custom URL', null, true, null, null, 'region', 'custom'),
    ];
}
```

**CredentialField constructor:**
```php
new CredentialField(
    name: string,
    type: string,            // text, url, email, password, oauth, select
    label: string,
    placeholder: ?string,
    required: bool,
    description: ?string,
    options: ?array,         // For select: ['value' => 'Label']
    conditionalOn: ?string,  // Field name
    conditionalValue: ?string
)
```

### 5. Create the service class (recommended for Personalized)

**Location:** `src/Service/Integration/MyNewService.php`

```php
class MyNewService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function searchItems(array $credentials, string $query, int $limit = 25): array
    {
        try {
            $response = $this->httpClient->request('GET', $credentials['url'] . '/api/search', [
                'headers' => ['Authorization' => 'Bearer ' . $credentials['api_token']],
                'query' => ['q' => $query, 'limit' => $limit],
            ]);
            return ['results' => $response->toArray(), 'count' => count($response->toArray())];
        } catch (\Exception $e) {
            error_log('MyService search failed: ' . $e->getMessage());
            return ['error' => 'Search failed: ' . $e->getMessage()];
        }
    }
}
```

**Patterns:**
- Inject `HttpClientInterface` via constructor
- Accept `$credentials` array as first param (keys match CredentialField names)
- Return structured arrays: `['results' => ...]` or `['error' => '...']`
- Never throw exceptions for API failures — return error arrays

### 6. Create system prompt (Personalized only)

**Location:** `templates/skills/prompts/myservice_full.xml.twig`

Look at existing prompts for the XML structure. Key variables available:
- `{{ api_base_url }}` — platform API URL
- `{{ tool_count }}` — number of tools
- `{{ integration_id }}` — config instance ID

Render it in the integration class:
```php
public function getSystemPrompt(?IntegrationConfig $config = null): string {
    return $this->twig->render('skills/prompts/myservice_full.xml.twig', [
        'api_base_url' => $_ENV['APP_URL'] ?? '',
        'tool_count' => count($this->getTools()),
        'integration_id' => $config?->getId() ?? 'XXX',
    ]);
}
```

### 7. Register in services config

**File:** `config/services/integrations.yaml`

```yaml
App\Integration\UserIntegrations\MyNewIntegration:
    tags: ['app.integration']
```

The `app.integration` tag auto-registers it with the `IntegrationRegistry`.

### 8. Add the logo

Place SVG/PNG in `public/images/logos/myservice-icon.svg` and reference it:
```php
public function getLogoPath(): string { return '/images/logos/myservice-icon.svg'; }
```

### 9. Add translations

Add to all 4 locale files (`translations/{en,de,lt,ro}/messages.{locale}.yaml`):
```yaml
integration:
  myservice:
    name: My Service
    description: Connect to My Service for...
```

## Post-creation checklist

1. Run `docker-compose exec frankenphp composer code-check` (PHPStan + PHPCS)
2. Update `CHANGELOG.md` with user-facing description
3. Update `public/llms.txt` (new integration type = required update)
4. Update `docs/global-concept.md` if it's a major new integration category
5. Clear cache if testing in prod: `php bin/console cache:pool:clear cache.app`

## Existing integrations for reference

**13 System Tools** in `src/Integration/SystemTools/`:
WebSearch, ShareFile, PdfGenerator, PowerPointGenerator, ContentLearn, ContentQuery, EmployeeQuery, EmployeeProfile, ReadFile, CompanyEvents, MemoryManagement, IssueReporting

**14 User Integrations** in `src/Integration/UserIntegrations/`:
Jira, Confluence, SharePoint, GitLab, Trello, HubSpot, Wrike, OutlookMail, OutlookCalendar, MsTeams, SapC4c, SapSac, Projektron, RemoteMcp
