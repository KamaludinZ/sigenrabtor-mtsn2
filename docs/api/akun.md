# API Akun

Semua jawaban bertajuk `Cache-Control: no-store, private`. Permintaan POST dari peramban memerlukan token CSRF Laravel.

## `POST /daftar` — kirim permohonan akun (tanpa masuk)

Rute `akun.daftar.kirim`, dibatasi 6 permintaan per menit (`throttle:6,1` → 429). Isi lewat
`App\Support\Akun\PendaftaranAkun::ajukan()`.

| Isian | Keterangan |
| --- | --- |
| `nama` | Wajib, ≤ 150, berhuruf (spasi dirapikan). |
| `jabatan` | Opsional, ≤ 150. |
| `nip` | Opsional, NIP 18 angka (boleh berpemisah). |
| `leadings` | Wajib ≥ 1 kode leading aktif. |
| `surel` | Wajib; belum menjadi akun dan belum punya permohonan menunggu. |
| `sandi`, `sandi_ulang` | Wajib; ≥ 8 karakter, huruf & angka, tidak memuat nama/surel; sama. |
| `catatan` | Opsional, ≤ 300. |
| `setuju` | Wajib `true`. |

201 `{permohonan: {id, nama, surel, jabatan, nip, leadings, catatan, status: "menunggu", diajukan_pada, …, label_status, nip_format, leading: [{kode, nama_unit}]}, pesan}` — tanpa hash sandi. 422 `{pesan, galat: {kolom: pesan}}`, 503 gagal disimpan.

## `GET /pengaturan/akun/permohonan` — daftar permohonan (admin)

Rute `pengaturan.akun.permohonan` (`KeputusanPendaftaran::daftar`): `{menunggu: [...] (terlama dulu), diputuskan: [...] (terbaru dulu, maks. 20), jumlah_menunggu}`.

## `POST /pengaturan/akun/permohonan/{id}/setujui|tolak` — putuskan permohonan (admin)

Rute `pengaturan.akun.permohonan.putuskan`.

- `setujui` `{leadings?: [kode…]}` — akun leading dibuat (aturan akun: surel unik, leading aktif; `leadings` menggantikan
  leading yang diminta), sandinya = sandi yang dibuat pemohon. 200 `{permohonan (status disetujui, akun_id), akun, pesan}`.
- `tolak` `{alasan}` — alasan wajib ≤ 300. 200 `{permohonan (status ditolak, alasan), pesan}`.

Galat: 422 `{pesan, galat}`, 403 bukan admin, 404 permohonan tidak ada, 409 sudah diputuskan, 503 gagal disimpan.
