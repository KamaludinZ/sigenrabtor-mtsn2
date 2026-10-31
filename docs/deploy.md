# Deploy SIRABTOR (Docker, Coolify, hosting VPS)

Panduan memasang dan **memperbarui (redeploy) dengan aman** SIRABTOR di server produksi memakai `Dockerfile` dan
Docker Compose — lewat **Coolify** maupun **hosting VPS** biasa. Semua berkas ada di akar repositori:

| Berkas | Isi |
| --- | --- |
| `Dockerfile` | Image produksi multi-tahap: dependensi Composer tanpa paket dev → aset Vite/Tailwind → runtime PHP 8.4-FPM. |
| `docker/entrypoint.sh` | Persiapan tiap kontainer mulai: folder storage, tunggu database, **pengaman deploy + cadangan + migrasi**, cache. |
| `docker/redeploy.sh` | Redeploy aman satu perintah untuk VPS (bangun → cadangan → ganti kontainer → tunggu sehat; `--kembali`). |
| `docker/pulihkan.sh` | `sirabtor-pulihkan` di dalam image: pulihkan satu set cadangan (database + data) dengan mode pemeliharaan. |
| `docker/nginx/`, `docker/php/`, `docker/supervisord.conf` | Konfigurasi web server, PHP, dan proses di dalam kontainer. |
| `docker/env.produksi.contoh` | Contoh semua variabel lingkungan beserta penjelasan. |
| `docker-compose.prod.yml` | Aplikasi + PostgreSQL 18 (siap Coolify; tanpa port host). |
| `docker-compose.vps.yml` | Tambahan untuk VPS: nama proyek tetap & port `127.0.0.1:8080`. |
| `docker-compose.ollama.yml` | Opsional: AI lokal Ollama di server yang sama. |
| `docker-compose.yml` | **Hanya pengembangan** (database lokal port 5434) — jangan dipakai di server. |

## 0. Pilih jalur

| Jalur | Cocok untuk | Bagian |
| --- | --- | --- |
| **Coolify — Docker Compose** (disarankan) | Server dengan Coolify; aplikasi + database satu paket, volume dikelola Coolify | 4a |
| Coolify — Dockerfile + PostgreSQL Coolify | Sudah punya resource PostgreSQL di Coolify | 4b |
| **Hosting VPS + Docker Compose** | VPS (Ubuntu/Debian) tanpa Coolify | 5 |

Kebutuhan server: Linux 64-bit, Docker Engine 24+ dengan plugin Compose v2, RAM ≥ 2 GB (≥ 4 GB nyaman; +8 GB bila
memakai Ollama), disk ≥ 20 GB (image ±2,5 GB + data + cadangan). **Shared hosting (cPanel) tidak didukung** —
aplikasi membutuhkan Chromium, LibreOffice, dan proses latar yang tidak tersedia di hosting bersama.

## 1. Isi image

Satu kontainer `app` menjalankan (lewat supervisord):

- **nginx** (port 8080) + **PHP-FPM 8.4** (pdo_pgsql, gd, zip, intl, bcmath, opcache, pcntl, exif);
- **pekerja antrean** (`queue:work`, koneksi `database`) dan **penjadwal** (`schedule:work` — termasuk cadangan harian);
- mesin dokumen: **Chromium** headless (Unduh PDF set dokumen), **LibreOffice** + locale `id_ID.UTF-8` (PDF RAB &
  Monitoring, angka "6.000.000"), **poppler-utils** (gabung PDF buku), fon Liberation/Carlito/Caladea/DejaVu;
- **postgresql-client-18** (dari repositori resmi PGDG) — `pg_dump`/`pg_restore`/`pg_isready` untuk cadangan otomatis.

Batas yang sudah diselaraskan: unggahan hingga 20 MB (berkas terbesar aplikasi 10 MB), waktu permintaan 360 detik
(nginx, PHP, FPM) — lebih lama dari batas waktu penyedia AI terpanjang (300 detik) dan pembuatan PDF (90 detik).

