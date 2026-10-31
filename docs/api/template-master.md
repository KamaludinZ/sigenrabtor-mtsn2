# API Template Master

Endpoint JSON untuk template master KAK/TOR ber-variabel, multi per leading (PRD Fase 5 "Template KAK/TOR Variabel (Multi per
Leading)"). Semua rute khusus admin (middleware `HanyaAdmin`, bukan admin → 403) dan bertajuk
`Cache-Control: no-store, private`. Data daftar dari `App\Support\Dokumen\DaftarTemplateMaster`, perubahan lewat
`App\Support\Pengaturan\UnggahMasterTemplate` & `App\Support\Pengaturan\KelolaTemplateMaster`.

## `GET /pengaturan/template/master/data` — daftar

Rute `pengaturan.template.master.data`.

| Parameter | Keterangan |
| --- | --- |
| `ta` | Tahun anggaran; kosong = tahun aktif, tahun tak terdaftar → 404. |
| `cari` | Kata kunci (nama, catatan versi, nama berkas, jenis); semua kata wajib cocok. |
| `leading` | `umum` (pemilik kosong) atau kode leading; nilai tak dikenal diabaikan. |
| `jenis` | `KAK+TOR`, `KAK`, `TOR`, `RAB`. |
| `status` | `aktif` atau `nonaktif`. |

Jawaban 200:

```json
{
  "tahun": 2026,
  "terkunci": false,
  "saringan": { "cari": "", "leading": "1.6", "jenis": "", "status": "" },
  "ringkasan": { "jumlah": 3, "aktif": 2, "nonaktif": 1, "umum": 2, "khusus": 1, "leading": 1, "dipakai": 2, "tanpa_berkas": 0 },
  "tersaring": 1,
  "kelompok": [
    {
      "kunci": "1.6", "leading": "1.6", "label": "1.6 · Tim Penjamin Mutu",
      "default": { "KAK+TOR": "TPM reguler" },
      "template": [
        { "id": "…", "nama": "TPM reguler", "jenis": "KAK+TOR", "label_jenis": "KAK/TOR (gabungan)", "leading": "1.6",
          "label_leading": "1.6 · Tim Penjamin Mutu", "catatan": "Versi 2 — narasumber eksternal", "aktif": true, "default": true,
          "variabel": 14, "nama_berkas": "tpm.docx", "ada_berkas": true, "diperbarui_pada": "…", "pengunggah": "Koordinator PUSKOM",
          "dipakai": [], "kegiatan": 2, "tautan_unduh": "…/pengaturan/template/master/…/unduh" }
      ]
    }
  ],
  "tanpa_khusus": [ { "kode": "1.1", "nama_unit": "…" } ]
}
```

`default` template = default pemilik × jenis dan aktif. `kegiatan` = kegiatan tahun itu yang memakainya
(`PilihanTemplate::untukKegiatan`).

## `GET /pengaturan/template/master/{template}` — detail

Rute `pengaturan.template.master.detail`. Jawaban 200 `{template, isi, jumlah, dampak_nonaktif}`: `template` = satu baris
daftar di atas, `isi` = `DetailTemplate::untuk()` (variabel terdaftar/tidak + saran, paragraf, berkas, pemakaian), `jumlah` =
kemunculan tiap variabel di berkas `{nama_kegiatan: 2, …}`, `dampak_nonaktif` (template aktif saja) =
`KelolaTemplateMaster::dampak()` `{default, pengganti, dipilih, beralih, aturan, pilihan}`. Bukan master / tidak ada → 404.

## `POST /pengaturan/template/master` — unggah

Rute `pengaturan.template.master.simpan`, multipart:

| Isian | Keterangan |
| --- | --- |
| `berkas` | Wajib, .docx ≤ 10 MB; penanda `${…}` harus ada dan semuanya terdaftar. |
| `nama` | Wajib, ≤ 100 karakter (spasi dirapikan). |
| `jenis` | `KAK+TOR`, `KAK`, `TOR`, `RAB`. |
| `leading` | Kosong = umum (semua leading), selain itu kode leading aktif. |
| `catatan` | Opsional, catatan versi satu baris ≤ 300. |
| `default` | `1` = jadikan default pemilik × jenis (default lama dilepas); template pertama pemilik × jenis otomatis default. |

201 `{pesan, template}`; 422 `{pesan, galat: {berkas|nama|jenis|leading|catatan: …}}`; 403 tahun anggaran terkunci.

## `PATCH /pengaturan/template/master/{template}` — ubah

Rute `pengaturan.template.master.ubah`, JSON pembaruan sebagian: `nama`, `catatan` (`""` = tanpa catatan), `aktif`
(`false` = nonaktifkan — default pindah ke template aktif terbaru pemilik × jenis; `true` = aktifkan kembali), `default: true`.
Kunci yang tidak dikirim tetap. 200 `{pesan, perubahan: ["Nama: … → …", "Dinonaktifkan", "Menjadi default"], template}`;
422 `{pesan, galat: {nama|catatan|default: …}}` (template nonaktif tidak bisa menjadi default); 403 tahun terkunci; 404.

## `DELETE /pengaturan/template/master/{template}` — hapus

Rute `pengaturan.template.master.hapus`. 200 `{pesan}`; 409 `{pesan, kegiatan: [{id, nama}]}` bila masih dipilih kegiatan
(template yang sudah dipakai hanya dinonaktifkan); 403 tahun terkunci; 404.
