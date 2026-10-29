# Kontrak API — Generasi Dokumen Server

Kontrak pembuatan berkas dokumen kegiatan (KAK/TOR, RAB, set lengkap) di server untuk Cetak & Unduh (PRD F12, F16, F21).
Satu pintu di kode: kontrak `App\Contracts\GeneratorBerkasDokumen` (implementasi `App\Support\Dokumen\Generasi\GeneratorBerkasServer`).
Semua endpoint memakai sesi aplikasi (cookie), hak baca `AksesKegiatan` (admin semua; akun leading kegiatan leading-nya
atau buatannya), dan `Cache-Control: no-store, private`.

## Format & jenis

| Format | Kunci | MIME | Lembar yang dimuat | Per sumber dana | Server | Peramban |
|---|---|---|---|---|---|---|
| PDF | `pdf` | `application/pdf` | cover, KAK/TOR, RAB | tidak | ya | — |
| Word | `docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | cover, KAK/TOR | tidak | belum (501) | ya (`unduhWord`) |
| Excel | `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | RAB | ya | ya | ya (`unduhExcel`) |

Jenis dokumen (`DokumenKegiatan::JENIS`) → lembar berkas (`FormatBerkas::LEMBAR_JENIS`) → format yang cocok:

| Jenis | Lembar | Format |
|---|---|---|
| `lengkap` (Set lengkap) | cover, KAK/TOR, RAB | `pdf` |
| `KAK+TOR`, `KAK`, `TOR` | cover, KAK/TOR | `pdf`, `docx` |
| `RAB` | RAB | `pdf`, `xlsx` |

Jenis `lengkap` & `RAB` disusun per sumber dana (`DokumenKegiatan::PER_SUMBER`); jenis KAK/TOR memakai sumber dana pertama
untuk bagian Pembiayaan. Kegiatan tanpa sumber dana hanya bisa membuat cover + KAK/TOR.

## `GET /kegiatan/{id}/berkas` — katalog (`kegiatan.berkas`)

Jawaban 200:

```json
{
  "kegiatan_id": 2,
  "format": { "pdf": { "label": "PDF", "mime": "application/pdf", "ekstensi": "pdf", "lembar": ["cover", "kak", "rab"], "perlu_sumber": false, "server": true, "klien": false }, "...": {} },
  "berkas": [
    {
      "kegiatan_id": 2, "jenis": "RAB", "sumber": "DIPA", "format": "xlsx", "lembar": ["rab"],
      "label_jenis": "RAB", "label_format": "Excel",
      "nama_berkas": "RAB_Supervisi_Akademik_dan_Refleksi_Pembelajaran_DIPA_2026_DRAF.xlsx",
      "draf": true, "alasan_draf": ["Belum disetujui admin (status Draf)."],
      "server": true, "klien": true,
      "tautan": "/kegiatan/2/berkas/unduh?jenis=RAB&sumber=DIPA&format=xlsx"
    }
  ]
}
```

Satu baris per jenis × sumber dana × format yang cocok. `server` false = gunakan penyusun peramban (`klien` true).
Galat: 404 kegiatan tidak ada, 403 di luar cakupan akun.

## `GET /kegiatan/{id}/berkas/unduh?format=&jenis=&sumber=` — unduh (`kegiatan.berkas.unduh`)

Parameter (dirapikan `PermintaanBerkas::dari`):

- `format` (wajib): `pdf` | `docx` | `xlsx`.
- `jenis` (opsional): kunci `DokumenKegiatan::JENIS`; kosong = `RAB` untuk Excel, `KAK+TOR` untuk Word atau kegiatan tanpa
  sumber dana, selain itu `lengkap`.
- `sumber` (opsional): sumber dana yang dipilih kegiatan; kosong = sumber pertama.

Jawaban 200 = isi berkas dengan header:

| Header | Isi |
|---|---|
| `Content-Type` | MIME format |
| `Content-Disposition` | `attachment; filename="…"; filename*=UTF-8''…` |
| `Content-Length` | ukuran byte |
| `X-Nama-Berkas` | nama berkas baku (`…_DRAF.ext` hanya bila lembarnya DRAF) |
| `X-Format-Berkas` | `pdf` / `docx` / `xlsx` |
| `X-Draf` | `1` bila bercap DRAF, selain itu `0` |

Galat (permintaan dengan `Accept: application/json`; permintaan biasa dialihkan ke Cetak & Unduh dengan notifikasi):

| Status | Isi | Kapan |
|---|---|---|
| 404 | — | kegiatan tidak ada |
| 403 | — | di luar cakupan akun |
| 422 | `{ "pesan": "…", "galat": { "format" \| "jenis" \| "sumber": "…" } }` | format/jenis tidak dikenal, jenis × format tidak cocok, sumber dana tidak dipilih / dibutuhkan |
| 501 | `{ "pesan": "…", "jenis": "tidak_didukung", "klien": true, "tautan_cetak": "…" }` | format belum dibuat server (Word) — susun di peramban |
| 503 | `{ "pesan": "…", "jenis": "gagal", "tautan_cetak": "…" }` | perender/pengubah berkas gagal — `tautan_cetak` = halaman cetak sebagai jalan lain |

## Perender PDF

`PembuatPdfDokumen` (dipakai format `pdf` dan `kegiatan.cetak.pdf`) dipilih lewat `SIRABTOR_PDF_PERENDER`:

- `chrome` (bawaan) — `App\Support\Dokumen\PembuatPdfChrome`: halaman cetak `dokumen.cetak` dirender di server (mode build
  Vite, aset `public/build` lewat `file://`, gambar cover sebagai data URI) lalu dicetak Chrome/Chromium headless
  (`--print-to-pdf`). Hasilnya sama dengan Cetak › Simpan sebagai PDF: A4, cover tanpa margin, tanda air & kaki DRAF,
  "Halaman x dari y". Butuh `npm run build` dan Chrome/Chromium (`SIRABTOR_CHROME` = path/nama program; kosong = dicari:
  google-chrome, google-chrome-stable, chromium, chromium-browser). Batas waktu `SIRABTOR_PDF_BATAS_DETIK` (bawaan 90).
- `tiruan` — PDF teks sederhana (pengembangan & PHPUnit).

Program tidak ada, aset belum dibangun, terlalu lama, atau hasil bukan PDF → `GagalUbahPdf` → 503 dengan `tautan_cetak`.

## Kontrak PHP

```php
interface GeneratorBerkasDokumen
{
    public function mendukung(string $format): bool;
    /** @throws GagalBuatBerkas */
    public function buat(PermintaanBerkas $permintaan): BerkasDokumen;
}
```

- `PermintaanBerkas` — kegiatan, jenis, sumber, format, lembar, pengguna; selalu sah (dibuat lewat `dari()` atau oleh
  `KatalogBerkas`).
- `BerkasDokumen` — isi, nama, format, draf, alasan DRAF, permintaan; `header()` untuk jawaban unduhan.
- `GagalBuatBerkas` — `jenis` (`tidak_didukung` 501, `gagal` 503), pesan siap tampil, `tautanCetak`.

Endpoint lama tetap: `kegiatan.cetak.pdf` (PDF lembar bebas untuk tombol unduh PDF), `kegiatan.rab.excel`/`kegiatan.rab.pdf`
(ekspor RAB dari halaman RAB).
