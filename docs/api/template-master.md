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

201 `{pesan, template, peringatan}`; 422 `{pesan, galat: {berkas|nama|jenis|leading|catatan: …, "variabel.{nama}": "${totl} tidak
terdaftar — maksud Anda ${total}?"}}`; 403 tahun anggaran terkunci.

## `POST /pengaturan/template/master/periksa` — periksa penanda tanpa menyimpan

Rute `pengaturan.template.master.periksa`, multipart `berkas` (.docx) + `jenis`. Isi dari
`App\Support\Dokumen\ValidasiVariabelTemplate::periksa()`. 200:

```json
{
  "jenis": "KAK+TOR", "label_jenis": "KAK/TOR (gabungan)", "bisa_disimpan": false,
  "variabel": [ { "nama": "totl", "jumlah": 1, "terdaftar": false, "saran": "total", "keterangan": null, "kelompok": null, "jenis": null } ],
  "galat": { "berkas": "Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.", "variabel.totl": "${totl} tidak terdaftar — maksud Anda ${total}?" },
  "peringatan": [ { "kunci": "sendiri.dasar_hukum", "pesan": "${dasar_hukum} (daftar) sebaiknya berdiri sendiri di paragrafnya — …" } ]
}
```

Peringatan tidak menahan simpan: `sendiri.{nama}` (penanda daftar/tabel/blok/gambar berbagi paragraf dengan teks lain), `rab`
(template RAB tanpa `${rab}`), `dianjurkan` (penanda identitas & angka yang belum dipakai). Berkas/jenis tidak sah → 422.

## `PATCH /pengaturan/template/master/{template}` — ubah

Rute `pengaturan.template.master.ubah`, JSON pembaruan sebagian: `nama`, `catatan` (`""` = tanpa catatan), `aktif`
(`false` = nonaktifkan — default pindah ke template aktif terbaru pemilik × jenis; `true` = aktifkan kembali), `default: true`.
Kunci yang tidak dikirim tetap. 200 `{pesan, perubahan: ["Nama: … → …", "Dinonaktifkan", "Menjadi default"], template}`;
422 `{pesan, galat: {nama|catatan|default: …}}` (template nonaktif tidak bisa menjadi default); 403 tahun terkunci; 404.

## `DELETE /pengaturan/template/master/{template}` — hapus

Rute `pengaturan.template.master.hapus`. 200 `{pesan}`; 409 `{pesan, kegiatan: [{id, nama}], dokumen: [{asal, kegiatan_id,
nama_kegiatan, jenis, label_jenis, sumber, keterangan}]}` bila masih dipilih kegiatan atau tercatat dipakai dokumen —
`asal` `hasil` (hasil susunan tersimpan) atau `riwayat` (Riwayat Dokumen: dicetak/diunduh/arsip final). Template yang sudah
dipakai hanya dinonaktifkan; 403 tahun terkunci; 404. Baris daftar (`GET …/data`) membawa `dokumen` (jumlah dokumen
tercatat) dan `boleh_dihapus`.

## `GET /pengaturan/template/variabel` — daftar variabel terdaftar

Rute `pengaturan.template.variabel`. Isi dari `App\Support\Dokumen\DaftarVariabelTemplate::untuk()`.

| Parameter | Keterangan |
| --- | --- |
| `ta` | Tahun anggaran untuk pemakaian; kosong = tahun aktif, tak terdaftar → 404. |
| `kegiatan` | Opsional, id kegiatan contoh → `contoh` tiap variabel; tak ada → 404. |
| `sumber` | Opsional, sumber dana kegiatan contoh (kosong = sumber pertama); tidak dipilih → 422 `galat.sumber`. |

Jawaban 200:

```json
{
  "tahun": 2026,
  "jumlah": 37,
  "contoh_kegiatan": { "id": 1, "nama": "Workshop …", "sumber": "Komite" },
  "kelompok": [
    {
      "kunci": "kegiatan", "judul": "Kegiatan & tahun",
      "variabel": [
        { "nama": "tahun_anggaran", "penanda": "${tahun_anggaran}", "keterangan": "Tahun anggaran aktif", "jenis": "teks",
          "berdiri_sendiri": false, "dianjurkan": true, "asal": "…", "dipakai": { "jumlah": 1, "template": ["Master reguler"] },
          "contoh": "2026" }
      ]
    }
  ]
}
```

`berdiri_sendiri` = jenis daftar/tabel/blok/gambar (sebaiknya sendirian di paragrafnya); `contoh` teks, daftar/blok = list
baris, tabel = ringkasan, null = belum ada datanya (atau tanpa kegiatan contoh).

## `GET /kegiatan/{kegiatan}/template/pilihan` — pilihan template & format satu kegiatan

Rute `kegiatan.template.pilihan`, semua pengguna yang berhak atas kegiatan itu (`AksesKegiatan`; selain itu 403, kegiatan
tidak ada 404). Isi dari `App\Support\Dokumen\PilihanTemplateKegiatan::data()`, tanpa cache.

```json
{
  "kegiatan_id": 4, "tahun": 2026,
  "berlaku": {
    "format": { "kunci": "lima_bab", "label": "Format 5 bab" },
    "kak": { "id": "…", "nama": "TPM narasumber", "jenis": "KAK+TOR", "catatan": "Versi 2", "leading": "1.6",
             "asal": "kegiatan", "label_asal": "dipilih untuk kegiatan ini", "ada_berkas": true },
    "cover": null
  },
  "template": {
    "dipilih": "…",
    "otomatis": { "id": "…", "nama": "TPM reguler", "asal": "default_leading", "label_asal": "Default template leading" },
    "pilihan": [ { "id": "…", "nama": "TPM reguler", "jenis": "KAK+TOR", "leading": "1.6", "catatan": "Versi 1", "default": true, "aktif": true } ]
  },
  "format": {
    "dipilih": "lima_bab", "tahun": "sembilan_bagian",
    "pilihan": [ { "nilai": "", "label": "Ikuti tahun anggaran (Format 9 bagian)", "keterangan": "…" }, { "nilai": "sembilan_bagian", … }, { "nilai": "lima_bab", … } ]
  },
  "dapat_diubah": true, "alasan_terkunci": null, "versi": "318c…"
}
```

`berlaku.kak` null = susunan baku (tanpa master Word). `template.otomatis` = template yang dipakai bila `dipilih` kosong
(aturan khusus leading Format Seragam → default template leading → pilihan per jenis kegiatan → bawaan). `pilihan` memuat
template aktif milik leading kegiatan (default lebih dulu) lalu template umum; template nonaktif hanya muncul bila sedang
dipilih kegiatan ini (`aktif: false`).

## `PUT /kegiatan/{kegiatan}/template/pilihan` — ubah pilihan

Rute `kegiatan.template.pilih`. Badan `{ "template"?: id|""|null, "format"?: "sembilan_bagian"|"lima_bab"|""|null,
"versi"?: string }` — hanya kunci yang dikirim yang berubah; kosong/null = otomatis (template) atau mengikuti tahun
anggaran (format). Disimpan lewat `UbahDataKegiatan` (kolom `template_kak`, `format_dokumen`).

| Status | Isi |
| --- | --- |
| 200 | `{ pesan, diubah: ["format_dokumen", "template_kak"], …data GET }` (`diubah` kosong = tidak ada perubahan) |
| 422 | `{ pesan, galat: { template | format } }` — template di luar pilihan/nonaktif, format tak dikenal, atau tanpa kiriman |
| 403 | `{ pesan }` — bukan cakupan akun, status bukan Draf/Dikembalikan, atau tahun anggaran terkunci |
| 409 | `{ pesan, versi }` — `versi` usang (kegiatan diubah di tempat lain); `versi` = versi terbaru |
| 404 | kegiatan tidak ada |