## 2. Data yang harus persisten — baca dulu

| Volume | Isi | Cadangkan |
| --- | --- | --- |
| `sirabtor_storage` → `/var/www/html/storage` | **Data aplikasi saat ini**: kegiatan, RAB, pengaturan, akun, riwayat (berkas JSON di `storage/app/private/stub/`), unggahan (logo, cover, master KAK/TOR, arsip final PDF), PDF buku, **cadangan otomatis** (`storage/app/private/cadangan/`), penanda instalasi (`storage/app/private/deploy/`). | Wajib |
| `sirabtor_pgdata` → PostgreSQL | Sesi, cache, antrean, id instalasi, dan skema database lengkap. | Wajib |

Status aplikasi (v1.0.0): penyimpanan data masih memakai repositori **tiruan** (berkas JSON di volume storage); padanan
PostgreSQL (`App\Repositories\Eloquent*`) dan perintah `sigenrabtor:pindah-*` sudah ada, tetapi binding belum
dialihkan. Akibatnya:

- volume storage **adalah** basis data aplikasi — jangan pernah dihapus, dan cadangkan bersama database;
- instalasi baru menampilkan **data contoh fiktif** (akun `@example.com`, tiga contoh kegiatan, master kode, pejabat
  contoh). Setelah admin pertama bisa masuk: ganti profil madrasah & pejabat, nonaktifkan akun contoh, dan hapus/ganti
  data contoh sebelum dipakai sungguhan;
- jalankan **satu replika** kontainer `app` saja (berkas data tidak dirancang ditulis dua kontainer sekaligus).

`APP_KEY` juga termasuk data: kunci API penyedia AI tersimpan terenkripsi dengannya. Simpan nilainya di tempat aman
(pengelola sandi) dan **jangan diganti** — pengaman deploy akan menghentikan kontainer bila `APP_KEY` berubah (bagian 6).

## 3. Variabel lingkungan

Contoh lengkap beserta penjelasan: `docker/env.produksi.contoh`. Yang wajib:

| Variabel | Nilai |
| --- | --- |
| `APP_KEY` | `base64:…` — buat **sekali**: `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | Alamat publik https persis seperti dibuka pengguna, mis. `https://sirabtor.mtsn2kotamalang.sch.id` |
| `DB_PASSWORD` | Sandi PostgreSQL acak yang kuat (dipakai layanan `db` dan aplikasi) — **jangan diganti** setelah database dibuat (lihat bagian 11) |

Nilai bawaan `docker-compose.prod.yml` sudah aman untuk produksi: `APP_ENV=production`, `APP_DEBUG=false`,
`SIRABTOR_PROKSI_TEPERCAYA=*` (percaya header X-Forwarded-* dari proksi TLS — Coolify/Traefik/nginx/Caddy — agar URL &
cookie https dan IP asli pengguna terbaca; isi daftar alamat/CIDR proksi bila kontainer bisa dijangkau langsung dari
internet), `SESSION_SECURE_COOKIE=true`, sesi/cache/antrean di database, `LOG_CHANNEL=stderr`,
`SIRABTOR_WAJIB_MASUK=true`, `SIRABTOR_MODE_DEMO=false`, `PHP_FPM_MAX_CHILDREN=10`, serta pengaman redeploy:

| Variabel | Bawaan | Arti |
| --- | --- | --- |
| `SIRABTOR_CADANGAN_PRA_MIGRASI` | `true` | Cadangan otomatis sebelum migrasi baru / rilis baru memakai data. |
| `SIRABTOR_CADANGAN_HARIAN` | `true` | Cadangan harian pukul `SIRABTOR_CADANGAN_JAM` (`01:30`, zona `APP_TIMEZONE`). |
| `SIRABTOR_CADANGAN_SIMPAN` | `7` | Jumlah set cadangan terbaru yang disimpan di volume. |
| `SIRABTOR_IZINKAN_GANTI_APP_KEY` | `false` | Hanya untuk penggantian `APP_KEY` yang disengaja — satu kali deploy. |
| `SIRABTOR_IZINKAN_DATA_BARU` | `false` | Hanya saat memulihkan volume/database terpisah dari cadangan — satu kali deploy. |

