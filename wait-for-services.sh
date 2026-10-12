#!/usr/bin/env bash
set -euo pipefail

timeout_seconds="${WAIT_TIMEOUT_SECONDS:-180}"
interval_seconds="${WAIT_INTERVAL_SECONDS:-2}"
compose_args=()
if [ -n "${COMPOSE_ENV_FILE:-}" ]; then
    compose_args+=(--env-file "$COMPOSE_ENV_FILE")
fi

compose() {
    docker compose "${compose_args[@]}" "$@"
}

if [ "$#" -gt 0 ]; then
    services=("$@")
else
    services=(postgres redis meilisearch matching frontend backend nginx)
fi

for service in "${services[@]}"; do
    deadline=$((SECONDS + timeout_seconds))
    printf 'Waiting for %s readiness' "$service"
    ready=false

    while [ "$SECONDS" -lt "$deadline" ]; do
        container_id="$(compose ps -q "$service" 2>/dev/null || true)"
        if [ -n "$container_id" ]; then
            state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null || true)"
            if [ "$state" = "healthy" ] || [ "$state" = "running" ]; then
                ready=true
                break
            fi
            if [ "$state" = "exited" ] || [ "$state" = "dead" ]; then
                printf '\n[ERROR] %s stopped before becoming ready\n' "$service" >&2
                compose logs --tail=80 "$service" >&2 || true
                exit 1
            fi
        fi
        printf '.'
        sleep "$interval_seconds"
    done

    if [ "$ready" != true ]; then
        printf '\n[ERROR] Timed out waiting for %s\n' "$service" >&2
        compose ps "$service" >&2 || true
        compose logs --tail=80 "$service" >&2 || true
        exit 1
    fi
    printf ' ready\n'
done
