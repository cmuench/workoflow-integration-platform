---
name: sentry
description: Investigate, triage, and resolve Sentry errors for the Workoflow platform. Use when the user mentions Sentry issues, error tracking, production errors, crash reports, unresolved exceptions, error spikes, or references a Sentry URL/issue ID. Triggers on keywords like "sentry", "error", "exception", "crash", "unresolved issue", "WORKOFLOW-", "WORKOFLOW-ORCHESTRATOR-", or any Sentry issue URL.
---

# Sentry Error Investigation

You MUST use the Sentry MCP tools (`mcp__sentry__*`) to gather error details. Never guess or assume error details — always fetch them from Sentry first.

## Environment

Read these values from the project `.env` file before making any Sentry API calls:

| Env Var | Purpose |
|---|---|
| `SENTRY_ORG_SLUG` | Organization slug for all Sentry API calls |
| `SENTRY_REGION_URL` | Self-hosted Sentry instance URL |
| `SENTRY_PROJECT_PLATFORM` | Project slug for the Symfony integration platform |
| `SENTRY_PROJECT_ORCHESTRATOR` | Project slug for the AI orchestrator |

**Self-hosted instance** — do NOT pass `regionUrl` to MCP tools, the server handles routing automatically.

## Projects

| Env Var | Description | Repo |
|---|---|---|
| `SENTRY_PROJECT_PLATFORM` | Symfony integration platform (PHP 8.5, FrankenPHP) | [workoflow-integration-platform](https://github.com/valantic-CEC-Deutschland-GmbH/workoflow-integration-platform/) |
| `SENTRY_PROJECT_ORCHESTRATOR` | AI agent orchestrator (Python ADK) | [workoflow-orchestrator](https://github.com/valantic-CEC-Deutschland-GmbH/workoflow-orchestrator) |

## Investigation Workflow

When the user asks about Sentry issues, follow this sequence:

### 1. Read config
Read `.env` to get `SENTRY_ORG_SLUG`, `SENTRY_PROJECT_PLATFORM`, and `SENTRY_PROJECT_ORCHESTRATOR`.

### 2. Identify Scope
- If the user provides a Sentry URL or issue ID → use `mcp__sentry__get_sentry_resource` directly
- If the user describes a vague error → use `mcp__sentry__search_issues` on BOTH projects to find matching issues
- If the user asks about error counts/statistics → use `mcp__sentry__search_events`
- If the user asks "what's broken" or "any new errors" → search unresolved issues on both projects

### 3. Gather Details
For each relevant issue:
1. **Get issue details**: `mcp__sentry__get_sentry_resource` — read the stacktrace, tags, and metadata
2. **Check tag distribution**: `mcp__sentry__get_issue_tag_values` — check `environment`, `release`, `browser`, `url` tags to understand scope
3. **Search related events**: `mcp__sentry__search_issue_events` — find recent occurrences, filter by time/environment
4. **AI root cause analysis**: `mcp__sentry__analyze_issue_with_seer` — only when the user explicitly asks for a fix or root cause, or when the stacktrace alone is insufficient

### 4. Cross-Reference with Code
After gathering Sentry data:
- For platform issues: the code is in the current working directory — read the relevant source files to understand context
- For orchestrator issues: mention the repo and file paths from the stacktrace so the user can investigate there

### 5. Report Findings
Present a structured summary:
- **Issue**: title and ID with link
- **Project**: which project is affected
- **Status**: resolved/unresolved/ignored
- **First/Last seen**: when it started and last occurrence
- **Frequency**: event count and affected users
- **Stacktrace**: key frames summarized
- **Root cause**: your analysis based on the code
- **Suggested fix**: concrete code changes if applicable

## Tool Reference

All Sentry tool calls MUST include `organizationSlug` from `SENTRY_ORG_SLUG` env var.

Do NOT pass `regionUrl` — this is a self-hosted instance.

### Common Queries

```
# List unresolved issues in the platform
mcp__sentry__search_issues(organizationSlug=$SENTRY_ORG_SLUG, projectSlugOrId=$SENTRY_PROJECT_PLATFORM, naturalLanguageQuery="unresolved issues")

# List unresolved issues in the orchestrator
mcp__sentry__search_issues(organizationSlug=$SENTRY_ORG_SLUG, projectSlugOrId=$SENTRY_PROJECT_ORCHESTRATOR, naturalLanguageQuery="unresolved issues")

# Get a specific issue by ID
mcp__sentry__get_sentry_resource(organizationSlug=$SENTRY_ORG_SLUG, resourceType="issue", resourceId="WORKOFLOW-123")

# Search events for error counts
mcp__sentry__search_events(organizationSlug=$SENTRY_ORG_SLUG, projectSlug=$SENTRY_PROJECT_PLATFORM, naturalLanguageQuery="how many errors today")

# Check recent releases
mcp__sentry__find_releases(organizationSlug=$SENTRY_ORG_SLUG, projectSlug=$SENTRY_PROJECT_PLATFORM)
```

## Important Notes

- Always search BOTH projects when the user doesn't specify which one — errors can surface in either
- When reporting issues, include the Sentry web URL so the user can click through
- If an issue is in the platform project (current repo), offer to read the relevant source files and suggest a fix
- If an issue is in the orchestrator project, provide the file paths from the stacktrace for the user to investigate in that repo
- Use `mcp__sentry__update_issue` to resolve/assign issues only when the user explicitly asks
