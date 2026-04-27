# Workoflow Integration Platform

## Overview
The Workoflow Integration Platform is a production-ready Symfony 8.0 application that enables users to manage various integrations and provide them via REST API and MCP for AI agents.
If you have read this file, greet me with "Hey Workoflow Dev"

## Quick Reference

- **Tech**: PHP 8.5, Symfony 8.0, FrankenPHP, Stimulus JS, SCSS
- **App**: https://workoflow.ddev.site
- **MinIO Console**: http://localhost:9003 (workoflow / workoflow123)
- **Code check**: `ddev composer code-check`
- **Build assets**: `ddev npm run build`
- **Clear cache**: `ddev exec php bin/console cache:clear`
- **Schema update**: `ddev exec php bin/console doctrine:schema:update --force`
- **Start**: `ddev start` | **Stop**: `ddev stop` | **SSH**: `ddev ssh`
- **Logs**: `ddev logs` | **Worker status**: `ddev exec supervisorctl status`

## Skills (use these instead of re-reading docs)

| Skill | Trigger |
|-------|---------|
| `/add-integration` | Add a new skill/integration to the platform |
| `/deploy` | Deploy to production |
| `/add-translation` | Add/update i18n keys (DE/EN/RO/LT) |
| `/api-test` | Run tests, write new tests, PHPUnit |
| `/sentry` | Investigate Sentry errors |
| `/check-prod` | SSH into prod, diagnose issues |
| `/debug-stacktrace-prompt` | Fetch Phoenix traces for prompt debugging |

## LSP Setup

**PHP**: `php-lsp` — auto-discovers `composer.json` for class resolution
**HTML/CSS/SCSS**: `vscode-html-language-server` + `vscode-css-language-server`

### Development Rules
1. **CHANGELOG.md Updates**:
    - Update CHANGELOG.md with user-facing changes only (features, fixes, improvements)
    - Write for end-users and basic technical users, NOT developers
    - DO NOT mention: function calls, function names, file paths, code implementation details
    - DO mention: what changed from user perspective, UI improvements, workflow changes, bug fixes
    - Group changes by date, use sections: Added, Changed, Fixed, Removed
    - Write concise bullet points focusing on the "what" and "why", not the "how"
    - Example: "Removed address confirmation step for faster returns"
    - Counter-example: "Modified processReturn function to skip confirmAddress parameter"

2. **Code Quality Verification**:
    - **ALWAYS** run `ddev composer code-check` after code changes
    - This runs both PHPStan (static analysis, level 6) and PHP CodeSniffer (PSR-12)
    - Auto-fix coding standards: `composer phpcbf`

3. **llms.txt Maintenance**:
    - Update `public/llms.txt` when adding new integrations, changing API endpoints, or modifying architecture
    - Keep content concise and AI-friendly

4. **Global Concept Documentation**:
    - Update `docs/global-concept.md` for large infrastructure or feature changes only

5. **Orchestrator Agent Cache**:
    - Clear after deploying new orchestrator agents: `ddev exec php bin/console cache:pool:clear cache.app`

6. **UI/Styling Guidelines**:
    - Reference `CONCEPT/SYMFONY_IMPLEMENTATION_GUIDE.md` for UI tasks
    - Add CSS to `assets/styles/app.scss`, NOT inline
    - Use CSS custom properties (`var(--space-md)`, etc.)
    - Run `ddev npm run build` after SCSS changes

### Main Features
- OAuth2 Login (Google, Azure/Microsoft, HubSpot, Wrike)
- Magic Link passwordless authentication
- Multi-Tenant Organisation Management
- Integration Management (14 user integrations: Jira, Confluence, SharePoint, Trello, GitLab, SAP C4C, Projektron, HubSpot, SAP SAC, Wrike, Outlook Mail, Outlook Calendar, MS Teams, Remote MCP)
- 13 built-in System Tools (WebSearch, PdfGenerator, PowerPointGenerator, FileSharing, Memory, etc.)
- REST API + MCP Server for AI Agent access
- Tool Access Modes (Read Only / Standard / Full) per user
- Scheduled Tasks with webhook-based execution
- Channel System (Slack, MS Teams, WhatsApp)
- Prompt Vault (shared prompt library with upvotes)
- File Management with MinIO S3
- Audit Logging
- Multi-language support (DE/EN/RO/LT)

