---
name: debug-stacktrace-prompt
description: Investigate a user prompt that was not answered correctly by the Workoflow orchestrator. Fetches Phoenix Arize stacktraces, identifies failed prompts, cross-references the knowledge base, and diagnoses root cause.
disable-model-invocation: true
argument-hint: <workflow_user_id>
allowed-tools: Bash(./scripts/get_stacktrace.sh *)
---

Investigate a user prompt that was not answered correctly by the Workoflow orchestrator.

## Context

Read `DEFAULT_ORG_UUID` and `DEFAULT_WORKFLOW_USER_ID` from the project `.env` file.

- **User ID**: $ARGUMENTS (if provided), otherwise use `DEFAULT_WORKFLOW_USER_ID` from `.env`
- **Tenant/Org ID**: Use `DEFAULT_ORG_UUID` from `.env`

## Steps

1. **Fetch today's stacktraces** from Phoenix Arize using `./scripts/get_stacktrace.sh --compact <user_id> today`. If "today" doesn't show results, try the last 24h or 7d.

2. **Identify the problematic prompt**: Look through the traces for prompts that returned poor/empty results. Pay attention to:
   - `call_llm` spans — what the agent reasoned
   - `tool.execute` spans — what tools were called and what they returned
   - `invocation [workoflow]` — the root span with input/output

3. **Cross-reference with knowledge base**: Check what's actually indexed by:
   - Looking at knowledge base documents in the platform (use workoflow MCP tools like `orchestrator_search_knowledge_base` or `orchestrator_list_knowledge_sources`)
   - SSH into the production server (use `PROD_SSH_HOST` from `.env`) if needed to inspect config or query services directly
   - Check the `workoflow-orchestrator` project if retrieval/indexing code needs inspection

4. **Diagnose the root cause**: Determine why the prompt failed:
   - Was the content not indexed at all?
   - Was the retrieval query poorly formed?
   - Did the agent not use the right tool?
   - Was the embedding/chunking insufficient?

5. **Report findings**: Summarize:
   - The exact prompt that failed
   - What the agent did (tool calls, reasoning)
   - What it should have found
   - Root cause and suggested fix
