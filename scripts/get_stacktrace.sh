#!/bin/bash
#
# Phoenix Arize Stacktrace Fetcher
# Fetches conversation traces from Phoenix for debugging prompt-related issues.
#
# Queries the Phoenix PostgreSQL database on production to retrieve full trace
# trees for a given workflow_user_id, including all spans (webhook, agent, tools, LLM).
#
# Usage:
#   ./scripts/get_stacktrace.sh <workflow_user_id> [date_range]
#   ./scripts/get_stacktrace.sh --list-users [date_range]
#   ./scripts/get_stacktrace.sh --schema
#
# Date range formats:
#   1h, 6h, 24h   - last N hours (default: 24h)
#   1d, 7d, 30d   - last N days
#   2026-03-30     - specific date
#   2026-03-25:2026-03-30 - date range
#
# Environment variables (override defaults):
#   PHOENIX_SSH_HOST       SSH host (default: val-workoflow-prod)
#   PHOENIX_DB_CONTAINER   Docker container (default: phoenix-postgres)
#   PHOENIX_DB_USER        DB user (default: phoenix)
#   PHOENIX_DB_NAME        DB name (default: phoenix)
#   PHOENIX_PROJECT        Project name (default: workoflow-orchestrator)
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'
DIM='\033[2m'

# Configuration (overridable via environment)
SSH_HOST="${PHOENIX_SSH_HOST:-val-workoflow-prod}"
DB_CONTAINER="${PHOENIX_DB_CONTAINER:-phoenix-postgres}"
DB_USER="${PHOENIX_DB_USER:-phoenix}"
DB_NAME="${PHOENIX_DB_NAME:-phoenix}"
PROJECT="${PHOENIX_PROJECT:-workoflow-orchestrator}"

# Defaults
LIMIT=10
COMPACT=false
RAW_SQL=false

# ── Helpers ──────────────────────────────────────────────────────────────────

usage() {
    cat <<EOF
${BOLD}Phoenix Arize Stacktrace Fetcher${NC}
Fetch conversation traces from Phoenix for debugging prompt issues.

${YELLOW}Usage:${NC}
  $0 <workflow_user_id> [date_range]        Get traces for a user
  $0 --list-users [date_range]              List users with recent traces
  $0 --schema                               Show Phoenix DB schema
  $0 --sample [date_range]                  Show sample span attribute keys

${YELLOW}Arguments:${NC}
  workflow_user_id    The user's workflow_user_id (from Integration Platform)
  date_range          Time range to search (default: 24h)

${YELLOW}Date range formats:${NC}
  1h, 6h, 24h                Last N hours
  1d, 7d, 30d                Last N days
  2026-03-30                 Specific date (full day)
  2026-03-25:2026-03-30      Date range (inclusive)

${YELLOW}Options:${NC}
  --limit N           Max traces to return (default: 10)
  --compact           Minimal output (key fields only, truncated I/O)
  --raw-sql           Print the SQL query without executing
  --list-users        List users with recent traces
  --schema            Show Phoenix database table schema
  --sample            Show sample attribute keys from recent spans
  --help              Show this help

${YELLOW}Examples:${NC}
  $0 abc123-def456-789
  $0 abc123-def456-789 7d
  $0 abc123-def456-789 2026-03-25:2026-03-30 --compact
  $0 --list-users 7d
  $0 --schema

${YELLOW}Environment variables:${NC}
  PHOENIX_SSH_HOST       SSH host (default: val-workoflow-prod)
  PHOENIX_DB_CONTAINER   Docker container (default: phoenix-postgres)
  PHOENIX_DB_USER        DB user (default: phoenix)
  PHOENIX_DB_NAME        DB name (default: phoenix)
  PHOENIX_PROJECT        Project name (default: workoflow-orchestrator)
EOF
}

err() {
    echo -e "${RED}Error: $1${NC}" >&2
}

info() {
    echo -e "${DIM}$1${NC}" >&2
}

# Parse a date range string into START_DATE and END_DATE (UTC ISO 8601)
parse_date_range() {
    local range="${1:-24h}"
    local now
    now=$(date -u +%s)

    case "$range" in
        *h)
            local hours="${range%h}"
            if ! [[ "$hours" =~ ^[0-9]+$ ]]; then
                err "Invalid hours value: $hours"
                exit 1
            fi
            START_DATE=$(date -u -d "@$((now - hours * 3600))" +"%Y-%m-%dT%H:%M:%SZ")
            END_DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
            ;;
        *d)
            local days="${range%d}"
            if ! [[ "$days" =~ ^[0-9]+$ ]]; then
                err "Invalid days value: $days"
                exit 1
            fi
            START_DATE=$(date -u -d "@$((now - days * 86400))" +"%Y-%m-%dT%H:%M:%SZ")
            END_DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
            ;;
        *:*)
            local start_part="${range%%:*}"
            local end_part="${range##*:}"
            if ! [[ "$start_part" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] || \
               ! [[ "$end_part" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
                err "Invalid date range format. Use YYYY-MM-DD:YYYY-MM-DD"
                exit 1
            fi
            START_DATE="${start_part}T00:00:00Z"
            END_DATE="${end_part}T23:59:59Z"
            ;;
        [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9])
            START_DATE="${range}T00:00:00Z"
            END_DATE="${range}T23:59:59Z"
            ;;
        *)
            err "Invalid date range format: $range"
            echo "  Use: 1h, 24h, 7d, 30d, YYYY-MM-DD, or YYYY-MM-DD:YYYY-MM-DD" >&2
            exit 1
            ;;
    esac
}

