#!/usr/bin/env bash
# One process per Laravel crawl queue name (1 worker / queue on this instance).
set -euo pipefail

CONNECTION="${QUEUE_CONNECTION:-database}"
QUEUES="${JVMETA_QUEUE_NAMES:-listing,detail,performer_listing,performer_detail,gallery}"
SLEEP="${JVMETA_QUEUE_SLEEP:-3}"
TRIES="${JVMETA_QUEUE_TRIES:-3}"
MAX_TIME="${JVMETA_QUEUE_MAX_TIME:-3600}"
MAX_JOBS="${JVMETA_QUEUE_MAX_JOBS:-500}"
WORKERS_PER_QUEUE="${JVMETA_WORKERS_PER_QUEUE:-1}"

IFS=',' read -r -a QUEUE_LIST <<< "${QUEUES}"

pids=()

shutdown() {
  for pid in "${pids[@]:-}"; do
    kill "${pid}" 2>/dev/null || true
  done
  wait || true
}

trap shutdown EXIT INT TERM

for queue in "${QUEUE_LIST[@]}"; do
  queue="$(echo "${queue}" | xargs)"
  [[ -z "${queue}" ]] && continue
  for ((i = 1; i <= WORKERS_PER_QUEUE; i++)); do
    echo "Starting worker instance queue=${queue} slot=${i}/${WORKERS_PER_QUEUE}"
    php artisan queue:work "${CONNECTION}" \
      --queue="${queue}" \
      --sleep="${SLEEP}" \
      --tries="${TRIES}" \
      --max-time="${MAX_TIME}" \
      --max-jobs="${MAX_JOBS}" \
      --no-interaction &
    pids+=($!)
  done
done

# Exit when any worker dies so the container orchestrator can restart.
wait -n "${pids[@]}"
exit_code=$?
shutdown
exit "${exit_code}"
