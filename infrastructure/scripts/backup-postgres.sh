#!/usr/bin/env bash
set -euo pipefail

# Directory paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/infrastructure/backups/postgres}"

# Database parameters
POSTGRES_USER="${POSTGRES_USER:-wiz_user}"
POSTGRES_DB="${POSTGRES_DB:-wiz_video}"
COMPOSE_SERVICE="${COMPOSE_SERVICE:-postgres}"

# Create backup directory if missing
mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/wiz_postgres_${TIMESTAMP}.sql.gz"

echo "=========================================="
echo "Starting PostgreSQL database backup"
echo "Database:  ${POSTGRES_DB}"
echo "User:      ${POSTGRES_USER}"
echo "Target:    ${BACKUP_FILE}"
echo "=========================================="

# Execute pg_dump via docker compose and compress with gzip
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    docker compose -f "${REPO_ROOT}/docker-compose.yml" exec -T "${COMPOSE_SERVICE}" \
        pg_dump -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" | gzip > "${BACKUP_FILE}"
elif docker ps --format '{{.Names}}' | grep -q "^wiz_postgres$"; then
    docker exec -t wiz_postgres \
        pg_dump -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" | gzip > "${BACKUP_FILE}"
else
    echo "ERROR: Unable to locate docker compose or running wiz_postgres container." >&2
    exit 1
fi

# Validate output exists and is non-empty
if [[ ! -s "${BACKUP_FILE}" ]]; then
    echo "ERROR: Backup file ${BACKUP_FILE} was not created or is empty." >&2
    rm -f "${BACKUP_FILE}"
    exit 1
fi

FILE_SIZE="$(du -h "${BACKUP_FILE}" | cut -f1)"
echo "SUCCESS: PostgreSQL backup completed successfully!"
echo "File: ${BACKUP_FILE} (${FILE_SIZE})"
echo "=========================================="
