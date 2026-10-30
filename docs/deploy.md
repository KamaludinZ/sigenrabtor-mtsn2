# Deploy SIRABTOR (Docker, Coolify, server biasa)

Panduan memasang SIRABTOR di server produksi memakai Docker — lewat **Coolify** (Docker Compose atau Dockerfile) maupun
server Linux biasa. Semua berkas ada di akar repositori:

| Berkas | Isi |
| --- | --- |
| `Dockerfile` | Image produksi multi-tahap: dependensi Composer tanpa paket dev → aset Vite/Tailwind → runtime PHP 8.4-FPM. |
| `docker/` | `entrypoint.sh` (persiapan saat kontainer mulai), `nginx/default.conf`, `php/php.ini`, `php/www.conf`, `supervisord.conf`, `env.produksi.contoh` (contoh variabel lingkungan). |
| `docker-compose.prod.yml` | Aplikasi + PostgreSQL 18 (siap Coolify; tanpa port host). |
| `docker-compose.vps.yml` | Tambahan untuk server biasa: buka port aplikasi di `127.0.0.1:8080`. |
| `docker-compose.ollama.yml` | Opsional: AI lokal Ollama di server yang sama. |
| `docker-compose.yml` | **Hanya pengembangan** (database lokal port 5434) — jangan dipakai di server. |

## 1. Isi image

Satu kontainer `app` menjalankan (lewat supervisord):

- **nginx** (port 8080) + **PHP-FPM 8.4** (pdo_pgsql, gd, zip, intl, bcmath, opcache, pcntl, exif);
- **pekerja antrean** (`queue:work`, koneksi `database`) dan **penjadwal** (`schedule:work`);
- mesin dokumen yang dipanggil aplikasi:
  - **Chromium** headless → Unduh PDF set dokumen (`SIRABTOR_PDF_PERENDER=chrome`, `SIRABTOR_CHROME=/usr/bin/chromium`);
  - **LibreOffice** (calc & writer, tanpa GUI) + locale `id_ID.UTF-8` → PDF RAB & Monitoring (angka "6.000.000");
  - **poppler-utils** (`pdfunite`, `pdfinfo`) → gabung PDF buku;
  - fon Liberation (pengganti Times New Roman/Arial), Carlito/Caladea (Calibri/Cambria), DejaVu.

Batas yang sudah diselaraskan: unggahan hingga 20 MB (berkas terbesar aplikasi 10 MB), waktu permintaan 360 detik
(nginx `fastcgi_read_timeout`, PHP `max_execution_time`, FPM `request_terminate_timeout`) — lebih lama dari batas waktu
penyedia AI terpanjang yang bisa diatur admin (300 detik) dan pembuatan PDF (90 detik).

Saat kontainer mulai, `docker/entrypoint.sh`:

1. menolak berjalan bila `APP_KEY` kosong;
2. membuat struktur folder `storage/` (volume baru masih kosong) dan memberi pemilik `www-data`;
3. menunggu database lalu menjalankan `php artisan migrate --force` (matikan dengan `SIRABTOR_MIGRASI=0`);
4. membersihkan view lama, lalu `php artisan optimize` (cache konfigurasi, rute, event, view);
5. menjalankan supervisord.

Perintah sekali jalan (`docker run --rm sirabtor php artisan …`) melewati langkah di atas.

## 2. Data yang harus persisten — baca dulu

| Volume | Isi | Cadangkan |
| --- | --- | --- |
| `sirabtor_storage` → `/var/www/html/storage` | **Data aplikasi saat ini**: kegiatan, RAB, pengaturan, akun, riwayat (berkas JSON di `storage/app/private/stub/`), unggahan (logo, cover, master KAK/TOR, arsip final PDF), PDF buku, view terkompilasi. | Wajib |
| `sirabtor_pgdata` → PostgreSQL | Sesi, cache, antrean, dan skema database lengkap. | Wajib |

Status aplikasi saat ini (v1.0.0): penyimpanan data masih memakai repositori **tiruan** (berkas JSON di volume storage);
padanan PostgreSQL (`App\Repositories\Eloquent*`) dan perintah pemindahan `sigenrabtor:pindah-*` sudah ada, tetapi
binding di `AppServiceProvider` belum dialihkan. Akibatnya:

- volume storage **adalah** basis data aplikasi — jangan pernah dihapus, dan cadangkan bersama database;
- instalasi baru menampilkan **data contoh fiktif** (enam akun `@example.com`, tiga contoh kegiatan, master kode, pejabat
  contoh, dsb.). Setelah admin pertama bisa masuk: ganti profil madrasah & pejabat, nonaktifkan akun contoh, dan hapus/
  ganti data contoh sebelum dipakai sungguhan;
- jalankan **satu replika** kontainer `app` saja (berkas data tidak dirancang ditulis dua kontainer sekaligus).

`APP_KEY` juga termasuk data: kunci API penyedia AI tersimpan terenkripsi dengannya. Simpan nilainya di tempat aman dan
**jangan diganti** setelah aplikasi dipakai — bila berubah, kunci API harus diisi ulang di Pengaturan AI dan semua sesi
keluar.

## 3. Variabel lingkungan

Contoh lengkap beserta penjelasan: `docker/env.produksi.contoh`. Yang wajib:

| Variabel | Nilai |
| --- | --- |
| `APP_KEY` | `base64:…` — buat sekali: `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | Alamat publik https persis seperti dibuka pengguna, mis. `https://sirabtor.mtsn2kotamalang.sch.id` |
| `DB_PASSWORD` | Sandi PostgreSQL acak yang kuat (dipakai layanan `db` dan aplikasi) |

Nilai bawaan `docker-compose.prod.yml` sudah aman untuk produksi: `APP_ENV=production`, `APP_DEBUG=false`,
`SIRABTOR_PROKSI_TEPERCAYA=*` (percaya header X-Forwarded-* dari proksi TLS — Coolify/Traefik/nginx — agar URL & cookie
https dan IP asli pengguna terbaca; isi daftar alamat/CIDR proksi bila kontainer bisa dijangkau langsung dari internet),
`SESSION_SECURE_COOKIE=true`, sesi/cache/antrean di database, `LOG_CHANNEL=stderr`, `SIRABTOR_WAJIB_MASUK=true`,
`SIRABTOR_MODE_DEMO=false` (tanpa persona demo dan tanpa masuk tanpa sandi), `PHP_FPM_MAX_CHILDREN=10` (±60–100 MB per
proses; naikkan bila RAM server besar).

## 4. Coolify

### 4a. Docker Compose (disarankan — aplikasi + database sekaligus)

1. **New Resource → Public/Private Repository** → pilih repositori ini, cabang `main`.
2. **Build Pack: Docker Compose**, *Docker Compose Location* `/docker-compose.prod.yml`.
3. Tab **Environment Variables**: isi `APP_KEY`, `APP_URL`, `DB_PASSWORD` (Coolify menandainya wajib). Variabel lain
   boleh dibiarkan.
4. Layanan **app** → *Domains*: `https://domain-anda:8080` (port 8080 = port kontainer, bukan port publik). Atau biarkan
   Coolify membuat domain dari variabel `SERVICE_FQDN_APP_8080`, lalu samakan `APP_URL` dengan domain itu.
5. **Deploy**. Build pertama ±10–15 menit (Chromium & LibreOffice). Status sehat setelah `/up` menjawab.
6. Buat admin pertama (bagian 6) lewat **Terminal** kontainer `app`.

Volume `sirabtor_storage` & `sirabtor_pgdata` dibuat otomatis dan tetap ada antar-deploy. Aktifkan **Backups** untuk
database di Coolify dan cadangkan volume storage (bagian 9).

### 4b. Dockerfile saja + database PostgreSQL Coolify

1. Buat resource **PostgreSQL** (versi 18) di Coolify; catat host internal, nama database, pengguna, sandi.
2. **New Resource → repositori ini → Build Pack: Dockerfile**. *Ports Exposes* `8080`.
3. Environment: semua variabel di `docker/env.produksi.contoh`, dengan `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/
   `DB_PASSWORD` dari resource PostgreSQL. Jika aplikasi & database berada di jaringan berbeda, aktifkan *Connect To
   Predefined Network* pada keduanya.
4. **Storages → Add Volume**: tujuan `/var/www/html/storage` (wajib — tanpa ini data hilang saat deploy ulang).
5. *Health check* path `/up`, port 8080. Deploy, lalu buat admin pertama.

## 5. Server Linux biasa (Docker Compose)

```bash
git clone <repositori> sirabtor && cd sirabtor
cp docker/env.produksi.contoh .env.produksi        # isi APP_KEY, APP_URL, DB_PASSWORD
docker compose --env-file .env.produksi -f docker-compose.prod.yml -f docker-compose.vps.yml up -d --build
docker compose --env-file .env.produksi -f docker-compose.prod.yml logs -f app   # tunggu "[sirabtor] Siap: …"
```

Aplikasi terbuka di `http://127.0.0.1:8080` server. Pasang proksi TLS di depannya, misalnya Caddy:

```
sirabtor.mtsn2kotamalang.sch.id {
    reverse_proxy 127.0.0.1:8080
}
```

atau nginx (sertifikat dari certbot):

```nginx
server {
    listen 443 ssl;
    server_name sirabtor.mtsn2kotamalang.sch.id;
    # ssl_certificate …; ssl_certificate_key …;
    client_max_body_size 25m;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Host $host;
        proxy_read_timeout 360s;
    }
}
```

Hosting tanpa Docker (VPS dengan PHP-FPM sendiri) memakai langkah yang sama dengan `docker/entrypoint.sh`: PHP 8.4 +
ekstensi di atas, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, paket `chromium`,
`libreoffice-calc-nogui`, `poppler-utils`, locale `id_ID.UTF-8`, `php artisan migrate --force && php artisan optimize`,
pekerja `queue:work` & `schedule:work` (systemd/supervisor), konfigurasi nginx dari `docker/nginx/default.conf`. Hosting
bersama (shared hosting) tidak didukung karena butuh Chromium & LibreOffice.

## 6. Admin pertama

Dengan `SIRABTOR_MODE_DEMO=false` akun hanya bisa masuk dengan sandi. Atur sandi dari terminal kontainer:

```bash
# Akun admin baru (sandi diketik tersembunyi, min. 8 karakter, huruf & angka, tidak memuat nama/surel):
php artisan sigenrabtor:atur-sandi admin@mtsn2kotamalang.sch.id --buat-admin --nama="Nama Admin"
# Akun yang sudah ada (mis. admin contoh) / sandi acak yang ditampilkan sekali:
php artisan sigenrabtor:atur-sandi puskom@example.com --acak
```

Di Coolify: *Terminal* layanan `app`; di server biasa: `docker compose -f docker-compose.prod.yml exec app php artisan …`.
Setelah masuk: **Pengaturan › Leading & Akun** untuk akun lain (sandi akun lain diatur ulang dari formulir akun),
**Pengaturan › Profil & Pejabat**, **Tahun Anggaran**, **Master Kode** (unggah Excel), lalu **Pengaturan AI**.

## 7. Integrasi AI

Penyedia AI diatur admin di layar **Pengaturan › Pengaturan AI** (bukan lewat variabel lingkungan) dan tersimpan di
volume storage; kunci API dienkripsi dengan `APP_KEY`. Tanpa penyedia aktif aplikasi tetap berjalan dengan saran tiruan.
Kontainer hanya butuh akses HTTPS keluar (sertifikat CA sudah terpasang).

**Penyedia cloud** (Claude, OpenAI, Gemini): tab *Penyedia* → kartu penyedia → isi model & kunci API → jadikan penyedia
aktif (opsional cadangan). Alamat API kosong = alamat resmi. Pastikan firewall server mengizinkan HTTPS keluar ke
`api.anthropic.com`, `api.openai.com`, atau `generativelanguage.googleapis.com`.

**Ollama lokal** (data kegiatan tidak keluar dari server):

```bash
docker compose -f docker-compose.prod.yml -f docker-compose.ollama.yml up -d
docker compose -f docker-compose.prod.yml -f docker-compose.ollama.yml exec ollama ollama pull qwen2.5:7b
```

Lalu di Pengaturan AI → kartu Ollama → *Ubah*: alamat server `http://ollama:11434`, model `qwen2.5:7b` (ketik nama model
yang sudah ditarik — daftar di tab *Model* masih daftar contoh), batas waktu 120–300 detik untuk CPU, jadikan penyedia aktif. Model 7B butuh RAM ±8 GB tanpa GPU; untuk GPU
NVIDIA aktifkan blok `deploy` di `docker-compose.ollama.yml`. Di Coolify, Ollama bisa dipasang sebagai *Service* satu
klik — isi alamatnya dengan nama host internal layanan itu (aktifkan *Connect To Predefined Network*), atau gabungkan
`docker-compose.ollama.yml` ke berkas compose. Ollama yang berjalan langsung di host: tambahkan
`extra_hosts: ["host.docker.internal:host-gateway"]` pada layanan `app` dan pakai `http://host.docker.internal:11434`
(Ollama di host harus mendengarkan `0.0.0.0`).

