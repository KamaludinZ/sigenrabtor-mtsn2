# API Format Seragam

Endpoint JSON untuk template dokumen yang tersedia dan template yang berlaku (PRD §9 "Pilihan Template"). Semua jawaban
bertajuk `Cache-Control: no-store, private`.

## `GET /pengaturan/format/template` — daftar template tersedia (admin)

Rute `pengaturan.format.template`, middleware `HanyaAdmin` (bukan admin → 403). Isi dari
`App\Support\Dokumen\DaftarTemplate::untuk()`.

| Parameter | Keterangan |
| --- | --- |
| `ta` | Tahun anggaran; kosong = tahun aktif, tahun tak terdaftar → 404 (`TahunDipilih`). |
| `jenis` | Opsional, kunci jenis kegiatan (`workshop`, `supervisi`, `lomba`, `siswa`, `umum`) → isi `berlaku`. |
| `leading` | Opsional, kode leading terdaftar; dipakai bersama `jenis` untuk template khusus leading. |

Jawaban 200:

```json
{
  "tahun": 2026,
  "terkunci": false,
  "saringan": { "jenis": "workshop", "leading": "1.6" },
  "kak": [
    { "id": "…", "nama": "Master Workshop", "jenis": "KAK+TOR", "label": "…", "default": true, "variabel": 5,
      "ada_berkas": true, "nama_berkas": "master.docx", "tautan_unduh": "…/pengaturan/template/master/…/unduh",
      "dipakai": [ { "asal": "jenis", "jenis": "workshop", "label_jenis": "Workshop/pelatihan", "leading": null } ] }
  ],
  "cover": [
    { "id": "…", "nama": "Cover Default", "label": "…", "default": true, "khusus": null, "leading": null, "sumber_dana": null,
      "ada_berkas": true, "tautan_gambar": "…/pengaturan/template/cover/…/gambar", "dipakai": [] }
  ],
  "pilihan": { "jenis": [ … ], "leading": [ … ], "diperbarui_pada": "…" },
  "jenis_kegiatan": [ { "kunci": "workshop", "label": "…" } ],
  "berlaku": { "jenis": "workshop", "label_jenis": "…", "asal": "leading", "label_asal": "Khusus leading",
               "kak": { "id": "…", "nama": "…", "jenis": "KAK+TOR", "ada_berkas": true }, "kak_hilang": false,
               "cover": { "id": "…", "nama": "…", "tautan_gambar": "…" }, "cover_hilang": false, "susunan_baku": false },
  "ringkasan": { "kak": 1, "cover": 2, "kak_dipakai": 1, "cover_dipakai": 1, "tanpa_berkas": 0 },
  "diperbarui_pada": "…"
}
```

- `kak` hanya master keluarga KAK/TOR (`KAK+TOR`, `KAK`, `TOR`); master RAB tidak ikut.
- `berlaku` = `null` tanpa `jenis`. `asal`: `leading` (template khusus leading × jenis), `jenis` (pilihan per jenis
  kegiatan), atau `bawaan` (susunan baku & cover default).
- `jenis`/`leading` tak dikenal → 422 `{ "pesan": "…", "galat": { "jenis": "…", "leading": "…" } }`.

## `GET /kegiatan/{id}/template` — template yang berlaku untuk satu kegiatan

Rute `kegiatan.template_dokumen`; hak baca `AksesKegiatan` (di luar cakupan → 403, kegiatan tidak ada → 404). Isi dari
`DaftarTemplate::untukKegiatan()`: `kegiatan_id`, `tahun` (tahun anggaran kegiatan) + bentuk `berlaku` di atas (jenis
kegiatan ditebak dari nama kegiatan, tautan gambar cover lewat rute non-admin `dokumen.cover.gambar`).
