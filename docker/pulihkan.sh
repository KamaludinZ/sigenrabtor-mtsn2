#!/usr/bin/env bash
# Pulihkan satu set cadangan SIRABTOR (database + data storage) — jalankan DI DALAM kontainer app
# (Coolify: Terminal layanan app; VPS: docker compose exec app sirabtor-pulihkan …):
#   sirabtor-pulihkan                           daftar set cadangan
#   sirabtor-pulihkan 20261101-013000-harian    pulihkan set itu (minta konfirmasi; --ya untuk tanpa tanya)
# Urutan: set disalin ke folder sementara → keadaan sekarang dicadangkan (set "pra-pemulihan", bisa dipulihkan balik)
# → mode pemeliharaan (pengguna melihat 503, antrean & penjadwal berhenti) → pg_restore → data storage → aplikasi aktif.
# Gagal di tengah jalan: aplikasi TETAP dalam mode pemeliharaan agar data setengah pulih tidak dipakai.
set -euo pipefail

cd /var/www/html

www() {
    if [[ "$(id -u)" == "0" ]]; then su -s /bin/bash www-data -c "$*"; else bash -c "$*"; fi
}

if [[ -z "${1:-}" ]]; then
    www "php artisan sigenrabtor:cadangan --daftar"
    exit 0
fi

nama="$(basename "$1")"
asal="storage/app/private/cadangan/${nama}"
[[ -d "$asal" ]] || { echo "Set cadangan ${nama} tidak ada. Daftar: sirabtor-pulihkan"; exit 1; }
[[ -f "$asal/database.dump" || -f "$asal/data.tgz" ]] || { echo "Set ${nama} tidak berisi database.dump maupun data.tgz."; exit 1; }

echo "Memulihkan ${nama}: database & data storage saat ini DIGANTI isi cadangan itu."
if [[ "${2:-}" != "--ya" ]]; then
    read -r -p "Ketik PULIHKAN untuk melanjutkan: " jawab
    [[ "$jawab" == "PULIHKAN" ]] || { echo "Dibatalkan."; exit 1; }
fi

# Salin dulu: cadangan pra-pemulihan bisa membuang set terlama (retensi) — termasuk set yang sedang dipulihkan.
sementara="$(mktemp -d)"
cp -a "$asal/." "$sementara/"
trap 'rm -rf "$sementara"' EXIT

www "php artisan sigenrabtor:cadangan --alasan=pra-pemulihan"
www "php artisan down --retry=60"
echo "Mode pemeliharaan aktif."

if [[ -f "$sementara/database.dump" ]]; then
    PGPASSWORD="${DB_PASSWORD:-}" pg_restore --clean --if-exists --no-owner --no-privileges \
        -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-sigenrabtor}" -d "${DB_DATABASE:-sigenrabtor}" \
        "$sementara/database.dump" \
        || { echo "pg_restore gagal — aplikasi tetap dalam mode pemeliharaan. Perbaiki lalu ulangi, atau pulihkan set pra-pemulihan."; exit 1; }
    echo "Database dipulihkan."
fi
if [[ -f "$sementara/data.tgz" ]]; then
    rm -rf storage/app/private/stub
    tar -xzf "$sementara/data.tgz" -C storage/app \
        || { echo "Ekstrak data gagal — aplikasi tetap dalam mode pemeliharaan."; exit 1; }
    chown -R www-data:www-data storage/app 2>/dev/null || true
    echo "Data storage dipulihkan."
fi

www "php artisan up"
echo "Selesai — ${nama} dipulihkan. Set pra-pemulihan menyimpan keadaan sebelum pemulihan ini."
