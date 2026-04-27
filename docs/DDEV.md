# DDEV Local Development Setup

DDEV is the recommended way to run Workoflow locally. It provides FrankenPHP, MariaDB, Redis, MinIO, and Mailpit out of the box — no manual Docker configuration needed.

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) v1.24+
- [Docker](https://docs.docker.com/get-docker/) (Docker Desktop, OrbStack, or Colima)

## First-Time Setup

```bash
git clone <repository-url>
cd workoflow-integration-platform

# Copy environment file and set your secrets
cp .env.dist .env
# → Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, ENCRYPTION_KEY, JWT_PASSPHRASE, …

# Generate JWT keys
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -aes256 -pass pass:$(grep JWT_PASSPHRASE .env | cut -d= -f2)
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:$(grep JWT_PASSPHRASE .env | cut -d= -f2)

# Start everything (pulls images, builds web container, runs setup hook)
ddev start
```

`ddev start` automatically runs `.ddev/01_setup.sh` via a post-start hook, which:
1. Installs Composer dependencies
2. Syncs the database schema (`doctrine:schema:update --force`)
3. Clears the Symfony cache

## Services & URLs

| Service | URL | Notes |
|---------|-----|-------|
| Application | https://workoflow.ddev.site | Automatic HTTPS via mkcert |
| Mailpit (email catcher) | https://workoflow.ddev.site:8026 | Captures all outgoing emails |
| MinIO Console | http://localhost:9003 | `workoflow` / `workoflow123` |
| MariaDB | `localhost` (dynamic port) | See `ddev describe` for port |
| Redis | Internal only | `redis:6379` inside containers |

Run `ddev describe` at any time to see all URLs and ports.

## Daily Usage

```bash
ddev start          # Start all containers and run setup hook
ddev stop           # Stop containers (data is preserved)
ddev restart        # Restart containers
ddev ssh            # Open shell inside the web container
ddev logs           # Tail web container logs
ddev logs -s db     # Tail database logs
ddev describe       # Show all service URLs and status
```

## Running Commands

All Symfony and tooling commands run inside the web container via `ddev exec`:

```bash
# Symfony console
ddev exec php bin/console <command>

# Composer
ddev composer <command>

# npm
ddev npm <command>
```

### Common Commands

```bash
# Code quality (PHPStan + PHP CodeSniffer)
ddev composer code-check

# Auto-fix coding standard violations
ddev composer phpcbf

# Build frontend assets
ddev npm run build

# Watch assets during development
ddev npm run dev

# Clear cache
ddev exec php bin/console cache:clear

# Update database schema
ddev exec php bin/console doctrine:schema:update --force

# Dump pending schema SQL without applying
ddev exec php bin/console doctrine:schema:update --dump-sql
```

## Background Workers

Two workers start automatically with `ddev start` and are managed by the container's process supervisor:

| Worker | Command |
|--------|---------|
| `scheduled-worker` | `php bin/console app:scheduled-task:worker` |
| `messenger-worker` | `php bin/console messenger:consume async` |

Check worker status:

```bash
ddev exec supervisorctl status
```

Restart a worker after a code change:

```bash
ddev exec supervisorctl restart webextradaemons:messenger-worker
ddev exec supervisorctl restart webextradaemons:scheduled-worker
```

## Email

In DDEV, all outgoing emails are captured by **Mailpit** instead of being delivered. Open the inbox at:

```
https://workoflow.ddev.site:8026
```

No `RESEND_API_KEY` is required for local development. The DDEV environment automatically sets:

```
MAILER_DSN=smtp://localhost:1025
MAIL_FROM_EMAIL=Workoflow <workoflow@ddev.local>
```

To test with a real Resend account locally, add `RESEND_API_KEY` to your `.env` file.

## Database

DDEV creates a MariaDB instance with the following credentials:

| Setting | Value |
|---------|-------|
| Host (inside containers) | `db` |
| Host (from host machine) | `127.0.0.1` + dynamic port (see `ddev describe`) |
| Database | `db` |
| User | `db` |
| Password | `db` |

```bash
# Open a MySQL shell
ddev mysql

# Run a SQL query
ddev exec php bin/console dbal:run-sql "SELECT * FROM user LIMIT 10"

# Create a database snapshot before risky changes
ddev snapshot

# Restore the latest snapshot
ddev snapshot restore
```

## Creating Users

Use the built-in console command for interactive user creation:

```bash
ddev exec php bin/console app:user:create
```

This will prompt for email, name, admin role, and optional organisation assignment.

For quick one-shot logins without creating a user first, use the test authenticator (dev only):

```
https://workoflow.ddev.site/?X-Test-Auth-Email=you@example.com
```

## Environment Variable Overrides

DDEV injects the following overrides at startup, taking precedence over `.env`:

| Variable | DDEV Value | Purpose |
|----------|------------|---------|
| `DATABASE_URL` | `mysql://db:db@db:3306/db` | Points to DDEV's MariaDB |
| `REDIS_URL` | `redis://redis:6379` | Points to DDEV's Redis |
| `MINIO_ENDPOINT` | `http://minio:9000` | Points to DDEV's MinIO |
| `MAILER_DSN` | `smtp://localhost:1025` | Routes email to Mailpit |
| `MAIL_FROM_EMAIL` | `Workoflow <workoflow@ddev.local>` | Sender address |

All other variables (OAuth credentials, encryption keys, etc.) are read from `.env`.

## Troubleshooting

### Container fails to start

```bash
ddev logs          # Check for errors
ddev restart       # Often fixes transient issues
```

### Setup hook fails (DB connection error)

The hook runs immediately after containers are healthy. If it still fails:

```bash
ddev exec bash .ddev/01_setup.sh   # Run manually to see the full error
```

### Port conflicts

```bash
ddev poweroff      # Stop all DDEV projects across the machine
ddev start
```

### Full reset (destroys local data)

```bash
ddev delete --omit-snapshot   # Remove project + volumes
ddev start                     # Fresh start, runs setup hook
```

### Xdebug

```bash
ddev xdebug on    # Enable (significant performance impact)
ddev xdebug off   # Disable
```
