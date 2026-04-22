#!/bin/bash
#
# Workoflow Orchestrator Prompt Executor
# Sends a prompt to the ADK orchestrator webhook and returns the response.
#
# Usage:
#   ./scripts/execute_prompt.sh <workflow_user_id> <org_uuid> "Your prompt here"
#
# Examples:
#   ./scripts/execute_prompt.sh 45908692-019e-4436-810c-b417f58f5f4f ae6f26a3-6f27-4ed6-a3a8-800c3226fb79 "Haben wir schonmal ein CAD Feature gebaut?"
#
# Reads from .env:
#   ORCHESTRATOR_URL          Orchestrator base URL (e.g. http://localhost:8080)
#   ORCHESTRATOR_AUTH_TOKEN   Webhook Bearer token
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'
DIM='\033[2m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Load orchestrator vars from .env (grep to avoid sourcing Symfony-style values)
if [ -f "$PROJECT_DIR/.env" ]; then
    : "${ORCHESTRATOR_URL:=$(grep '^ORCHESTRATOR_URL=' "$PROJECT_DIR/.env" | head -1 | cut -d= -f2-)}"
    : "${ORCHESTRATOR_AUTH_TOKEN:=$(grep '^ORCHESTRATOR_AUTH_TOKEN=' "$PROJECT_DIR/.env" | head -1 | cut -d= -f2-)}"
fi

ORCH_URL="${ORCHESTRATOR_URL:?Missing ORCHESTRATOR_URL in .env}"
ORCH_TOKEN="${ORCHESTRATOR_AUTH_TOKEN:?Missing ORCHESTRATOR_AUTH_TOKEN in .env}"

usage() {
    echo -e "${BOLD}Workoflow Orchestrator Prompt Executor${NC}"
    echo ""
    echo "Usage:"
    echo "  $0 <workflow_user_id> <org_uuid> \"<prompt>\""
    echo ""
    echo "Examples:"
    echo "  $0 45908692-019e-4436-810c-b417f58f5f4f ae6f26a3-6f27-4ed6-a3a8-800c3226fb79 \"What is ISMS?\""
    exit 1
}

if [ $# -lt 3 ]; then
    usage
fi

WORKFLOW_USER_ID="$1"
ORG_UUID="$2"
PROMPT="$3"
CONV_ID="cli-test-$(date +%s)"

echo -e "${DIM}Sending prompt to ${ORCH_URL}/webhook ...${NC}"
echo -e "${CYAN}User:${NC}   ${WORKFLOW_USER_ID}"
echo -e "${CYAN}Org:${NC}    ${ORG_UUID}"
echo -e "${CYAN}Prompt:${NC} ${PROMPT}"
echo ""

ESCAPED_PROMPT=$(printf '%s' "$PROMPT" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')

RESPONSE=$(curl -s -w "\n%{http_code}" \
  -X POST "${ORCH_URL}/webhook" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${ORCH_TOKEN}" \
  --max-time 180 \
  -d "{
    \"text\": ${ESCAPED_PROMPT},
    \"conversation\": {
      \"tenantId\": \"${ORG_UUID}\",
      \"id\": \"${CONV_ID}\"
    },
    \"from\": {
      \"aadObjectId\": \"${WORKFLOW_USER_ID}\",
      \"name\": \"CLI Test\"
    },
    \"custom\": {
      \"conversationId\": \"${CONV_ID}\",
      \"user\": {
        \"aadObjectId\": \"${WORKFLOW_USER_ID}\",
        \"displayName\": \"CLI Test\"
      },
      \"isThreadReply\": false
    },
    \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
  }")

HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo -e "${GREEN}${BOLD}Response (HTTP ${HTTP_CODE}):${NC}"
    echo "$BODY" | python3 -m json.tool 2>/dev/null || echo "$BODY"
else
    echo -e "${RED}${BOLD}Error (HTTP ${HTTP_CODE}):${NC}"
    echo "$BODY" | python3 -m json.tool 2>/dev/null || echo "$BODY"
    exit 1
fi
