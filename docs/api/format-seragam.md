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

## `POST /kegiatan/{id}/dokumen/lengkap` — simpan dokumen lengkap dalam format seragam

Rute `kegiatan.dokumen.lengkap`; hak `AksesKegiatan`. Isi dari `App\Support\Dokumen\SimpanDokumenLengkap::simpan()`:
dokumen set lengkap (cover + KAK/TOR + RAB) satu sumber dana disusun dari data terbaru lewat `PembuatDokumen` (hasil
kegiatan × sumber ditimpa) beserta format seragam yang dipakai dan nomor dokumennya.

Isian (opsional): `sumber` (kosong = sumber dana pertama), `nomor_dokumen` (nomor ketikan sendiri; huruf/angka/spasi/
`.`/`/`/`-`, ≤ 100, tidak dipakai dokumen lain tahun itu).

Jawaban 201 (pertama kali) / 200 (disusun ulang):

```json
{
  "dokumen": { "jenis": "lengkap", "sumber": "DIPA", "nomor": "DOK/008/1.6/2026",
               "format": { "tahun": 2026, "sumber": "DIPA", "template": { "jenis": "workshop", "asal": "jenis", … },
                           "kop_lengkap": true, "kop_kurang": [], "aturan_nomor": { "pola": "DOK/{urut}/{leading}/{tahun}", "digit": 3, "aktif": true } },
               "bagian": [ … ], "rab": { … }, "cover": { … }, "keadaan": { … }, "tautan_cetak": "…" },
  "baru": true,
  "berubah": true,
  "nomor": { "nomor": "DOK/008/1.6/2026", "keadaan": "terbit", "label": "Nomor diterbitkan otomatis" },
  "pesan": "Dokumen … disusun dari data terbaru. Nomor dokumen DOK/008/1.6/2026."
}
```

`nomor.keadaan`: `tetap` (nomor tersimpan; nomor lain → 422), `manual`, `arsip` (mengikuti nomor arsip final yang sudah
tercatat), `terbit` (diterbitkan otomatis — hanya selama kegiatan Disetujui/Final), `tidak_dinomori` (aturan penomoran set
lengkap tidak aktif), `menunggu` (draf: nomor terbit setelah disetujui). Arsip final yang diunggah kemudian memakai nomor
yang sama. Galat: 422 `{pesan, galat: {sumber | nomor_dokumen}}`, 403, 404, 503 gagal disimpan.
