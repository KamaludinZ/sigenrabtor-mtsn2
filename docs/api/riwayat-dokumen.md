# Kontrak API — Riwayat Dokumen

Daftar berkas KAK/TOR, RAB, dan set dokumen yang pernah dicetak, diunduh, atau diarsipkan (PRD §7 Riwayat Dokumen, F28).
Satu jalur dengan halaman `/dokumen`: catatan dalam cakupan akun (`RiwayatDokumen::untuk` — admin semua, akun leading
dokumen leading-nya atau buatannya) ditelusuri `PenelusuranRiwayat`. Sesi aplikasi, `Cache-Control: no-store, private`.

## `GET /dokumen/data` — daftar berpenyaring (`dokumen.data`)

| Parameter | Isi | Tak sah |
|---|---|---|
| `cari` | kata kunci (≤ 100 karakter; semua kata wajib muncul di nama kegiatan, nama berkas, nomor, leading, pembuat, jenis, format, sumber, status, asal) | — |
| `jenis` | `KAK+TOR` · `KAK` · `TOR` · `RAB` · `lengkap` | diabaikan |
| `format` | `pdf` · `docx` · `xlsx` | diabaikan |
| `leading` | kode leading (termasuk nonaktif) | diabaikan |
| `sumber` | `DIPA` · `Komite` | diabaikan |
| `status` | status kegiatan saat dokumen dibuat: `draf` · `diperiksa` · `dikembalikan` · `disetujui` · `final` | diabaikan |
| `dari`, `sampai` | tanggal dibuat `Y-m-d` (inklusif; `sampai` < `dari` = rentang tidak diterapkan) | diabaikan |
| `tampilan` | `kronologis` (bawaan) · `kegiatan` | `kronologis` |
| `urut` | `terbaru` (bawaan) · `terlama` (kronologis) | `terbaru` |
| `hal` | halaman ≥ 1 (dijepit ke halaman terakhir) | 1 |
| `per` | 10 · 20 (bawaan) · 50 · 100 | 20 |

Jawaban 200:

```json
{
  "saringan": { "cari": "", "jenis": "RAB", "format": "", "leading": "", "sumber": "DIPA", "status": "", "dari": "", "sampai": "", "terbalik": false, "tampilan": "kronologis", "urut": "terbaru", "hal": 1, "per": 20 },
  "label": { "jenis": "RAB", "sumber": "Sumber dana DIPA" },
  "keterangan": "1 dari 9 dokumen · RAB · Sumber dana DIPA",
  "aktif": true, "total": 9, "jumlah": 1,
  "ringkasan": { "jumlah": 1, "kegiatan": 1, "tahun": 1, "arsip": 0, "draf": 1, "format": { "pdf": 1, "docx": 0, "xlsx": 0 }, "semua": { "...": 0 } },
  "pilihan": { "jenis": [{ "nilai": "RAB", "label": "RAB", "jumlah": 1 }], "format": [], "leading": [], "sumber": [], "status": [] },
  "halaman": { "ke": 1, "dari": 1, "per": 20, "jumlah": 1 },
  "tampilan": "kronologis",
  "dokumen": [{ "id": "rd-2026-1", "kegiatan_id": 2, "nama_kegiatan": "…", "jenis": "RAB", "sumber": "DIPA", "format": "pdf", "asal": "unduh", "nama_berkas": "…_DRAF.pdf", "versi": 1, "status": "draf", "bertanda_draf": true, "dibuat_pada": "2026-02-10T10:12:00.000000+07:00", "tersedia": true, "tautan_kegiatan": "/kegiatan/2/pratinjau?sumber=DIPA", "...": "…" }],
  "saran_longgar": []
}
```

- `pilihan` = hitungan tiap nilai saringan bila dipilih bersama kriteria lain (nilai itu sendiri diabaikan).
- `tampilan=kegiatan` mengganti `dokumen` dengan `kegiatan`: per kegiatan `jumlah`/`jumlah_semua` dan `dokumen` per jenis ×
  sumber × format {`terbaru`, `lama`, `lebih_baru` — versi terbaru yang tidak cocok dengan saringan, null bila tidak ada}.
- `saran_longgar` (hanya bila tanpa hasil) = kriteria yang bisa dilepas beserta jumlah dokumen yang akan tampil.
- Tiap baris dokumen membawa `izin` {lihat, buka_ulang, unduh_arsip, duplikat} dari `OtorisasiDokumen`.

## `GET /dokumen/{id}` — rincian satu dokumen (`dokumen.detail`)

`AksiRiwayatDokumen::lihat` + rencana duplikat ringkas: `dokumen` (catatan + `total_teks`), `terbaru`, `versi` (semua versi
jenis × sumber × format yang sama, terbaru dulu), `perbedaan`, `kegiatan` (null bila tidak tersimpan/di luar cakupan),
`sekarang`, `buka` (tautan arsip/pratinjau/cetak/PDF + Word/Excel + nama berkas), `alasan_buka`, `izin` & `alasan` tiap aksi,
`duplikat` {bisa, mode tahun_lalu|tahun_ini, alasan, tautan, nama, nama_terkunci, tahun}. 404 `{pesan}` tidak ada, 403
`{pesan}` di luar cakupan atau akun nonaktif.

## Otorisasi (`App\Support\Dokumen\OtorisasiDokumen`)

| Aksi | Syarat |
|---|---|
| `lihat` | akun aktif; admin semua; akun leading: leading tertaut ke akunnya (termasuk leading nonaktif) atau dokumen buatannya |
| `buka_ulang` | `lihat` + kegiatan masih tersimpan + kegiatan dalam cakupan akun (`AksesKegiatan`) + sumber dana masih dipilih |
| `unduh_arsip` | `buka_ulang` + catatan arsip final versi terbaru |
| `duplikat` | `lihat` + tahun aktif terbuka + kegiatan tersimpan & dalam cakupan + admin atau akun yang tertaut ke leading kegiatan + leading aktif |