## 4. Coolify

### 4a. Docker Compose (disarankan — aplikasi + database sekaligus)

1. **New Resource → Public/Private Repository** → pilih repositori ini, cabang `main`.
2. **Build Pack: Docker Compose**, *Docker Compose Location* `/docker-compose.prod.yml`.
3. Tab **Environment Variables**: isi `APP_KEY`, `APP_URL`, `DB_PASSWORD` (Coolify menandainya wajib). Variabel lain
   boleh dibiarkan. Simpan salinan `APP_KEY` & `DB_PASSWORD` di pengelola sandi.
4. Layanan **app** → *Domains*: `https://domain-anda:8080` (8080 = port kontainer, bukan port publik). Atau biarkan
   Coolify membuat domain dari `SERVICE_FQDN_APP_8080`, lalu samakan `APP_URL` dengan domain itu.
5. **Deploy**. Build pertama ±10–15 menit (Chromium, LibreOffice). Sehat setelah `/up` menjawab; log memuat
   `[sirabtor] Instalasi … siap.` dan `[sirabtor] Siap: …`.
6. Buat admin pertama (bagian 7) lewat **Terminal** layanan `app`.

Volume dibuat Coolify dengan awalan id resource dan tetap ada antar-deploy **selama resource tidak dihapus**. Bila suatu
saat menghapus resource, **jangan centang opsi hapus volume** sebelum data dicadangkan ke luar server.

### 4b. Dockerfile saja + database PostgreSQL Coolify

1. Buat resource **PostgreSQL 18** di Coolify; catat host internal, nama database, pengguna, sandi. Aktifkan
   **Backups** bawaan resource itu (cadangan tambahan di luar volume aplikasi).
2. **New Resource → repositori ini → Build Pack: Dockerfile**. *Ports Exposes* `8080`.
3. Environment: semua variabel di `docker/env.produksi.contoh`, dengan `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/
   `DB_PASSWORD` dari resource PostgreSQL. Bila berbeda jaringan, aktifkan *Connect To Predefined Network* di keduanya.
4. **Storages → Add Volume**: tujuan `/var/www/html/storage` — **wajib**; tanpa ini setiap deploy dimulai dari data contoh
   (pengaman deploy menghentikannya sejak deploy kedua, lihat bagian 6).
5. *Health check* path `/up`, port 8080. Deploy, lalu buat admin pertama.

Pada build pack Dockerfile, Coolify bisa menjalankan kontainer baru sebelum yang lama berhenti (*rolling update*).
Migrasi tetap satu per satu (kunci berkas di volume), tetapi selama tumpang-tindih itu dua kontainer bisa menulis berkas
data yang sama — lakukan redeploy di luar jam kerja, atau pakai jalur 4a.

## 5. Hosting VPS dengan Docker Compose

Contoh untuk Ubuntu/Debian. Pasang Docker Engine + plugin Compose dari dokumentasi resmi Docker, lalu:

```bash
git clone <alamat-repositori> /opt/sirabtor && cd /opt/sirabtor
cp docker/env.produksi.contoh .env          # isi APP_KEY, APP_URL, DB_PASSWORD (chmod 600 .env)
cat >> .env <<'CONF'
COMPOSE_PROJECT_NAME=sirabtor
COMPOSE_FILE=docker-compose.prod.yml:docker-compose.vps.yml
CONF
chmod 600 .env
docker compose up -d --build
docker compose logs -f app                  # tunggu "[sirabtor] Siap: …"
```

`.env` ini hanya dibaca Docker Compose di server (tidak ikut ke image — lihat `.dockerignore`). Dua baris
`COMPOSE_*` membuat semua perintah `docker compose …` di folder ini otomatis memakai kedua berkas dan nama proyek yang
sama — nama proyek menentukan nama volume, jadi **jangan diubah** (`docker-compose.vps.yml` juga menguncinya ke
`sirabtor`). Jangan isi kedua baris itu di Coolify.

Aplikasi terbuka di `http://127.0.0.1:8080` server. Pasang proksi TLS di depannya, misalnya **Caddy** (sertifikat
otomatis):