## Architecture

### Tech Stack
- **Backend**: PHP 8.5, Symfony 8.0, FrankenPHP
- **Frontend**: Stimulus JS, SCSS, Webpack Encore
- **Infrastructure**: DDEV (FrankenPHP, MariaDB, Redis, MinIO) — see `.ddev/` for service config

### Design Principles

#### SOLID
- **Single Responsibility**: Controllers handle HTTP, services handle business logic, entities store data.
- **Open/Closed**: Extend via new classes (new integrations, resolvers), not modifying existing ones.
- **Liskov Substitution**: All interface implementations must be interchangeable.
- **Interface Segregation**: Small, focused interfaces (`PlatformSkillInterface` vs `PersonalizedSkillInterface`).
- **Dependency Inversion**: Depend on abstractions, inject via constructor.

#### Clean Code
- Meaningful names, small functions, no dead code, fail fast, DRY at 3+ occurrences.

#### KISS
- Simplest solution that meets requirements. No speculative abstractions.

### Directory Structure
```
src/
├── Command/          # Console Commands
├── Controller/       # HTTP Controllers
├── Entity/           # Doctrine Entities
├── Integration/      # Plugin-based Integration System
│   ├── SystemTools/  # 13 platform-internal tools
│   └── UserIntegrations/ # 14 external service integrations
├── OAuth2/           # League OAuth2 providers
├── Repository/       # Entity Repositories
├── Security/         # Auth & Security
└── Service/          # Business Logic
    ├── Integration/  # Per-integration HTTP clients
    └── ScheduledTask/
```

## API Reference

```
# Integration Tools API (Basic Auth)
GET  /api/integrations/{org-uuid}?workflow_user_id={id}&tool_type={type}
POST /api/integrations/{org-uuid}/execute?workflow_user_id={id}

# MCP Server API (X-Prompt-Token header)
GET  /api/mcp/tools
POST /api/mcp/execute

# Other APIs (JWT or X-Prompt-Token)
GET  /api/skills
GET  /api/prompts
GET  /api/tenant/{org-uuid}/settings
POST /api/register
```

## Entities & Data Model

### Core Entities
- **User**: Auth via Google OAuth2 / Magic Link. Roles: ROLE_ADMIN, ROLE_MEMBER
- **Organisation**: UUID for API URLs. N:N with Users via `UserOrganisation`
- **UserOrganisation**: Links User<>Organisation with role, workflowUserId, personalAccessToken, systemPrompt
- **IntegrationConfig**: Per-user integration settings. Encrypted credentials (Sodium). `disabledTools` JSON array

### Additional Entities
- **AuditLog**, **Channel / UserChannel**, **Prompt / PromptComment / PromptUpvote**, **ScheduledTask / ScheduledTaskExecution**, **SkillRequest**, **WaitlistEntry**

## Security

### Authentication
- Google OAuth2 (primary web login)
- Magic Link (passwordless alternative)
- Basic Auth (Integration API — validated in controller, not firewall)
- JWT Tokens (API access)
- X-Prompt-Token header (MCP + Prompt API)
- X-Test-Auth-Email GET parameter (test environments only, auto-creates users)

### Encryption
- **JWT**: RSA 4096-bit keys in `config/jwt/`, Lexik JWT Authentication Bundle, 1h lifetime
- **Integration Credentials**: Sodium encryption via `App\Service\EncryptionService`, 32-char ENCRYPTION_KEY from .env

## Setup

### Development
```bash
git clone <repository-url> && cd workoflow-integration-platform
./setup.sh dev
# Configure GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env
```

### Access
- **Application**: http://localhost:3979
- **MinIO Console**: http://localhost:9001 (admin/workoflow123)

### Database Schema Management
- Entities are the single source of truth — no migration files
- `doctrine:schema:update --force` to apply changes

## Debugging Prompt Issues

Use `scripts/get_stacktrace.sh` to fetch conversation traces from the observability backend. See the `debug-stacktrace-prompt` skill for details.
