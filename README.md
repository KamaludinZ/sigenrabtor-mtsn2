# SIRABTOR — MTsN 2 Kota Malang

**Versi 1.0.0** · sebelumnya bernama SIGENRABTOR. Nama database, volume Docker, dan repositori tetap `sigenrabtor` (identitas teknis, tidak tampil ke pengguna).

Generator **KAK/TOR & RAB** madrasah: data kegiatan diisi sekali, lalu cover, KAK/TOR, RAB, dan lembar pengesahan
dihasilkan otomatis dalam format seragam (Word/PDF), dapat dicetak per leading dan per sumber dana (DIPA/Komite).

## Teknologi
PHP 8.4 · Laravel 13 · Livewire 4 · Alpine.js · Tailwind CSS 4 · PostgreSQL 18

## Menjalankan secara lokal
```bash
docker compose up -d          # PostgreSQL 18 di port 5434 (+ database testing)
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build  # atau: npm run dev
php artisan serve             # http://127.0.0.1:8000
```

## Pengujian
```bash
php artisan test     # unit & feature (PHPUnit, database sigenrabtor_testing)
npm run test:e2e     # end-to-end Playwright di Google Chrome terpasang (desktop & mobile)
```

## Deploy produksi
Docker/Coolify (image dengan Chromium, LibreOffice, poppler; PostgreSQL 18; AI cloud atau Ollama lokal): lihat
[`docs/deploy.md`](docs/deploy.md). Admin pertama: `php artisan sigenrabtor:atur-sandi <surel> --buat-admin --nama="…"`.

## Status
Pengembangan bertahap per fase (frontend di atas data tiruan lebih dulu, lalu backend).
Selama tahap data tiruan, menu profil menyediakan sakelar **Mode demo** untuk melihat tampilan Admin/Leading.