```
sirabtor.mtsn2kotamalang.sch.id {
    reverse_proxy 127.0.0.1:8080
}
```

atau **nginx** (sertifikat dari certbot):

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

Firewall: buka hanya 22 (SSH), 80, 443. Port 8080 & 5432 tidak dipublikasikan ke internet oleh berkas ini.

Hosting tanpa Docker (VPS dengan PHP-FPM sendiri) memakai langkah yang sama dengan `docker/entrypoint.sh`: PHP 8.4 +
ekstensi di atas, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, paket `chromium`,
`libreoffice-calc-nogui`, `poppler-utils`, `postgresql-client-18`, locale `id_ID.UTF-8`, lalu setiap pembaruan
`php artisan sigenrabtor:siapkan-deploy && php artisan optimize`, pekerja `queue:work` & `schedule:work` (systemd/
supervisor), konfigurasi nginx dari `docker/nginx/default.conf`.

## 6. Redeploy aman

### Yang terjadi otomatis setiap kontainer `app` mulai

1. `APP_KEY` wajib terisi; folder storage disiapkan.
2. Menunggu database (`pg_isready`, maks. `SIRABTOR_TUNGGU_DB_DETIK`).
3. Di dalam **kunci berkas** pada volume storage (dua kontainer tidak pernah bermigrasi bersamaan),
   `php artisan sigenrabtor:siapkan-deploy`:
   - **pengaman** — berhenti *sebelum menulis apa pun* bila:
     - `APP_KEY` berbeda dengan saat data volume ini dibuat (yang dicatat hanya sidiknya, bukan kuncinya);
     - volume storage kosong/baru padahal database sudah dipakai (volume tidak terpasang / namanya berubah);
     - database kosong/baru padahal volume berisi data (salah `DB_HOST`/`DB_DATABASE`, volume database hilang);
     - volume & database berasal dari instalasi berbeda;
   - **cadangan** `storage/app/private/cadangan/{tanggal}-pra-migrasi|rilis-baru/` (`database.dump` + `data.tgz` +
     `info.json`) bila ada migrasi baru atau image rilis baru — gagal mencadangkan = migrasi **tidak** dijalankan;
   - **migrasi** (`migrate --force`), lalu id instalasi, sidik `APP_KEY`, dan rilis dicatat.
4. Cache konfigurasi/rute/view dibangun ulang dari image baru (aset Vite selalu ikut image — tidak ada aset basi).
5. Saat kontainer lama dihentikan: nginx & PHP-FPM menuntaskan permintaan berjalan, pekerja antrean menuntaskan
   pekerjaannya (`stop_grace_period: 6m30s`), PostgreSQL diberi 1 menit untuk checkpoint.

Data contoh tidak pernah ditanam ulang saat redeploy (tidak ada seeding otomatis), dan sesi pengguna tersimpan di
database sehingga pengguna tetap masuk.

### Aturan emas

- Jangan mengganti `APP_KEY` dan `DB_PASSWORD` setelah aplikasi dipakai.
- Jangan mengganti nama layanan (`app`, `db`) atau volume (`sirabtor_storage`, `sirabtor_pgdata`) di berkas compose, dan
  di VPS jangan mengganti `COMPOSE_PROJECT_NAME`/folder tanpa memindahkan volumenya.
- Jangan menjalankan `docker compose down -v`, `docker volume prune`, `php artisan migrate:fresh`/`db:wipe`/`db:seed`
  di produksi.
- Jangan menaikkan versi utama PostgreSQL (`postgres:18-alpine` → 19) dengan sekadar mengganti tag — butuh dump & restore.
- Satu replika `app`.

