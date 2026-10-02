#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

: "${JVMETA_APP_DIR:=/home/joos/jvmeta}"
: "${JVMETA_REPO_URL:=https://github.com/jooservices/jvmeta.git}"
: "${JVMETA_BRANCH:=develop}"
: "${JVMETA_COMPOSE_FILE:=docker-compose.yml}"
: "${JVMETA_EMBEDDER_SERVICE:=embedder}"
: "${JVMETA_EMBEDDER_RETRIES:=40}"
: "${JVMETA_EMBEDDER_INTERVAL:=3}"

# shellcheck disable=SC1091
source "${SCRIPT_DIR}/repository.sh"

trap 'printf "deploy: failed at line %s: %s\n" "$LINENO" "$BASH_COMMAND" >&2' ERR

require_command git
require_command docker

sync_repository
prepare_environment
compose config --quiet
compose build "${JVMETA_EMBEDDER_SERVICE}"
compose up -d --force-recreate "${JVMETA_EMBEDDER_SERVICE}"

ready=false
for ((attempt = 1; attempt <= JVMETA_EMBEDDER_RETRIES; attempt++)); do
  if compose exec -T "${JVMETA_EMBEDDER_SERVICE}" python -c \
    'import urllib.request; urllib.request.urlopen("http://127.0.0.1:8000/healthz", timeout=5)' \
    >/dev/null 2>&1; then
    ready=true
    break
  fi
  sleep "${JVMETA_EMBEDDER_INTERVAL}"
done

[[ "${ready}" == true ]] || die 'embedder health check did not become ready'

compose exec -T "${JVMETA_EMBEDDER_SERVICE}" python -c '
import json
import urllib.request

payload = json.dumps({"model": "jvmeta-passage", "input": "deployment warmup"}).encode()
request = urllib.request.Request(
    "http://127.0.0.1:8000/v1/embeddings",
    data=payload,
    headers={"Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(request, timeout=120) as response:
    body = json.loads(response.read())
if len(body.get("data", [])) != 1:
    raise RuntimeError("embedder returned no vector")
'

printf 'deploy: embedder is healthy and warm\n'
