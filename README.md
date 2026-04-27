<p align="center">
  <img src="assets/logo_orig_large.png" alt="Workoflow Logo" width="360px">
</p>

<p align="center">
  <a href="https://github.com/valantic-CEC-Deutschland-GmbH/workoflow-integration-platform/actions/workflows/tests.yml"><img src="https://github.com/valantic-CEC-Deutschland-GmbH/workoflow-integration-platform/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://codecov.io/gh/valantic-CEC-Deutschland-GmbH/workoflow-integration-platform"><img src="https://codecov.io/gh/valantic-CEC-Deutschland-GmbH/workoflow-integration-platform/branch/main/graph/badge.svg" alt="codecov"></a>
</p>

# Workoflow Integration Platform

> AI-first enterprise integration hub for orchestrating tools and data across multiple channels

## Overview

Workoflow is a production-ready integration platform designed to serve as a unified hub for managing and orchestrating AI agents across multiple channels and enterprise systems. It provides a central point of control for deploying tailored AI agents with granular permission management and data sovereignty.

The platform solves a critical challenge in modern enterprises: connecting AI agents to business tools (Jira, Confluence, SharePoint, etc.) while maintaining security, audit trails, and multi-tenant isolation. Unlike traditional platforms, Workoflow is built with AI agents as first-class citizens, providing dynamic tool discovery and execution via REST API.

Key differentiators:
- **Data Sovereignty** - Self-hosted, on-premise deployment keeps all data under your control
- **Channel-Specific Intelligence** - Deploy different AI agents for different business functions
- **Granular Permissions** - Define exactly which tools each channel/agent can access
- **Enterprise-Grade Security** - Encrypted credentials, JWT authentication, comprehensive audit logging

## Key Features

- **Multi-Tenant Organization Management** - Workspace isolation with UUID-based organization identities and role-based access control
- **Plugin-Based Integration Architecture** - Extensible system with 7+ user integrations and 13+ system tools
- **REST API with Dynamic Tool Discovery** - AI agents can discover and execute tools at runtime
- **OAuth2 Authentication** - Google and Azure AD authentication support
- **Encrypted Credential Storage** - Sodium (libsodium) encryption for all user credentials
- **Comprehensive Audit Logging** - All critical actions logged with IP, user agent, and sanitized data
- **Multi-Language Support** - German (DE) and English (EN) translations
- **Docker-Based Deployment** - FrankenPHP runtime with Caddy server

## Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.5, Symfony 8.0 |
| Runtime | FrankenPHP (Caddy) |
| Database | MariaDB 11.8 |
| Cache | Redis 8 |
| Storage | MinIO (S3-compatible) |
| Local Dev | DDEV |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        AI Agents / Bots                         │
│                  (MS Teams, WhatsApp, Slack, etc.)              │
└─────────────────────────────┬───────────────────────────────────┘
                              │ REST API (Basic Auth / JWT)
┌─────────────────────────────▼───────────────────────────────────┐
│                   Workoflow Integration Platform                │
├─────────────────────────────────────────────────────────────────┤
│  IntegrationRegistry ──► Tool Discovery & Execution             │
│  EncryptionService   ──► Credential Management (Sodium)         │
│  AuditLogService     ──► Activity Logging                       │
├─────────────────────────────────────────────────────────────────┤
│  User Integrations          │  System Tools                     │
│  ├── Jira                   │  ├── File Sharing                 │
│  ├── Confluence             │  ├── PDF Generator                │
│  ├── SharePoint             │  ├── Web Search                   │
│  ├── GitLab                 │  ├── Knowledge Query              │
│  ├── Trello                 │  └── ...                          │
│  └── ...                    │                                   │
└─────────────────────────────────────────────────────────────────┘
```

## Quick Start

### Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) v1.24+
- Docker (Docker Desktop, OrbStack, or Colima)
- Google OAuth2 credentials (for user authentication)

### Development Setup

```bash
# 1. Clone and enter repository
git clone <repository-url>
cd workoflow-integration-platform

# 2. Copy environment file and fill in secrets
cp .env.dist .env
# → Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, ENCRYPTION_KEY, JWT_PASSPHRASE

# 3. Generate JWT keys
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -algorithm RSA -pkeyopt rsa_keygen_bits:4096 \
  -aes256 -pass pass:$(grep JWT_PASSPHRASE .env | cut -d= -f2)
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout \
  -passin pass:$(grep JWT_PASSPHRASE .env | cut -d= -f2)

# 4. Start DDEV (pulls images, builds, runs setup automatically)
ddev start
```

- **Application**: https://workoflow.ddev.site
- **Mailpit** (email catcher): https://workoflow.ddev.site:8026
- **MinIO Console**: http://localhost:9003 (workoflow / workoflow123)

For the full local development guide see [docs/DDEV.md](docs/DDEV.md).

### Production Deployment

```bash
# 1. As non-root user with Docker access
./setup.sh prod

# 2. Configure SSL certificates
# 3. Update domain in .env
# 4. Configure Google OAuth redirect URIs