### Redeploy di Coolify

1. (Disarankan) **Terminal** layanan `app` → `php artisan sigenrabtor:cadangan --alasan=pra-redeploy`, lalu unduh
   salinannya ke luar server bila perubahan besar (bagian 8).
2. Dorong kode ke cabang `main` → **Redeploy** (atau aktifkan *Auto Deploy* dari webhook Git).
3. Pantau **Logs** layanan `app`: `Cadangan … dibuat`, `Instalasi … siap`, `[sirabtor] Siap: … rilis …`.
4. Bila deploy gagal karena pengaman, data tidak berubah — baca pesannya (tabel di bawah), perbaiki variabel/volume,
   lalu Redeploy. Untuk kembali ke versi lama, deploy ulang commit sebelumnya (*Rollback* di Coolify bila tersedia).

### Redeploy di VPS

```bash
cd /opt/sirabtor
./docker/redeploy.sh             # git pull --ff-only → build → cadangan → ganti kontainer → tunggu sehat
./docker/redeploy.sh --tanpa-git # kode sudah diperbarui manual
./docker/redeploy.sh --kembali   # jalankan lagi image sebelumnya (sirabtor:sebelumnya)
```

Image baru dibangun selagi versi lama tetap melayani pengguna, sehingga jeda layanan hanya selama kontainer diganti
(±0,5–2 menit, lebih lama bila ada migrasi atau pekerjaan antrean berjalan). `--kembali` tidak membatalkan migrasi yang
sudah jalan: bila rilis baru mengubah skema dan perlu dibatalkan, pulihkan juga cadangan `pra-migrasi` (bagian 8).

### Pesan pengaman & penanganannya

| Pesan di log | Artinya | Tindakan |
| --- | --- | --- |
| `APP_KEY berbeda dengan saat data di volume ini dibuat` | Variabel `APP_KEY` berubah | Kembalikan nilai lama. Bila sengaja: deploy sekali dengan `SIRABTOR_IZINKAN_GANTI_APP_KEY=true`, lalu kembalikan `false` dan isi ulang kunci API di Pengaturan AI. |
| `Volume storage kosong/baru, padahal database sudah dipakai` | Volume storage tidak terpasang/berganti nama | Pasang volume lama (Coolify *Storages*, nama proyek VPS). Memulihkan storage dari cadangan: sekali `SIRABTOR_IZINKAN_DATA_BARU=true`. |
| `Database kosong/baru, padahal volume storage berisi data` | Salah database / volume database hilang | Periksa `DB_HOST`/`DB_DATABASE` & volume `sirabtor_pgdata`. Memulihkan database dari cadangan: sekali `SIRABTOR_IZINKAN_DATA_BARU=true`. |
| `… berasal dari instalasi berbeda` | Volume & database milik dua instalasi | Pasangkan volume & database yang benar. |
| `Cadangan gagal (pg_dump): …` | `pg_dump` tidak bisa mencadangkan (versi server lebih baru, hak akses, disk penuh) | Perbaiki penyebab; bila sudah punya cadangan sendiri, deploy sekali dengan `SIRABTOR_CADANGAN_PRA_MIGRASI=false`. |

## 7. Admin pertama

Dengan `SIRABTOR_MODE_DEMO=false` akun hanya bisa masuk dengan sandi. Atur sandi dari terminal kontainer:

```bash
# Akun admin baru (sandi diketik tersembunyi, min. 8 karakter, huruf & angka, tidak memuat nama/surel):
php artisan sigenrabtor:atur-sandi admin@mtsn2kotamalang.sch.id --buat-admin --nama="Nama Admin"
# Akun yang sudah ada (mis. admin contoh) / sandi acak yang ditampilkan sekali:
php artisan sigenrabtor:atur-sandi puskom@example.com --acak
```

