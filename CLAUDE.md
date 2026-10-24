# SIGENRABTOR — MTsN 2 Kota Malang

Generator KAK/TOR & RAB madrasah. PRD lengkap: `npx ngodingpakeai plan get fe0f436c-8f4b-4ce7-92bf-510b53eb56e7`.

## Stack
- PHP 8.4, Laravel 13, Livewire 4 (komponen berbasis kelas di `app/Livewire`, view di `resources/views/livewire`), Alpine.js (bawaan Livewire), Tailwind CSS 4, Vite.
- PostgreSQL 18 via Docker: `docker compose up -d` (port 5434, DB `sigenrabtor`; tes memakai `sigenrabtor_testing`).

## Perintah
- Jalankan: `php artisan serve` + `npm run dev` (atau `npm run build`).
- Tes: `php artisan test` · E2E: `npm run test:e2e` (Playwright, channel chrome, server port 8010) · Format: `vendor/bin/pint`.

## Konvensi
- Bahasa UI & domain: Indonesia (nama tabel/kolom mengikuti PRD: `activities`, `anggaran_kegiatan`, `budget_items`, ...).
- Frontend-first: layar dibangun di atas `App\Support\Stub\StubData` lalu backend menggantinya dengan Eloquent tanpa mengubah bentuk data di view.
- Penyimpanan kegiatan lewat kontrak `App\Contracts\KegiatanRepository` (saat ini `StubKegiatanRepository`, berkas JSON di disk local; E2E memakai `stub/kegiatan-e2e.json`). Backend cukup mengganti binding di `AppServiceProvider`.
- Komponen UI Blade di `resources/views/components/ui` (`<x-ui.icon>`, `<x-ui.form-section>`, `<x-ui.status-badge>`, `<x-ui.toast>`).
- Livewire 4: `wire:model.blur` hanya menunda state klien (tanpa request). Untuk validasi saat blur pakai `wire:model.live.blur`.
- Blade: jangan campur `@php(...)` bentuk pendek dengan blok `@php … @endphp` dalam satu file (kompilasi Blade jadi salah); pakai satu bentuk saja.
- Livewire mengisi otomatis properti publik yang namanya sama dengan parameter rute (mis. `{kegiatan}`); beri nama lain untuk properti data (mis. `$dataKegiatan`).
- Notifikasi dari Livewire: `$this->dispatch('notify', message: '...', type: 'success|error|info')`.
- Menu samping: `App\Support\Navigation` — item otomatis aktif begitu rutenya terdaftar.
- Folder `REFERENSI/` berisi dokumen contoh asli (tidak di-commit): acuan struktur KAK/TOR, RAB, dan master kode.

Aturan kerja NgodingPakeAI ada di `AGENTS.md`.
