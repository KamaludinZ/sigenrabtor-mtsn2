#!/usr/bin/env bash
# Redeploy aman SIRABTOR di server biasa (Docker Compose tanpa Coolify). Jalankan dari mana saja:
#   ./docker/redeploy.sh              tarik kode terbaru (git pull --ff-only), bangun image, cadangkan, ganti kontainer
#   ./docker/redeploy.sh --tanpa-git  sama, tanpa git pull (kode sudah diperbarui manual)
#   ./docker/redeploy.sh --kembali    jalankan lagi image sebelumnya (migrasi yang sudah jalan TIDAK dibatalkan)
# Urutan: image baru dibangun selagi versi lama tetap melayani → cadangan dari kontainer lama → kontainer diganti
# (entrypoint memeriksa APP_KEY & volume, mencadangkan lagi bila ada migrasi, lalu migrasi) → tunggu sehat.
# Panduan: docs/deploy.md bagian 6.
set -euo pipefail

cd "$(dirname "$0")/.."
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-sirabtor}"
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml:docker-compose.vps.yml}"

catat() { echo "[redeploy] $*"; }
dc() { docker compose "$@"; }

tunggu_sehat() {
    local batas="${SIRABTOR_TUNGGU_SEHAT_DETIK:-900}" mulai id status
    mulai=$(date +%s)
    while true; do
        id="$(dc ps -q app)"
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$id" 2>/dev/null || echo hilang)"
        case "$status" in
            healthy) catat "Aplikasi sehat."; return 0 ;;
            unhealthy|exited|dead|hilang)
                dc logs --tail 80 app || true
                catat "Kontainer app $status. Data tidak diubah bila pesan di atas berasal dari pengaman deploy."
                catat "Kembali ke versi sebelumnya: ./docker/redeploy.sh --kembali"
                return 1 ;;
        esac
        if (( $(date +%s) - mulai >= batas )); then
            catat "Belum sehat setelah ${batas} detik — periksa: docker compose logs app"
            return 1
        fi
        sleep 5
    done
}

[[ -f .env ]] || { catat ".env tidak ada — salin docker/env.produksi.contoh ke .env lalu isi APP_KEY, APP_URL, DB_PASSWORD."; exit 1; }
dc config --quiet

if [[ "${1:-}" == "--kembali" ]]; then
    docker image inspect sirabtor:sebelumnya >/dev/null 2>&1 || { catat "Tidak ada image sirabtor:sebelumnya."; exit 1; }
    docker tag sirabtor:sebelumnya sirabtor:latest
    dc up -d --no-build app
    tunggu_sehat
    catat "Image sebelumnya berjalan. Bila rilis baru sudah menjalankan migrasi, pulihkan cadangan pra-migrasi (docs/deploy.md bagian 8)."
    exit 0
fi

if [[ "${1:-}" != "--tanpa-git" ]]; then
    git pull --ff-only
fi

# 1. Bangun image baru selagi versi lama tetap melayani pengguna; image lama disimpan untuk --kembali.
if docker image inspect sirabtor:latest >/dev/null 2>&1; then
    docker tag sirabtor:latest sirabtor:sebelumnya
fi
SOURCE_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || true)" dc build app

# 2. Cadangan dari kontainer yang sedang berjalan (versi lama tanpa perintah ini dilewati — entrypoint rilis baru tetap
#    mencadangkan sebelum migrasi).
if [[ -n "$(dc ps -q --status running app)" ]]; then
    dc exec -T -u www-data app php artisan sigenrabtor:cadangan --alasan=pra-redeploy \
        || catat "Cadangan pra-redeploy dilewati (versi lama belum punya perintah cadangan)."
fi

# 3. Ganti kontainer (database tidak dibuat ulang bila konfigurasinya tetap) lalu tunggu sehat.
dc up -d
tunggu_sehat
