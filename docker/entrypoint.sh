#!/usr/bin/env bash
# Entrypoint kontainer SIRABTOR: periksa konfigurasi wajib, siapkan folder storage (volume persisten), tunggu database,
# jalankan migrasi, cache konfigurasi/rute/view, lalu serahkan ke perintah kontainer (supervisord).
# Variabel: SIRABTOR_MIGRASI (bawaan 1), SIRABTOR_TUNGGU_DB_DETIK (bawaan 60), PHP_FPM_MAX_CHILDREN (bawaan 10).
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
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/testing storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

sebagai_www() { su -s /bin/bash www-data -c "$*"; }

if [[ "${SIRABTOR_MIGRASI:-1}" == "1" ]]; then
    batas="${SIRABTOR_TUNGGU_DB_DETIK:-60}"
    mulai=$(date +%s)
    until sebagai_www "php artisan migrate --force --no-interaction"; do
        if (( $(date +%s) - mulai >= batas )); then
            catat "Database belum bisa dipakai setelah ${batas} detik — periksa DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD."
            exit 1
        fi
        catat "Menunggu database siap…"
        sleep 3
    done
fi

sebagai_www "php artisan view:clear >/dev/null"
sebagai_www "php artisan optimize"

catat "Siap: $(php artisan --version) · Chromium: $(chromium --version 2>/dev/null | head -n1 || echo 'tidak ada') · LibreOffice: $(soffice --version 2>/dev/null | head -n1 || echo 'tidak ada')"

exec "$@"
