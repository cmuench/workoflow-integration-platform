# Digital Worker (Digital Employee)

## Overview

Digital Workers are user-created, specialized AI agents with custom system prompts and a curated subset of the user's configured skills. Instead of every prompt going through the generic main agent, users can delegate work to purpose-built workers like "Internal News SharePoint Worker" or "Dev Ticket Analyst".

The main agent continues to work as before, but can now also delegate to user-defined workers when explicitly requested. Workers are wrapped as `AgentTool` in the orchestrator, so the main agent can combine a worker's result with other work in the same response.

## User Story

1. User configures personal skills on `/skills/` (SharePoint, Jira, People Finder, etc.)
2. User navigates to `/digital-workers/` and creates a new worker:
   - Name: "Internal News Worker"
   - System Prompt: "You are responsible for reviewing, publishing, and preparing valantic internal news. Create them, then serve them to me. Your tone is friendly."
   - Skills: enables SharePoint, disables `sharepoint_delete_page` tool
3. User chats via Teams: "Delegate to my Internal News Worker: create a news article about Project XYZ"
4. Main agent recognizes the delegation request, routes to the worker AgentTool
5. Worker agent runs with its custom system prompt and only its allowed sub-agents/tools
6. Main agent receives the worker's result and returns it to the user

Mixed requests also work: "Delegate the news to my news worker AND search Jira for PM-4461" — the main agent calls the worker AgentTool + the Jira sub-agent, then combines both results.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       ORCHESTRATOR (Google ADK)                          │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ MAIN AGENT (Router/Coordinator)                                     │  │
│  │                                                                      │  │
│  │ tools (AgentTool):                     sub_agents (transfer):        │  │
│  │   ├── people_finder_agent (native)       ├── jira_agent_42           │  │
│  │   ├── knowledge_base_agent (native)      ├── confluence_agent_55     │  │
│  │   ├── news_worker (Digital Worker) ◄──   ├── sharepoint_agent_60     │  │
│  │   └── ticket_analyst (Digital Worker)    └── ...                     │  │
│  └─────────────┬────────────────────────────────────────────────────┘  │
│                │                                                         │
│                │ AgentTool invocation                                     │
│                ▼                                                         │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ DIGITAL WORKER AGENT (e.g. "news_worker")                           │  │
│  │                                                                      │  │
│  │ LLM: GPT-5.4 (reasoning_effort=medium)                              │  │
│  │ Instruction: user's custom system prompt                             │  │
│  │                                                                      │  │
│  │ sub_agents (filtered — only worker's allowed skills):                │  │
│  │   └── sharepoint_agent_60  (with tool-level filtering via worker_id) │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Key Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Ownership | Per-user (personal) | Workers use the user's own credentials/skills. Not shared across org. |
| Invocation | Explicit natural language | User says "delegate to my news worker". No @mention (not possible in Teams). |
| Agent pattern | `AgentTool` wrapping | Main agent calls worker as a tool, gets result back, can continue its own work. Supports "worker + other tasks" in one request. |
| Tool filtering | Platform-side (`worker_id` param) | Orchestrator passes `worker_id` to discover/execute APIs. Platform applies deny-list. Clean separation of concerns. |
| Skill assignment | Deny-list at tool level | All user skills available by default. User disables specific integrations or tools. Consistent with existing `IntegrationConfig.disabledTools` pattern. |
| Conversation history | Shared with main agent | Workers share the same Redis conversation thread. No separate memory per worker. |
| Description | Auto-generated at save time | LLM summarizes system prompt into 1-2 sentence routing description. Stored in DB. User can edit. |
| MCP exposure | One tool per worker | Each worker appears as `invoke_{worker_name}` in MCP API. |
| Scheduled tasks | Can target worker | ScheduledTask gains optional `digitalWorker` relation. |
| Worker limit | No limit | Users can create as many as needed. |

## Data Model

### New Entity: `DigitalWorker`

```
┌─────────────────────────────────────┐
│ digital_worker                       │
├─────────────────────────────────────┤
│ id              INT (PK, auto)       │
│ uuid            GUID (unique)        │
│ name            VARCHAR(255)         │
│ system_prompt   TEXT                 │
│ description     TEXT (nullable)      │
│ active          BOOL (default true)  │
│ disabled_skill_types  JSON (default [])  │
│ disabled_tools        JSON (default [])  │
│ user_id         INT (FK → user)      │
│ organisation_id INT (FK → organisation) │
│ created_at      DATETIME             │
│ updated_at      DATETIME             │
└─────────────────────────────────────┘
```

**`disabled_skill_types`**: Integration types fully disabled for this worker (e.g. `["jira", "confluence"]`). These integrations won't have sub-agents created at all.

**`disabled_tools`**: Specific tool names disabled within enabled integrations (e.g. `["sharepoint_create_page", "sharepoint_delete_page"]`). The platform's tool discovery/execution APIs filter these out when `worker_id` is passed.

### Modified Entity: `ScheduledTask`

Add optional relation:
```
digital_worker_id  INT (FK → digital_worker, nullable)
```

When set, the scheduled task executor sends the prompt to the orchestrator with `worker_id` in the payload, triggering direct worker execution (bypassing main agent routing).

## API Contract

### New Endpoint: `GET /api/digital-workers/`

Auth: Basic Auth (service-to-service, same as other API endpoints)

**Request:**
```
GET /api/digital-workers/?organisation_uuid=X&workflow_user_id=Y
Authorization: Basic xxxx==
```

**Response:**
```json
{
  "workers": [
    {
      "id": 42,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Internal News Worker",
      "system_prompt": "You are responsible for reviewing, publishing...",
      "description": "Handles internal news creation and publishing on SharePoint",
      "active": true,
      "disabled_skill_types": [],
      "disabled_tools": ["sharepoint_create_page"],
      "skills": [
        {
          "type": "sharepoint",
          "instance_id": 60,
          "instance_name": "Company SharePoint",
          "system_prompt": "<?xml version=\"1.0\"?>..."
        }
      ]
    }
  ]
}
```

The `skills` array contains only skills **not** in `disabled_skill_types` — pre-filtered for the orchestrator.

### Extended: Tool Discovery with `worker_id`

```
GET /api/integrations/{org-uuid}/?workflow_user_id=X&tool_type=sharepoint&worker_id=42
```

When `worker_id` is present:
1. Load the DigitalWorker entity
2. Verify ownership (same user, same org)
3. Filter out tools in the worker's `disabled_tools` from the response
4. Return only allowed tools

### Extended: Tool Execution with `worker_id`

```
POST /api/integrations/{org-uuid}/execute?workflow_user_id=X&worker_id=42
Body: {"tool_id": "sharepoint_search_60", "parameters": {...}}
```

When `worker_id` is present:
1. Load the DigitalWorker entity
2. Verify the requested tool is NOT in the worker's `disabled_tools`
3. Reject with 403 if the tool is disabled for this worker
4. Execute normally if allowed

### Extended: Webhook Payload with `worker_id`

For scheduled tasks and MCP-initiated worker invocations, the webhook payload includes:
```json
{
  "custom": {
    "worker_id": 42,
    ...
  }
}
```

When `worker_id` is set in the payload, the orchestrator:
1. Skips main agent routing
2. Builds only the targeted worker agent with its allowed sub-agents
3. Executes the worker directly and returns its response

## MCP Integration

### Tool Discovery

`GET /api/mcp/tools` returns additional tools for each active Digital Worker:

```json
{
  "name": "invoke_internal_news_worker",
  "description": "Delegate work to the 'Internal News Worker' digital employee. Handles internal news creation and publishing on SharePoint.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "prompt": {
        "type": "string",
        "description": "The task to delegate to this worker"
      }
    },
    "required": ["prompt"]
  }
}
```

### Tool Execution

When an MCP client calls `invoke_internal_news_worker`:
1. Platform resolves the worker from the tool name
2. Forwards the `prompt` to the orchestrator webhook with `worker_id` in the payload
3. Returns the orchestrator's response to the MCP client

## Orchestrator Changes

### Worker Agent Factory (`src/agents/worker_agent_factory.py`)

New module that:
1. Calls `GET /api/digital-workers/` to fetch user's active workers
2. For each worker, builds sub-agents using existing `create_sub_agents()` logic:
   - Only includes skills from the worker's `skills` array (pre-filtered by platform)
   - Passes `worker_id` to discover/execute tool builders for tool-level filtering
3. Creates an `LlmAgent` per worker with:
   - `name`: sanitized worker name (e.g. `internal_news_worker`)
   - `instruction`: worker's `system_prompt`
   - `description`: worker's `description` (for main agent routing)
   - `sub_agents`: the filtered sub-agents
4. Wraps each in `AgentTool` for use by the main agent

### Main Agent Enhancement (`src/agents/main_agent.py`)

In `_setup_agent()`:
1. Call `create_worker_agents()` to get worker AgentTools
2. Add to the main agent's `tools` list alongside native agent tools
3. Dynamically append a "DIGITAL WORKERS" section to the main agent instruction listing available workers and their descriptions

### Direct Worker Execution

When `payload.worker_id` is set (from scheduled tasks or MCP invoke):
- Skip main agent creation entirely
- Build only the targeted worker agent
- Execute it directly and return its response

### Sub-Agent Factory Extension (`src/agents/sub_agent_factory.py`)

Add optional `worker_id` parameter to `_build_discover_tool()` and `_build_execute_tool()`. When set, the discover/execute HTTP calls include `&worker_id=X` in the query string.

### Webhook Payload Extension (`src/webhook/payload_parser.py`)

Add optional field:
```python
worker_id: int | None = None  # parsed from custom.worker_id
```

## UI Design

### Navigation

Under existing **Workspace** dropdown in `_partials/header.html.twig`:
```
Workspace ▼
  ├── General
  ├── Skills
  ├── Digital Workers  ◄── NEW
  ├── Prompt Vault
  ├── Scheduled Tasks
  └── Knowledge Base
```

### List Page (`/digital-workers/`)

Table showing:
| Name | Description | Skills | Status | Actions |
|---|---|---|---|---|
| Internal News Worker | Handles internal news... | SharePoint | Active | Edit / Delete |
| Ticket Analyst | Analyzes Jira tickets... | Jira, Confluence | Inactive | Edit / Delete |

"Create Worker" button at top.

### Create/Edit Form (`/digital-workers/new`, `/digital-workers/{uuid}/edit`)

Fields:
1. **Name** — text input (required)
2. **System Prompt** — textarea (required, the worker's personality and instructions)
3. **Description** — text input (auto-generated, editable, shows "Auto-generated from system prompt" hint)
4. **Active** — toggle switch
5. **Skills & Tools** — accordion component:

```
▼ SharePoint (Company SP)          [✓ enabled]
  │  ✓ sharepoint_search
  │  ✓ sharepoint_list_files
  │  ✗ sharepoint_create_page      ← user disabled this tool
  │  ✓ sharepoint_read_document
  │
▶ Jira (Dev Board)                  [✓ enabled]
▶ People Finder                     [✗ disabled]  ← entire integration disabled
▶ Knowledge Base                    [✓ enabled]
```

The accordion shows only integrations the user has actually configured on `/skills/`. The integration-level toggle disables all tools at once (adds to `disabled_skill_types`). Individual tool checkboxes control `disabled_tools`.

### Scheduled Task Form Extension

The existing scheduled task create/edit form gains an optional **"Target Worker"** dropdown:
- Default: "Main Agent" (no worker, current behavior)
- Options: list of user's active Digital Workers

## Description Auto-Generation

### Service: `DigitalWorkerDescriptionService`

When a worker is created or its system prompt is edited:
1. Call LiteLLM (via the organisation's orchestrator API or directly) with a meta-prompt:
   ```
   Summarize the following AI agent system prompt into exactly 1-2 sentences
   that describe WHEN to delegate work to this agent and WHAT it specializes in.
   The summary will be used by a routing agent to decide which requests to send here.
   
   System prompt:
   {system_prompt}
   ```
2. Store the result in the `description` field
3. User can manually edit the description after generation

## Key Files

### New Files (Platform)

| File | Purpose |
|---|---|
| `src/Entity/DigitalWorker.php` | Entity with fields, deny-list logic |
| `src/Repository/DigitalWorkerRepository.php` | Queries: by user/org, by uuid, active workers |
| `src/Controller/DigitalWorkerController.php` | CRUD UI routes (list, create, edit, toggle, delete) |
| `src/Controller/DigitalWorkerApiController.php` | `GET /api/digital-workers/` for orchestrator |
| `src/Service/DigitalWorkerDescriptionService.php` | LLM-based description auto-generation |
| `templates/digital_worker/index.html.twig` | Worker list page |
| `templates/digital_worker/form.html.twig` | Create/edit form with accordion skill selector |
| `assets/controllers/skill_accordion_controller.js` | Stimulus controller for accordion UI |

### Modified Files (Platform)

| File | Change |
|---|---|
| `src/Entity/ScheduledTask.php` | Add nullable `digitalWorker` ManyToOne relation |
| `src/Controller/IntegrationApiController.php` | Accept optional `worker_id`, filter tools |
| `src/Service/ToolProviderService.php` | Apply worker deny-list when `worker_id` present |
| `src/Controller/McpApiController.php` | Add worker tools to MCP discovery + handle `invoke_*` execution |
| `src/Controller/ScheduledTaskController.php` | Add worker dropdown to form |
| `src/Service/ScheduledTask/CommonPayloadBuilder.php` | Add `worker_id` to webhook payload |
| `templates/_partials/header.html.twig` | Add "Digital Workers" nav item |
| `templates/scheduled_task/form.html.twig` | Add "Target Worker" dropdown |
| `assets/styles/app.scss` | Accordion component styles |
| `translations/messages.*.yaml` | Translation keys for all new UI text |

### New Files (Orchestrator)

| File | Purpose |
|---|---|
| `src/agents/worker_agent_factory.py` | Build worker LlmAgents from platform API, wrap as AgentTool |

### Modified Files (Orchestrator)

| File | Change |
|---|---|
| `src/utils/http_client.py` | Add `fetch_digital_workers()` |
| `src/agents/main_agent.py` | Integrate worker AgentTools + direct execution path when `worker_id` set |
| `src/agents/sub_agent_factory.py` | Add `worker_id` param to discover/execute tool builders |
| `src/webhook/payload_parser.py` | Add `worker_id` field, parse from `custom.worker_id` |
| `src/agents/prompts.py` | Dynamic "DIGITAL WORKERS" section in main agent instruction |

## Security Considerations

- Workers are scoped to user + organisation (same as IntegrationConfig)
- Worker tool filtering is enforced server-side in the platform API — the orchestrator cannot bypass it
- Worker API endpoint requires Basic Auth (service-to-service)
- MCP worker tools use X-Prompt-Token auth (maps to user)
- A worker can only use skills/tools that the owning user has actually configured and has credentials for
- Tool access modes (Read Only / Standard / Full) are still enforced by the platform on top of worker filtering

## Implementation Order

1. Entity + DB schema (foundation)
2. API endpoint (orchestrator needs to discover workers)
3. UI (users need to create workers)
4. Orchestrator changes (workers become functional)
5. MCP exposure (extends to Claude Desktop)
6. Scheduled task integration (extends to automation)
7. Main agent prompt tuning (polish)
