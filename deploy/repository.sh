#!/usr/bin/env bash

set -Eeuo pipefail

die() {
  printf 'deploy: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "required command is missing: $1"
}

sync_repository() {
  if [[ -d "${JVMETA_APP_DIR}/.git" ]]; then
    if [[ -n "$(git -C "${JVMETA_APP_DIR}" status --porcelain --untracked-files=all)" ]]; then
      die "repository is not clean: ${JVMETA_APP_DIR}"
    fi

    local current_branch
    current_branch="$(git -C "${JVMETA_APP_DIR}" branch --show-current)"
    [[ "${current_branch}" == "${JVMETA_BRANCH}" ]] || die "expected branch ${JVMETA_BRANCH}, found ${current_branch:-detached}"

    GIT_TERMINAL_PROMPT=0 git -C "${JVMETA_APP_DIR}" pull --ff-only origin "${JVMETA_BRANCH}"
    return
  fi

  [[ ! -e "${JVMETA_APP_DIR}" ]] || die "application path exists but is not a Git repository: ${JVMETA_APP_DIR}"

  mkdir -p "$(dirname "${JVMETA_APP_DIR}")"
  GIT_TERMINAL_PROMPT=0 git clone --branch "${JVMETA_BRANCH}" --single-branch "${JVMETA_REPO_URL}" "${JVMETA_APP_DIR}"
}

prepare_environment() {
  if [[ -n "${JVMETA_ENV_SOURCE:-}" ]]; then
    [[ -f "${JVMETA_ENV_SOURCE}" ]] || die "runtime env source does not exist: ${JVMETA_ENV_SOURCE}"

    if [[ "${JVMETA_ENV_SOURCE}" != "${JVMETA_APP_DIR}/.env" ]]; then
      install -m 600 "${JVMETA_ENV_SOURCE}" "${JVMETA_APP_DIR}/.env"
    fi
  fi

  [[ -f "${JVMETA_APP_DIR}/.env" ]] || die "missing ${JVMETA_APP_DIR}/.env; set JVMETA_ENV_SOURCE"
  chmod 600 "${JVMETA_APP_DIR}/.env"
}

compose() {
  docker compose \
    --project-directory "${JVMETA_APP_DIR}" \
    --env-file "${JVMETA_APP_DIR}/.env" \
    --file "${JVMETA_APP_DIR}/${JVMETA_COMPOSE_FILE}" \
    --profile app \
    --profile control \
    --profile embed \
    "$@"
}