# Sanitize input to prevent SQL injection (basic check for a debugging script)
sanitize() {
    local val="$1"
    if [[ "$val" =~ [\;\'\"\\\$\`] ]]; then
        err "Invalid characters in argument: $val"
        exit 1
    fi
    echo "$val"
}

# Execute SQL on remote Phoenix PostgreSQL via SSH + docker exec
# When RAW_SQL=true, prints the SQL and returns without executing.
run_psql() {
    local sql="$1"

    if [ "$RAW_SQL" = true ]; then
        echo "$sql"
        return 0
    fi

    info "Connecting to $SSH_HOST → $DB_CONTAINER ..."

    local result
    result=$(echo "$sql" | ssh "$SSH_HOST" \
        "sudo -u docker docker exec -i $DB_CONTAINER psql -U $DB_USER -d $DB_NAME -t -A" 2>&1)

    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        # Retry with login shell approach if non-login sudo failed
        info "Retrying with login shell..."
        result=$(echo "$sql" | ssh "$SSH_HOST" \
            "sudo -iu docker sh -c 'docker exec -i $DB_CONTAINER psql -U $DB_USER -d $DB_NAME -t -A'" 2>&1)
        exit_code=$?
    fi

    if [ $exit_code -ne 0 ]; then
        err "Failed to execute query on remote database"
        echo "$result" >&2
        exit 1
    fi

    echo "$result"
}

# Run SQL and format output. In --raw-sql mode, prints SQL without formatting.
run_and_format() {
    local sql="$1"
    if [ "$RAW_SQL" = true ]; then
        run_psql "$sql"
    else
        run_psql "$sql" | format_json
    fi
}

# Format JSON output (pretty-print with jq if available)
format_json() {
    if command -v jq &> /dev/null; then
        jq .
    else
        cat
    fi
}

# ── Commands ─────────────────────────────────────────────────────────────────

cmd_schema() {
    info "Fetching Phoenix database schema..."

    local sql="
SELECT json_build_object(
    'database', current_database(),
    'tables', (
        SELECT json_agg(json_build_object(
            'table_name', t.table_name,
            'columns', (
                SELECT json_agg(json_build_object(
                    'column_name', c.column_name,
                    'data_type', c.data_type,
                    'is_nullable', c.is_nullable
                ) ORDER BY c.ordinal_position)
                FROM information_schema.columns c
                WHERE c.table_schema = t.table_schema
                AND c.table_name = t.table_name
            )
        ) ORDER BY t.table_name)
        FROM information_schema.tables t
        WHERE t.table_schema = 'public'
        AND t.table_type = 'BASE TABLE'
    )
);"

    run_and_format "$sql"
}

cmd_sample() {
    parse_date_range "${1:-24h}"

    info "Fetching sample attribute keys from recent spans..."

    local sql="
SELECT json_build_object(
    'project', '$PROJECT',
    'date_range', json_build_object('start', '$START_DATE', 'end', '$END_DATE'),
    'attribute_keys', (
        SELECT json_agg(DISTINCT key ORDER BY key)
        FROM (
            SELECT jsonb_object_keys(s.attributes) AS key
            FROM spans s
            JOIN traces t ON s.trace_rowid = t.id
            JOIN projects p ON t.project_rowid = p.id
            WHERE p.name = '$PROJECT'
            AND s.start_time >= '$START_DATE'::timestamptz
            AND s.start_time <= '$END_DATE'::timestamptz
            LIMIT 500
        ) keys
    ),
    'span_names', (
        SELECT json_agg(DISTINCT s.name ORDER BY s.name)
        FROM spans s
        JOIN traces t ON s.trace_rowid = t.id
        JOIN projects p ON t.project_rowid = p.id
        WHERE p.name = '$PROJECT'
        AND s.start_time >= '$START_DATE'::timestamptz
        AND s.start_time <= '$END_DATE'::timestamptz
    ),
    'sample_span', (
        SELECT row_to_json(sub)
        FROM (
            SELECT s.name, s.span_kind, s.status_code,
                   s.start_time, s.attributes
            FROM spans s
            JOIN traces t ON s.trace_rowid = t.id
            JOIN projects p ON t.project_rowid = p.id
            WHERE p.name = '$PROJECT'
            AND s.name = 'invocation [workoflow]'
            AND s.start_time >= '$START_DATE'::timestamptz
            AND s.start_time <= '$END_DATE'::timestamptz
            ORDER BY s.start_time DESC
            LIMIT 1
        ) sub
    )
);"

    run_and_format "$sql"
}

cmd_list_users() {
    parse_date_range "${1:-24h}"

    info "Listing users with traces ($START_DATE to $END_DATE)..."

    local sql="
SELECT json_build_object(
    'project', '$PROJECT',
    'date_range', json_build_object('start', '$START_DATE', 'end', '$END_DATE'),
    'users', COALESCE((
        SELECT json_agg(user_row ORDER BY user_row->>'last_seen' DESC)
        FROM (
            SELECT json_build_object(
                'workflow_user_id', s.attributes->'user'->>'id',
                'trace_count', COUNT(DISTINCT t.trace_id),
                'span_count', COUNT(*),
                'first_seen', MIN(s.start_time),
                'last_seen', MAX(s.start_time)
            ) AS user_row
            FROM spans s
            JOIN traces t ON s.trace_rowid = t.id
            JOIN projects p ON t.project_rowid = p.id
            WHERE p.name = '$PROJECT'
            AND s.attributes->'user'->>'id' IS NOT NULL
            AND s.start_time >= '$START_DATE'::timestamptz
            AND s.start_time <= '$END_DATE'::timestamptz
            GROUP BY s.attributes->'user'->>'id'
        ) sub
    ), '[]'::json)
);"

    run_and_format "$sql"
}

cmd_traces_full() {
    local workflow_user_id="$1"
    parse_date_range "${2:-24h}"

    info "Fetching traces for user $workflow_user_id ($START_DATE to $END_DATE, limit $LIMIT)..."

    local sql="
WITH matching_traces AS (
    SELECT DISTINCT t.id AS trace_rowid, t.trace_id
    FROM spans s
    JOIN traces t ON s.trace_rowid = t.id
    JOIN projects p ON t.project_rowid = p.id
    WHERE p.name = '$PROJECT'
    AND s.attributes->'user'->>'id' = '$workflow_user_id'
    AND s.start_time >= '$START_DATE'::timestamptz
    AND s.start_time <= '$END_DATE'::timestamptz
    ORDER BY t.trace_id
    LIMIT $LIMIT
)
SELECT json_build_object(
    'query', json_build_object(
        'workflow_user_id', '$workflow_user_id',
        'project', '$PROJECT',
        'date_range', json_build_object('start', '$START_DATE', 'end', '$END_DATE'),
        'limit', $LIMIT
    ),
    'trace_count', (SELECT COUNT(*) FROM matching_traces),
    'traces', COALESCE((
        SELECT json_agg(trace_data ORDER BY (trace_data->>'start_time') DESC)
        FROM (
            SELECT json_build_object(
                'trace_id', mt.trace_id,
                'start_time', MIN(s.start_time),
                'end_time', MAX(s.end_time),
                'duration_ms', EXTRACT(EPOCH FROM (MAX(s.end_time) - MIN(s.start_time))) * 1000,
                'span_count', COUNT(*),
                'spans', json_agg(
                    json_build_object(
                        'span_id', s.span_id,
                        'parent_id', s.parent_id,
                        'name', s.name,
                        'span_kind', s.span_kind,
                        'start_time', s.start_time,
                        'end_time', s.end_time,
                        'duration_ms', EXTRACT(EPOCH FROM (s.end_time - s.start_time)) * 1000,
                        'status_code', s.status_code,
                        'status_message', s.status_message,
                        'attributes', s.attributes,
                        'events', s.events,
                        'cumulative_error_count', s.cumulative_error_count,
                        'cumulative_llm_token_count_prompt', s.cumulative_llm_token_count_prompt,
                        'cumulative_llm_token_count_completion', s.cumulative_llm_token_count_completion
                    ) ORDER BY s.start_time
                )
            ) AS trace_data
            FROM matching_traces mt
            JOIN spans s ON s.trace_rowid = mt.trace_rowid
            GROUP BY mt.trace_id
        ) sub
    ), '[]'::json)
);"

    run_and_format "$sql"
}

cmd_traces_compact() {
    local workflow_user_id="$1"
    parse_date_range "${2:-24h}"

    info "Fetching traces (compact) for user $workflow_user_id ($START_DATE to $END_DATE, limit $LIMIT)..."

    local sql="
WITH matching_traces AS (
    SELECT DISTINCT t.id AS trace_rowid, t.trace_id
    FROM spans s
    JOIN traces t ON s.trace_rowid = t.id
    JOIN projects p ON t.project_rowid = p.id
    WHERE p.name = '$PROJECT'
    AND s.attributes->'user'->>'id' = '$workflow_user_id'
    AND s.start_time >= '$START_DATE'::timestamptz
    AND s.start_time <= '$END_DATE'::timestamptz
    ORDER BY t.trace_id
    LIMIT $LIMIT
)
SELECT json_build_object(
    'query', json_build_object(
        'workflow_user_id', '$workflow_user_id',
        'project', '$PROJECT',
        'date_range', json_build_object('start', '$START_DATE', 'end', '$END_DATE'),
        'limit', $LIMIT,
        'mode', 'compact'
    ),
    'trace_count', (SELECT COUNT(*) FROM matching_traces),
    'traces', COALESCE((
        SELECT json_agg(trace_data ORDER BY (trace_data->>'start_time') DESC)
        FROM (
            SELECT json_build_object(
                'trace_id', mt.trace_id,
                'start_time', MIN(s.start_time),
                'end_time', MAX(s.end_time),
                'duration_ms', EXTRACT(EPOCH FROM (MAX(s.end_time) - MIN(s.start_time))) * 1000,
                'span_count', COUNT(*),
                'spans', json_agg(
                    json_build_object(
                        'span_id', s.span_id,
                        'parent_id', s.parent_id,
                        'name', s.name,
                        'span_kind', s.span_kind,
                        'start_time', s.start_time,
                        'duration_ms', EXTRACT(EPOCH FROM (s.end_time - s.start_time)) * 1000,
                        'status_code', s.status_code,
                        'workflow_user_id', s.attributes->'user'->>'id',
                        'session_id', s.attributes->'session'->>'id',
                        'org_uuid', s.attributes->>'org_uuid',
                        'execution_id', s.attributes->>'execution_id',
                        'tool_id', s.attributes->>'tool_id',
                        'input_preview', LEFT(s.attributes->'input'->>'value', 300),
                        'output_preview', LEFT(s.attributes->'output'->>'value', 300)
                    ) ORDER BY s.start_time
                )
            ) AS trace_data
            FROM matching_traces mt
            JOIN spans s ON s.trace_rowid = mt.trace_rowid
            GROUP BY mt.trace_id
        ) sub
    ), '[]'::json)
);"

    run_and_format "$sql"
}

# ── Main ─────────────────────────────────────────────────────────────────────

COMMAND=""
WORKFLOW_USER_ID=""
DATE_RANGE=""
POSITIONAL_ARGS=()

for arg in "$@"; do
    case $arg in
        --help|-h)
            usage
            exit 0
            ;;
        --schema)
            COMMAND="schema"
            ;;
        --sample)
            COMMAND="sample"
            ;;
        --list-users)
            COMMAND="list-users"
            ;;
        --compact)
            COMPACT=true
            ;;
        --raw-sql)
            RAW_SQL=true
            ;;
        --limit)
            # Next arg will be the value; handled below
            NEXT_IS_LIMIT=true
            ;;
        *)
            if [ "${NEXT_IS_LIMIT:-}" = true ]; then
                LIMIT="$arg"
                NEXT_IS_LIMIT=false
            else
                POSITIONAL_ARGS+=("$arg")
            fi
            ;;
    esac