Di Coolify: *Terminal* layanan `app`; di VPS: `docker compose exec -u www-data app php artisan …`. Setelah masuk:
**Pengaturan › Leading & Akun**, **Profil & Pejabat**, **Tahun Anggaran**, **Master Kode** (unggah Excel), lalu
**Pengaturan AI**.

## 8. Cadangan & pemulihan

**Otomatis**: sebelum migrasi baru/rilis baru dan setiap hari pukul `SIRABTOR_CADANGAN_JAM`; `SIRABTOR_CADANGAN_SIMPAN`
set terbaru dipertahankan di `storage/app/private/cadangan/`. Satu set = `database.dump` (pg_dump format custom),
`data.tgz` (seluruh `storage/app` kecuali folder cadangan), `info.json` (alasan, waktu, rilis).

```bash
# VPS (di Coolify jalankan perintah php artisan … dari Terminal layanan app)
docker compose exec -u www-data app php artisan sigenrabtor:cadangan --alasan=manual   # buat sekarang
docker compose exec -u www-data app php artisan sigenrabtor:cadangan --daftar          # lihat set yang ada
docker compose cp app:/var/www/html/storage/app/private/cadangan ./cadangan-$(date +%F) # salin ke luar kontainer
```

Cadangan di volume melindungi dari migrasi/rilis yang salah, **bukan** dari server rusak — salin set terbaru ke luar
server secara berkala (rsync/rclone ke penyimpanan lain; di Coolify jalur 4b aktifkan juga Backups resource database).

**Pemulihan** dengan `sirabtor-pulihkan` di dalam kontainer `app` (Coolify: Terminal layanan `app`; VPS:
`docker compose exec app …`):

```bash
sirabtor-pulihkan                            # daftar set cadangan
sirabtor-pulihkan 20261101-013000-harian     # pulihkan (ketik PULIHKAN untuk konfirmasi)
```

Skrip itu menyalin set terpilih, mencadangkan keadaan sekarang sebagai set `pra-pemulihan` (pemulihan bisa dibatalkan
dengan memulihkan set itu), memasang mode pemeliharaan (`php artisan down`: pengguna melihat 503, antrean & penjadwal
berhenti), memulihkan database (`pg_restore --clean`) dan data storage dari **set yang sama** — sehingga pasangan
instalasinya tetap cocok — lalu mengaktifkan aplikasi lagi. Bila gagal di tengah jalan aplikasi tetap dalam mode
pemeliharaan agar data setengah pulih tidak dipakai.

Memulihkan dari cadangan di luar server: salin foldernya ke `storage/app/private/cadangan/` (mis.
`docker compose cp ./20261101-013000-harian app:/var/www/html/storage/app/private/cadangan/`) lalu jalankan skrip yang
sama. Bila hanya database **atau** hanya storage yang dipulihkan terpisah dan pengaman menolak saat redeploy, deploy
sekali dengan `SIRABTOR_IZINKAN_DATA_BARU=true` lalu kembalikan `false`.

## 9. Integrasi AI

Penyedia AI diatur admin di **Pengaturan › Pengaturan AI** (bukan variabel lingkungan) dan tersimpan di volume storage;
kunci API dienkripsi dengan `APP_KEY`. Tanpa penyedia aktif aplikasi tetap berjalan dengan saran tiruan. Kontainer hanya
butuh HTTPS keluar.

- **Cloud** (Claude, OpenAI, Gemini): tab *Penyedia* → kartu penyedia → model & kunci API → jadikan aktif (opsional
  cadangan). Izinkan HTTPS keluar ke `api.anthropic.com`, `api.openai.com`, atau `generativelanguage.googleapis.com`.
