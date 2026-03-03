#!/usr/bin/env bash
# deploy.sh – Kopiuje katalog projektu na serwer testowy przez SSH (rsync).
#
# Użycie:
#   1. cp deploy.env.example deploy.env   # uzupełnij dane serwera
#   2. chmod +x deploy.sh
#   3. ./deploy.sh

set -euo pipefail

# ---------------------------------------------------------------------------
# Wczytanie konfiguracji
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_ENV="${SCRIPT_DIR}/deploy.env"

if [[ ! -f "${DEPLOY_ENV}" ]]; then
    echo "BŁĄD: Brak pliku konfiguracyjnego deploy.env"
    echo "      Skopiuj szablon i uzupełnij dane:"
    echo "      cp deploy.env.example deploy.env"
    exit 1
fi

# shellcheck source=/dev/null
source "${DEPLOY_ENV}"

DEPLOY_HOST="${DEPLOY_HOST:?Ustaw DEPLOY_HOST w deploy.env}"
DEPLOY_USER="${DEPLOY_USER:?Ustaw DEPLOY_USER w deploy.env}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_PATH="${DEPLOY_PATH:?Ustaw DEPLOY_PATH w deploy.env}"

# ---------------------------------------------------------------------------
# Budowanie opcji SSH
# ---------------------------------------------------------------------------
SSH_OPTS="-p ${DEPLOY_PORT} -o StrictHostKeyChecking=yes"
if [[ -n "${DEPLOY_SSH_KEY:-}" ]]; then
    SSH_OPTS="${SSH_OPTS} -i \"${DEPLOY_SSH_KEY}\""
fi

# ---------------------------------------------------------------------------
# Synchronizacja przez rsync
# ---------------------------------------------------------------------------
echo "=========================================="
echo " Wdrażanie na serwer testowy"
echo " ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"
echo "=========================================="

rsync -avz --progress \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='deploy.env' \
    --exclude='logs/' \
    --exclude='cache/' \
    --exclude='temp/' \
    --exclude='volumes/' \
    --exclude='*.log' \
    --exclude='*.tmp' \
    --exclude='*.bak' \
    --exclude='*.swp' \
    -e "ssh ${SSH_OPTS}" \
    "${SCRIPT_DIR}/" \
    "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"

echo ""
echo "✅ Gotowe! Katalog skopiowany na ${DEPLOY_HOST}:${DEPLOY_PATH}"