done

# Handle --limit N as two separate args or --limit=N
for i in "${!POSITIONAL_ARGS[@]}"; do
    if [[ "${POSITIONAL_ARGS[$i]}" =~ ^--limit=(.+)$ ]]; then
        LIMIT="${BASH_REMATCH[1]}"
        unset 'POSITIONAL_ARGS[$i]'
    fi
done
# Re-index
POSITIONAL_ARGS=("${POSITIONAL_ARGS[@]}")

# Dispatch
case "${COMMAND:-}" in
    schema)
        cmd_schema
        ;;
    sample)
        cmd_sample "${POSITIONAL_ARGS[0]:-24h}"
        ;;
    list-users)
        cmd_list_users "${POSITIONAL_ARGS[0]:-24h}"
        ;;
    "")
        # Default: get traces for a user
        if [ ${#POSITIONAL_ARGS[@]} -lt 1 ]; then
            err "Missing required argument: workflow_user_id"
            echo "" >&2
            usage >&2
            exit 1
        fi

        WORKFLOW_USER_ID=$(sanitize "${POSITIONAL_ARGS[0]}")
        DATE_RANGE="${POSITIONAL_ARGS[1]:-24h}"

        if [ "$COMPACT" = true ]; then
            cmd_traces_compact "$WORKFLOW_USER_ID" "$DATE_RANGE"
        else
            cmd_traces_full "$WORKFLOW_USER_ID" "$DATE_RANGE"
        fi
        ;;
esac
