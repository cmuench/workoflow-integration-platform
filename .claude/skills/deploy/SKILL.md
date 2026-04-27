---
name: deploy
description: Deploy code to the Workoflow production server. Use when the user says "deploy", "push to prod", "ship it", "release", "update production", or wants to deploy changes to the production environment.
---

# Deploy to Production

Read `PROD_SSH_HOST`, `PROD_SSH_USER`, `PROD_DEPLOY_DIR`, and `PROD_ORCHESTRATOR_DIR` from the project `.env` file before running any commands.

## CRITICAL: Always use docker-compose-prod.yml

```bash
# CORRECT — uses external volumes with production data
docker-compose -f docker-compose-prod.yml <command>

# WRONG — creates new prefixed volumes, LOSES production data!
docker-compose <command>
```

The prod compose uses `external: true` volumes (`mariadb_data`, `redis_data`, `caddy_data`, `caddy_config`). The default compose creates new prefixed volumes and disconnects from all production data.

## Connection

```bash
ssh $PROD_SSH_HOST
sudo -iu $PROD_SSH_USER
cd $PROD_DEPLOY_DIR
```

## Deployment Sequence

### 1. Pull latest code
```bash
git pull
```

### 2. Build the container
```bash
docker-compose -f docker-compose-prod.yml build frankenphp
```

The Dockerfile runs `composer install --optimize-autoloader` and `npm ci && npm run build` during the build stage — no need to run these separately.

### 3. Bring up services
```bash
docker-compose -f docker-compose-prod.yml up -d frankenphp messenger-worker scheduled-worker
```

### 4. Update database schema (if entities changed)
```bash
# Preview changes first
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console doctrine:schema:update --dump-sql

# Apply
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console doctrine:schema:update --force
```

### 5. Clear caches
```bash
# Symfony cache
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:clear

# Orchestrator agent cache (if new agents were added)
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:pool:clear cache.app
```

### 6. Verify
```bash
# Check containers are healthy
docker-compose -f docker-compose-prod.yml ps

# Check logs for errors
docker-compose -f docker-compose-prod.yml logs -f --tail=50 frankenphp

# Resource check
docker stats --no-stream
```

## Quick deploy (full sequence)

```bash
ssh $PROD_SSH_HOST
sudo -iu $PROD_SSH_USER
cd $PROD_DEPLOY_DIR
git pull
docker-compose -f docker-compose-prod.yml build frankenphp
docker-compose -f docker-compose-prod.yml up -d frankenphp messenger-worker scheduled-worker
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console doctrine:schema:update --dump-sql
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:clear
docker-compose -f docker-compose-prod.yml logs -f --tail=20 frankenphp
```

## Deploying orchestrator changes

The orchestrator lives in a separate directory:

```bash
cd $PROD_ORCHESTRATOR_DIR
docker-compose restart adk-orchestrator
docker-compose logs -f adk-orchestrator
```

After deploying new orchestrator agents, clear the platform cache (5-min TTL):
```bash
cd $PROD_DEPLOY_DIR
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:pool:clear cache.app
```

## Rollback

If something goes wrong:
```bash
# Check recent commits
git log --oneline -5

# Revert to previous commit
git checkout <commit-hash>
docker-compose -f docker-compose-prod.yml build frankenphp
docker-compose -f docker-compose-prod.yml up -d frankenphp messenger-worker scheduled-worker
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:clear
```

## Pre-deploy checklist

Before deploying, ensure:
1. `docker-compose exec frankenphp composer code-check` passes (PHPStan + PHPCS)
2. Tests pass: `docker-compose exec frankenphp ./vendor/bin/phpunit`
3. CHANGELOG.md is updated with user-facing changes
4. Assets build without errors: `docker-compose exec frankenphp npm run build`

## Production services

| Container | Port | Purpose |
|---|---|---|
| frankenphp | 3979->80, 443 | Symfony app |
| messenger-worker | — | Async message processing |
| scheduled-worker | — | Scheduled task execution |
| mariadb | 3306 | MariaDB 12 |
| redis | — | Redis 8 |
