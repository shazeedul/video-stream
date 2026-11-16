#!/usr/bin/env bash
set -euo pipefail

# Directory paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Database parameters
POSTGRES_USER="${POSTGRES_USER:-wiz_user}"
POSTGRES_DB="${POSTGRES_DB:-wiz_video}"
COMPOSE_SERVICE="${COMPOSE_SERVICE:-postgres}"

FORCE=false
DUMP_FILE=""

for arg in "$@"; do
    if [[ "${arg}" == "--force" || "${arg}" == "-f" ]]; then
        FORCE=true
    elif [[ -z "${DUMP_FILE}" ]]; then
        DUMP_FILE="${arg}"
    fi
done

if [[ -z "${DUMP_FILE}" ]]; then
    echo "Usage: $0 <path_to_dump.sql.gz> [--force]" >&2
    exit 1
fi

if [[ ! -f "${DUMP_FILE}" ]]; then
    echo "ERROR: Backup dump file '${DUMP_FILE}' does not exist or is not a regular file." >&2
    exit 1
fi

if [[ ! -s "${DUMP_FILE}" ]]; then
    echo "ERROR: Backup dump file '${DUMP_FILE}' is empty (0 bytes)." >&2
    exit 1
fi

echo "=========================================="
echo "Starting PostgreSQL database restore"
echo "Database:    ${POSTGRES_DB}"
echo "User:        ${POSTGRES_USER}"
echo "Source Dump: ${DUMP_FILE}"
echo "=========================================="

if [[ "${FORCE}" != "true" ]]; then
    read -r -p "CAUTION: This will DROP the public schema and overwrite '${POSTGRES_DB}'. Continue? [y/N]: " CONFIRMATION
    if [[ "${CONFIRMATION}" != "y" && "${CONFIRMATION}" != "Y" ]]; then
        echo "Restore aborted by user."
        exit 0
    fi
fi

DROP_REBUILD_SCHEMA_SQL="DROP SCHEMA public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO ${POSTGRES_USER}; GRANT ALL ON SCHEMA public TO public;"

echo "Rebuilding public schema..."
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    docker compose -f "${REPO_ROOT}/docker-compose.yml" exec -T "${COMPOSE_SERVICE}" \
        psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -c "${DROP_REBUILD_SCHEMA_SQL}"

    echo "Importing dump archive..."
    gunzip -c "${DUMP_FILE}" | docker compose -f "${REPO_ROOT}/docker-compose.yml" exec -T "${COMPOSE_SERVICE}" \
        psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}"
elif docker ps --format '{{.Names}}' | grep -q "^wiz_postgres$"; then
    docker exec -i wiz_postgres \
        psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -c "${DROP_REBUILD_SCHEMA_SQL}"

    echo "Importing dump archive..."
    gunzip -c "${DUMP_FILE}" | docker exec -i wiz_postgres \
        psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}"
else
    echo "ERROR: Unable to locate docker compose or running wiz_postgres container." >&2
    exit 1
fi

echo "SUCCESS: PostgreSQL database restored successfully from ${DUMP_FILE}!"
echo "=========================================="