Hal lain yang sudah tersedia di layar Pengaturan AI: model & parameter, template prompt per bagian, batas permintaan
harian per akun, penyedia cadangan otomatis (failover), dan simulasi kegagalan untuk menguji pesan galat. Uji
alurnya: buka satu kegiatan → tab **Kalimat KAK** → *Buat dengan AI*; riwayatnya tercatat di **Riwayat Saran AI**.
Batas waktu permintaan server (360 detik) sudah lebih lama dari batas waktu penyedia terpanjang (300 detik).

## 8. Pemeriksaan sesudah deploy

1. `https://domain/up` → 200; log kontainer memuat `[sirabtor] Siap: Laravel Framework … · Chromium … · LibreOffice …`.
2. Halaman Masuk tampil tanpa kartu persona demo; masuk dengan admin dari bagian 6.
3. Kegiatan → Pratinjau → **Unduh PDF** (Chromium) dan RAB → **Unduh PDF** (LibreOffice).
4. Buku Dokumen → **Gabung PDF** (poppler).
5. Pengaturan AI → penyedia aktif → Kalimat KAK → *Buat dengan AI*.
6. Unggah logo di Profil & Pejabat (unggahan & volume storage).

## 9. Pembaruan, cadangan, pemulihan

- **Pembaruan**: Coolify → *Redeploy* (atau `git pull && docker compose … up -d --build`). Migrasi berjalan otomatis;
  volume tetap.
- **Cadangan database**: `docker compose -f docker-compose.prod.yml exec db pg_dump -U sigenrabtor -Fc sigenrabtor > sirabtor-$(date +%F).dump`
  (atau Backups Coolify).
- **Cadangan storage**: `docker run --rm -v <proyek>_sirabtor_storage:/data -v "$PWD":/cadangan debian:bookworm-slim tar czf /cadangan/storage-$(date +%F).tgz -C /data .`
  (nama volume lengkap terlihat di `docker volume ls`).
- **Pemulihan**: `pg_restore --clean -d sigenrabtor` dari berkas dump, dan ekstrak arsip storage ke volume yang sama
  sebelum kontainer `app` dijalankan.

## 10. Pemecahan masalah

| Gejala | Penyebab & jalan keluar |
| --- | --- |
| Kontainer berhenti: "APP_KEY belum diisi" | Isi `APP_KEY` (bagian 3). |
| "Database belum bisa dipakai setelah 60 detik" | Periksa `DB_*`; database eksternal lambat → naikkan `SIRABTOR_TUNGGU_DB_DETIK`. |
| Tautan/aset memakai `http://`, formulir gagal 419 | `APP_URL` harus https & sama dengan domain; `SIRABTOR_PROKSI_TEPERCAYA` terisi; jam server benar. |
| Unduh PDF gagal "Chrome/Chromium tidak ditemukan" | Image bukan dari `Dockerfile` ini, atau `SIRABTOR_CHROME` salah. Di host tanpa Docker: pasang Chromium (bukan versi snap untuk pengguna `www-data`). |
| Angka PDF RAB memakai koma Inggris | Locale `id_ID.UTF-8` belum dibuat (`locale -a`). |
| Saran AI "tidak terhubung"/"waktu habis" | Alamat Ollama harus nama host di jaringan Docker (`http://ollama:11434`), bukan `localhost`; model besar di CPU butuh batas waktu lebih lama. |
| Kunci API AI "tidak bisa dibuka" setelah pindah server | `APP_KEY` berubah — kembalikan nilai lama atau isi ulang kunci API. |
| Data kembali ke contoh setelah deploy | Volume `/var/www/html/storage` tidak terpasang (Dockerfile build pack tanpa *Storages*). |

## Catatan pengujian konfigurasi

Konfigurasi ini disiapkan di mesin pengembangan yang tidak punya akses ke Docker daemon, sehingga **image belum pernah
dibangun dan dijalankan di sana** — lakukan deploy uji (bagian 8) sebelum dipakai. Yang sudah diperiksa: tag image dasar
tersedia di Docker Hub, `nginx -t` atas `docker/nginx/default.conf`, parser supervisord atas `docker/supervisord.conf`,
sintaks `entrypoint.sh`, YAML ketiga berkas compose, `php artisan optimize` (cache konfigurasi/rute/view) dengan
`APP_ENV=production`, perintah `sigenrabtor:atur-sandi`, dan proksi tepercaya (tes `DeployTest`, `SandiKonsolTest`).
