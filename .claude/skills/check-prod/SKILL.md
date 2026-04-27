---
name: check-prod
description: Inject production server knowledge and run diagnostics. Use when user says "check prod", "look at prod", "production issue", "you need to check prod", or wants to inspect/debug/restart anything on the production server.
---

You are now operating in **production context** for the Workoflow platform.

Read `PROD_SSH_HOST`, `PROD_SSH_USER`, `PROD_DEPLOY_DIR`, and `PROD_ORCHESTRATOR_DIR` from the project `.env` file before running any commands.

## Connection

```bash
ssh $PROD_SSH_HOST
sudo -iu $PROD_SSH_USER
cd $PROD_DEPLOY_DIR
```

## Docker Setup Directories

All under the parent of `$PROD_DEPLOY_DIR`:

| Directory | Purpose | Compose File |
|---|---|---|
| `workoflow-integration-platform/` | Symfony app (frankenphp, mariadb, redis, workers) | `docker-compose-prod.yml` |
| `n8n/` | AI stack (orchestrator, litellm, docling, qdrant, phoenix, teams-bot, minio, crawl4ai, searxng, rustfs, tika, gotenberg, mcp, atlassian-mcp) | `docker-compose.yaml` |
| `workoflow-metrics/` | Grafana monitoring | default |
| `workoflow-pptx/` | PPTX generator | default |
| `workoflow-rag/` | RAG services | default |
| `n8n-monitoring/` | Elastic metricbeat | default |

## CRITICAL: Compose File Rules

**ALWAYS use `docker-compose-prod.yml`** for the integration platform:

```bash
# CORRECT
docker-compose -f docker-compose-prod.yml up -d
docker-compose -f docker-compose-prod.yml restart frankenphp
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:clear
docker-compose -f docker-compose-prod.yml logs -f frankenphp

# WRONG - creates new prefixed volumes, disconnects from production data!
docker-compose up -d
docker-compose restart frankenphp
```

For the **n8n stack**, use normal `docker-compose` (in `$PROD_ORCHESTRATOR_DIR` with `docker-compose.yaml`).

## All Production Containers

### Integration Platform
| Container | Port | Purpose |
|---|---|---|
| frankenphp | 3979->80, 443->443 | Symfony app (FrankenPHP) |
| messenger-worker | — | Symfony Messenger async worker |
| scheduled-worker | — | Scheduled task worker |
| mariadb | 3306 | MariaDB 12 |
| redis | — | Redis 8 Alpine |

### AI Stack
| Container | Port | Purpose |
|---|---|---|
| adk-orchestrator | 8080 | Workoflow AI orchestrator (ADK) |
| litellm | 4000 | LLM proxy (mem limit: 1G, often near 90%) |
| workoflow-docling | 5001 | Document parsing/ML (mem limit: 2G) |
| qdrant | 6333/6334 | Vector DB (mem limit: 1G) |
| phoenix | 6006 | Trace observability (OpenInference) |
| phoenix-postgres | internal | Phoenix trace DB |
| minio | 9000/9001 | S3 object storage |
| workoflow-rustfs | 9004/9007 | Knowledge base file storage |
| workoflow-crawl4ai | 11235 | Web crawling |
| teams-bot | 3978 | MS Teams bot |
| workoflow-mcp | 9006 | Workoflow MCP server |
| mcp-atlassian | 9005 | Atlassian MCP server |
| searxng | 8090 | Search engine |
| gotenberg | 3002 | PDF/document conversion |
| tika | 9998 | Content extraction |
| postgres | 5432 | Shared PostgreSQL |
| redis (n8n) | 6381 | Shared Redis |

## Common Operations

### Restart the Symfony app
```bash
cd $PROD_DEPLOY_DIR
docker-compose -f docker-compose-prod.yml restart frankenphp
```

### View logs
```bash
# Symfony app
docker-compose -f docker-compose-prod.yml logs -f frankenphp
# Messenger worker
docker-compose -f docker-compose-prod.yml logs -f messenger-worker
# Orchestrator
cd $PROD_ORCHESTRATOR_DIR && docker-compose logs -f adk-orchestrator
```

### Clear Symfony cache
```bash
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:clear
```

### Clear orchestrator agent cache (after deploying new agents)
```bash
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console cache:pool:clear cache.app
```

### Rebuild after code deploy
```bash
cd $PROD_DEPLOY_DIR
git pull
docker-compose -f docker-compose-prod.yml build frankenphp
docker-compose -f docker-compose-prod.yml up -d frankenphp messenger-worker scheduled-worker
```

### Database operations
```bash
# Schema update
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console doctrine:schema:update --dump-sql
docker-compose -f docker-compose-prod.yml exec frankenphp php bin/console doctrine:schema:update --force
```

### Resource check
```bash
docker stats --no-stream
free -h
df -h /
```

### n8n stack operations
```bash
cd $PROD_ORCHESTRATOR_DIR
docker-compose restart adk-orchestrator
docker-compose restart litellm
docker-compose logs -f adk-orchestrator
```

## Known Resource Concerns

- **litellm**: 1G memory limit, typically runs at ~90% — restart if OOM-killed
- **workoflow-docling**: 2G memory limit, CPU-heavy during document parsing
- **qdrant**: 1G memory limit, ~20% typical usage

## When Diagnosing Issues

1. **SSH in** and check `docker ps` for unhealthy/restarting containers
2. Check `docker stats --no-stream` for resource pressure
3. Check logs for the relevant service
4. For prompt/agent issues, use the `debug-stacktrace-prompt` skill to fetch Phoenix traces
5. For Symfony errors, check `docker-compose -f docker-compose-prod.yml exec frankenphp cat var/log/prod.log | tail -100`
