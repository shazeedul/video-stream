#!/usr/bin/env bash
set -euo pipefail

# Directory paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/infrastructure/backups/minio}"

# MinIO connection defaults
MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
MINIO_HOST_ENDPOINT="${MINIO_HOST_ENDPOINT:-http://localhost:9000}"
MINIO_ROOT_USER="${MINIO_ROOT_USER:-minioadmin}"
MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:-minioadmin}"

BUCKETS=("videos-original" "videos-processed" "thumbnails")

mkdir -p "${BACKUP_DIR}"

echo "=========================================="
echo "Starting MinIO object storage backup"
echo "Target Dir: ${BACKUP_DIR}"
echo "Buckets:    ${BUCKETS[*]}"
echo "=========================================="

if command -v mc >/dev/null 2>&1; then
    echo "Using local mc binary..."
    mc alias set localminio "${MINIO_HOST_ENDPOINT}" "${MINIO_ROOT_USER}" "${MINIO_ROOT_PASSWORD}" --api S3v4
    for bucket in "${BUCKETS[@]}"; do
        echo "Mirroring bucket '${bucket}'..."
        mkdir -p "${BACKUP_DIR}/${bucket}"
        mc mirror --overwrite "localminio/${bucket}" "${BACKUP_DIR}/${bucket}"
    done
elif command -v docker >/dev/null 2>&1; then
    echo "Running MinIO client via docker container..."
    NETWORK_NAME="$(docker inspect wiz_minio --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}' 2>/dev/null || echo "wiz-video-platform_default")"
    
    for bucket in "${BUCKETS[@]}"; do
        echo "Mirroring bucket '${bucket}' via container..."
        mkdir -p "${BACKUP_DIR}/${bucket}"
        docker run --rm \
            --network "${NETWORK_NAME}" \
            -v "${BACKUP_DIR}:/backup" \
            minio/minio:latest \
            sh -c "mc alias set minio_source ${MINIO_ENDPOINT} ${MINIO_ROOT_USER} ${MINIO_ROOT_PASSWORD} >/dev/null 2>&1 && mc mirror --overwrite minio_source/${bucket} /backup/${bucket}"
    done
else
    echo "ERROR: Neither 'mc' CLI nor 'docker' is available on this system." >&2
    exit 1
fi

echo "SUCCESS: MinIO backup completed successfully to ${BACKUP_DIR}!"
echo "=========================================="