- **Ollama lokal** (data kegiatan tidak keluar dari server):

  ```bash
  docker compose -f docker-compose.prod.yml -f docker-compose.vps.yml -f docker-compose.ollama.yml up -d
  docker compose -f docker-compose.prod.yml -f docker-compose.vps.yml -f docker-compose.ollama.yml exec ollama ollama pull qwen2.5:7b
  ```

  (Di VPS tambahkan `:docker-compose.ollama.yml` ke `COMPOSE_FILE` agar ikut setiap redeploy.) Lalu di Pengaturan AI →
  Ollama: alamat `http://ollama:11434`, model `qwen2.5:7b`, batas waktu 120–300 detik untuk CPU, jadikan aktif. Model
  7B butuh RAM ±8 GB tanpa GPU; GPU NVIDIA: aktifkan blok `deploy` di `docker-compose.ollama.yml`. Di Coolify, Ollama
  bisa dipasang sebagai *Service* — isi alamatnya dengan nama host internal layanan itu.

## 10. Pemeriksaan sesudah deploy/redeploy

1. `https://domain/up` → 200; log memuat `Instalasi … siap.` dan `[sirabtor] Siap: Laravel … · rilis … · Chromium … ·
   LibreOffice …`.
2. Halaman Masuk tampil tanpa kartu persona demo; masuk sebagai admin.
3. Data lama masih ada (kegiatan, akun, pengaturan) — bukan data contoh.
4. `php artisan sigenrabtor:cadangan --daftar` menampilkan set cadangan (sesudah redeploy dengan migrasi: `pra-migrasi`).
5. Kegiatan → Pratinjau → **Unduh PDF** (Chromium); RAB → **Unduh PDF** (LibreOffice); Buku Dokumen → **Gabung PDF**.
6. Pengaturan AI → Kalimat KAK → *Buat dengan AI*.

## 11. Pemecahan masalah

| Gejala | Penyebab & jalan keluar |
| --- | --- |
| Kontainer berhenti: "APP_KEY belum diisi" | Isi `APP_KEY` (bagian 3). |
| "Database belum bisa dipakai setelah 60 detik" | Periksa `DB_*`; database eksternal lambat → naikkan `SIRABTOR_TUNGGU_DB_DETIK`. |
| Kontainer berhenti dengan pesan pengaman | Lihat tabel di bagian 6 — data belum diubah. |
| Migrasi/cadangan gagal "password authentication failed" sesudah `DB_PASSWORD` diganti | PostgreSQL hanya memakai `POSTGRES_PASSWORD` saat volume database pertama dibuat. Kembalikan sandi lama, atau ubah sandi di dalam database (`ALTER USER sigenrabtor PASSWORD '…'`) lalu samakan variabelnya. |
| Tautan/aset memakai `http://`, formulir gagal 419 | `APP_URL` harus https & sama dengan domain; `SIRABTOR_PROKSI_TEPERCAYA` terisi; jam server benar. |
| Unduh PDF gagal "Chrome/Chromium tidak ditemukan" | Image bukan dari `Dockerfile` ini atau `SIRABTOR_CHROME` salah. |
| Angka PDF RAB memakai koma Inggris | Locale `id_ID.UTF-8` belum dibuat (`locale -a`). |
| Saran AI "tidak terhubung"/"waktu habis" | Alamat Ollama harus nama host jaringan Docker (`http://ollama:11434`), bukan `localhost`; model besar di CPU butuh batas waktu lebih lama. |
| Disk penuh | Kurangi `SIRABTOR_CADANGAN_SIMPAN`, pindahkan set lama ke luar server, `docker image prune` (bukan `volume prune`). |

## Catatan pengujian konfigurasi

Konfigurasi ini disiapkan di mesin pengembangan tanpa akses Docker daemon, sehingga **image belum pernah dibangun dan
dijalankan di sana** — lakukan deploy uji (bagian 10) dan satu kali uji redeploy + pemulihan cadangan di server uji
sebelum dipakai sungguhan. Yang sudah diperiksa otomatis: logika pengaman, cadangan, retensi, dan perintah artisan
(`PengamanDeployTest`), keselarasan berkas deploy (`DeployTest`: batas waktu, stop grace period, pemeriksaan entrypoint,
variabel), sintaks `entrypoint.sh`, `redeploy.sh`, & `pulihkan.sh`, YAML berkas compose.