# Deploy updates
git pull
./setup.sh prod
```

## Integration System

Workoflow uses a plugin-based architecture where all integrations implement a common `IntegrationInterface`. Integrations are auto-discovered and registered via Symfony's dependency injection.

### User Integrations

External service integrations that require user-specific API credentials:

- **Jira** - Issue tracking, sprint management, workflow transitions
- **Confluence** - Wiki pages, content management, CQL search
- **SharePoint** - Enterprise document management, KQL search
- **GitLab** - Repository management, merge requests, pipelines
- **Trello** - Board and card management
- **SAP C4C** - Customer relationship management
- **Projektron** - Project and task management

### System Tools

Platform-internal tools that don't require external credentials:

- File sharing and management
- PDF and PowerPoint document generation
- Web search and page reading
- Knowledge base management
- Employee directory queries
- Memory management for AI agents

## API Overview

The REST API enables AI agents to discover and execute integration tools dynamically.

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/integrations/{org-uuid}` | List available tools for organization |
| POST | `/api/integrations/{org-uuid}/execute` | Execute a tool |
| GET | `/api/skills` | List all available skills |
| POST | `/api/register` | Register new user/organization |

### Authentication

```bash
# Basic Auth header required
Authorization: Basic base64(username:password)
```

### Example: List Tools

```bash
curl -X GET "http://localhost:3979/api/integrations/{org-uuid}?workflow_user_id=user-123" \
  -H "Authorization: Basic $(echo -n 'workoflow:workoflow' | base64)"
```

### Example: Execute Tool

```bash
curl -X POST "http://localhost:3979/api/integrations/{org-uuid}/execute" \
  -H "Authorization: Basic $(echo -n 'workoflow:workoflow' | base64)" \
  -H "Content-Type: application/json" \
  -d '{
    "tool_id": "jira_search_42",
    "workflow_user_id": "user-123",
    "parameters": {
      "jql": "project = PROJ AND status = Open"
    }
  }'
```

For detailed API documentation, see [CLAUDE.md](CLAUDE.md).

## Security

### Authentication Layers

| Layer | Mechanism | Use Case |
|-------|-----------|----------|
| Web UI | Google OAuth2 / Azure AD | User login |
| REST API | Basic Auth | Machine-to-machine |
| API Tokens | JWT (RSA 4096-bit) | Token-based auth |

### Credential Encryption

User integration credentials are encrypted using Sodium (libsodium) with a 256-bit key. Credentials are:
- Encrypted at rest in the database
- Decrypted only at execution time
- Never exposed in API responses or logs

### Audit Logging

All critical actions are logged with:
- User and organization context
- IP address and user agent
- Sanitized request/response data
- Stored in `/var/log/audit.log`

## Development

### Code Quality

```bash
# Run all checks (PHPStan + PHP CodeSniffer)
ddev composer code-check

# Static analysis (level 6)
ddev composer phpstan

# Check coding standards (PSR-12)
ddev composer phpcs

# Auto-fix coding standard violations
ddev composer phpcbf
```

### Database Schema

```bash
# View pending schema changes
ddev exec php bin/console doctrine:schema:update --dump-sql

# Apply schema updates
ddev exec php bin/console doctrine:schema:update --force

# Clear cache
ddev exec php bin/console cache:clear
```

### Adding New Integrations

1. Create a class implementing `IntegrationInterface` in `src/Integration/`
2. Place in `SystemTools/` (platform-internal) or `UserIntegrations/` (external services)
3. Define tools via `ToolDefinition` objects
4. The integration is auto-registered via `config/services/integrations.yaml`

```php
class MyIntegration implements IntegrationInterface
{
    public function getType(): string { return 'mytype'; }
    public function getName(): string { return 'My Integration'; }
    public function getTools(): array { /* return ToolDefinition[] */ }
    public function executeTool($name, $params, $creds): array { /* logic */ }
    public function requiresCredentials(): bool { return false; }
}
```

## Directory Structure

```
src/
├── Command/              # CLI Commands
├── Controller/           # HTTP Controllers
│   └── Api/              # REST API endpoints
├── Entity/               # Doctrine ORM Entities
├── Integration/          # Plugin-based Integration System
│   ├── IntegrationInterface.php
│   ├── IntegrationRegistry.php
│   ├── SystemTools/      # Platform-internal tools
│   └── UserIntegrations/ # External service integrations
├── Repository/           # Data Access Layer
├── Security/             # Authentication & Authorization
└── Service/              # Business Logic
```

## Environment Variables

Key configuration in `.env`:

```bash
# Application
APP_ENV=dev|prod
APP_SECRET=<random>
APP_URL=http://localhost:3979

# Database
DATABASE_URL=mysql://user:pass@mariadb:3306/workoflow_db

# OAuth
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...

# MinIO S3
MINIO_ENDPOINT=http://minio:9000
MINIO_BUCKET=workoflow-files

# Security
ENCRYPTION_KEY=<32-character-key>
JWT_PASSPHRASE=<passphrase>
API_AUTH_USER=workoflow
API_AUTH_PASSWORD=workoflow
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Ensure code passes quality checks (`composer code-check`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

Proprietary - All rights reserved.

---

<p align="center">
  Built with Symfony 8 and FrankenPHP
</p>
