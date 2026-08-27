#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
cd "$ROOT_DIR"
umask 077

ENV_ONLY=false
case "${1:-}" in
    --env-only)
        ENV_ONLY=true
        ;;
    --help|-h)
        printf 'Usage: bash setup.sh [--env-only]\n'
        printf '  --env-only  create/validate local env files without starting Docker\n'
        exit 0
        ;;
    "")
        ;;
    *)
        printf 'Unknown option: %s\n' "$1" >&2
        exit 2
        ;;
esac

die() {
    printf '[ERROR] %s\n' "$1" >&2
    exit 1
}

env_value() {
    local file="$1"
    local name="$2"
    awk -F= -v name="$name" '$1 == name { sub(/^[^=]*=/, ""); print; exit }' "$file"
}

set_env_value() {
    local file="$1"
    local name="$2"
    local value="$3"

    if grep -q "^${name}=" "$file"; then
        sed -i "s|^${name}=.*$|${name}=${value}|" "$file"
    else
        printf '\n%s=%s\n' "$name" "$value" >> "$file"
    fi
}

is_placeholder() {
    case "$1" in
        ""|__GENERATE_LOCAL_SECRET__|generate-a-long-random-value|change-me|changeme)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

random_hex() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    else
        od -An -N32 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

random_app_key() {
    if command -v openssl >/dev/null 2>&1; then
        printf 'base64:%s' "$(openssl rand -base64 32 | tr -d '\n')"
    else
        printf 'base64:%s' "$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    fi
}

ensure_env_file() {
    local target="$1"
    local example="$2"

    [ -f "$example" ] || die "Missing environment template: $example"
    if [ -e "$target" ]; then
        printf '[OK] Preserving %s\n' "$target"
    else
        cp -- "$example" "$target"
        chmod 600 "$target"
        printf '[OK] Created %s from %s\n' "$target" "$example"
    fi
}

sync_secret() {
    local left_file="$1"
    local left_name="$2"
    local right_file="$3"
    local right_name="$4"
    local description="$5"
    local left_value right_value generated

    left_value="$(env_value "$left_file" "$left_name")"
    right_value="$(env_value "$right_file" "$right_name")"

    if is_placeholder "$left_value" && is_placeholder "$right_value"; then
        generated="$(random_hex)"
        set_env_value "$left_file" "$left_name" "$generated"
        set_env_value "$right_file" "$right_name" "$generated"
    elif is_placeholder "$left_value"; then
        set_env_value "$left_file" "$left_name" "$right_value"
    elif is_placeholder "$right_value"; then
        set_env_value "$right_file" "$right_name" "$left_value"
    elif [ "$left_value" != "$right_value" ]; then
        die "$description differs between $left_file and $right_file"
    fi
}

require_value() {
    local file="$1"
    local name="$2"
    local value
    value="$(env_value "$file" "$name")"
    if is_placeholder "$value"; then
        die "$name in $file is empty or still a placeholder"
    fi
}

prepare_environment() {
    ensure_env_file .env .env.example
    ensure_env_file .env.backend .env.backend.example
    ensure_env_file .env.frontend .env.frontend.example
    ensure_env_file .env.matching .env.matching.example

    sync_secret .env POSTGRES_PASSWORD .env.backend DB_PASSWORD "PostgreSQL password"
    sync_secret .env MEILI_MASTER_KEY .env.backend MEILISEARCH_KEY "Meilisearch key"
    sync_secret .env.backend MATCHING_SERVICE_API_KEY .env.matching MATCHING_SERVICE_API_KEY "Matching API key"

    local app_key
    app_key="$(env_value .env.backend APP_KEY)"
    if is_placeholder "$app_key"; then
        set_env_value .env.backend APP_KEY "$(random_app_key)"
    fi

    local reverb_name reverb_value
    for reverb_name in REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET; do
        reverb_value="$(env_value .env.backend "$reverb_name")"
        if is_placeholder "$reverb_value"; then
            set_env_value .env.backend "$reverb_name" "$(random_hex)"
        fi
    done

    [ "$(env_value .env POSTGRES_DB)" = "$(env_value .env.backend DB_DATABASE)" ] \
        || die "POSTGRES_DB and DB_DATABASE must match"
    [ "$(env_value .env POSTGRES_USER)" = "$(env_value .env.backend DB_USERNAME)" ] \
        || die "POSTGRES_USER and DB_USERNAME must match"

    require_value .env POSTGRES_DB
    require_value .env POSTGRES_USER
    require_value .env POSTGRES_PASSWORD
    require_value .env MEILI_MASTER_KEY
    require_value .env.backend APP_KEY
    require_value .env.backend DB_PASSWORD
    require_value .env.backend MEILISEARCH_KEY
    require_value .env.backend MATCHING_SERVICE_API_KEY
    require_value .env.matching MATCHING_SERVICE_API_KEY
}

printf '=== MIS Project Setup ===\n\n'
printf '[1/5] Preparing local environment files...\n'
prepare_environment

if [ "$ENV_ONLY" = true ]; then
    printf '\nEnvironment files are ready. Docker was not started.\n'
    exit 0
fi

if docker info >/dev/null 2>&1; then
    printf '[OK] Docker is accessible\n'
else
    printf '[ERROR] Docker is not accessible. Start Docker and run this script again.\n' >&2
    exit 1
fi

printf '[2/5] Building Docker images...\n'
make build

printf '[3/5] Starting services...\n'
make up
bash wait-for-services.sh postgres redis meilisearch matching frontend

printf '[4/5] Installing application dependencies...\n'
make composer-install
make npm-install
bash wait-for-services.sh backend

printf '[5/5] Preparing the database and storage...\n'
make storage
bash wait-for-services.sh nginx

printf '\n=== Setup complete ===\n'
printf '  Laravel:  http://localhost/up\n'
printf '  Matching: http://localhost:8000/api/v1/health\n'
printf '  Frontend: http://localhost:3000\n'
printf '  Mailpit:  http://localhost:8025\n'
printf '  Meili:    http://localhost:7700\n'
printf '\nRun "make logs" to see service logs.\n'
