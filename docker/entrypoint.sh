#!/usr/bin/env bash
# Entrypoint kontainer SIRABTOR: periksa konfigurasi wajib, siapkan folder storage (volume persisten), tunggu database,
# lalu — di dalam kunci berkas agar dua kontainer tidak bermigrasi bersamaan — periksa APP_KEY & pasangan volume/database,
# buat cadangan bila ada migrasi baru atau rilis berganti, dan migrasi (`php artisan sigenrabtor:siapkan-deploy`),
# cache konfigurasi/rute/view, lalu serahkan ke perintah kontainer (supervisord).
# Variabel: SIRABTOR_MIGRASI (bawaan 1), SIRABTOR_TUNGGU_DB_DETIK (bawaan 60), PHP_FPM_MAX_CHILDREN (bawaan 10),
# SIRABTOR_CADANGAN_PRA_MIGRASI / SIRABTOR_IZINKAN_* (lihat docs/deploy.md).
set -euo pipefail

cd /var/www/html

catat() { echo "[sirabtor] $*"; }

# Perintah sekali jalan (mis. `docker run --rm <image> php artisan key:generate --show`) tidak melewati persiapan.
if [[ "${1:-}" != "supervisord" ]]; then
    exec "$@"
fi

export PHP_FPM_MAX_CHILDREN="${PHP_FPM_MAX_CHILDREN:-10}"

if [[ -z "${APP_KEY:-}" ]]; then
    catat "APP_KEY belum diisi. Buat sekali dengan: echo \"base64:\$(openssl rand -base64 32)\""
    catat "lalu simpan nilainya di variabel lingkungan APP_KEY (jangan pernah diganti: kunci API AI tersimpan terenkripsi dengannya)."
    exit 1
fi

# Volume storage baru masih kosong: buat struktur folder Laravel & beri pemilik www-data.
mkdir -p storage/app/private/deploy storage/app/private/cadangan storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

sebagai_www() { su -s /bin/bash www-data -c "$*"; }

if [[ "${SIRABTOR_MIGRASI:-1}" == "1" ]]; then
    batas="${SIRABTOR_TUNGGU_DB_DETIK:-60}"
    mulai=$(date +%s)
    until pg_isready -q -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-sigenrabtor}" -d "${DB_DATABASE:-sigenrabtor}"; do
        if (( $(date +%s) - mulai >= batas )); then
            catat "Database belum bisa dipakai setelah ${batas} detik — periksa DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME."
            exit 1
        fi
        catat "Menunggu database siap…"
        sleep 3
    done

    # Satu kontainer pada satu waktu (kunci di volume bersama): pemeriksaan, cadangan, dan migrasi.
    if ! sebagai_www "flock -w 900 storage/app/private/deploy/.kunci php artisan sigenrabtor:siapkan-deploy --no-interaction"; then
        catat "Persiapan deploy dihentikan — data tidak diubah. Baca pesan di atas (docs/deploy.md bagian 'Redeploy aman')."
        exit 1
    fi
fi

sebagai_www "php artisan view:clear >/dev/null"
sebagai_www "php artisan optimize"

catat "Siap: $(php artisan --version) · rilis $(cat /etc/sirabtor-rilis 2>/dev/null || echo '?') · Chromium: $(chromium --version 2>/dev/null | head -n1 || echo 'tidak ada') · LibreOffice: $(soffice --version 2>/dev/null | head -n1 || echo 'tidak ada')"

exec "$@"
