# API Akun

Semua jawaban bertajuk `Cache-Control: no-store, private`. Permintaan POST dari peramban memerlukan token CSRF Laravel.

## `POST /masuk` — masuk

Rute `akun.masuk.kirim` (terbuka). Isian `{surel, sandi, ingat?}`; pemeriksaan lewat `App\Support\Akun\Autentikasi`:
surel terdaftar, aktif, bukan permohonan yang masih menunggu; sandi dicocokkan dengan hash tersimpan. Akun yang belum punya
sandi (akun contoh tiruan) hanya bisa masuk selama `SIRABTOR_MODE_DEMO=true` — di produksi matikan agar setiap akun wajib
bersandi.

- 200 `{pesan, akun: {id, nama, surel, peran, label_peran, jabatan, leading, masuk_pada}, tujuan}` — sesi baru dibuat
  (diregenerasi); `tujuan` = halaman yang diingat sebelum masuk atau Dashboard. `ingat: true` memasang cookie
  `sirabtor_ingat` (30 hari; tidak berlaku lagi setelah sandi diganti) yang memulihkan sesi yang berakhir.
- 422 `{pesan, galat: {surel | sandi}, peringatan}` — `peringatan` = sisa percobaan sebelum dijeda.
- 429 `{pesan, galat, tunggu}` + `Retry-After` — 5 percobaan gagal untuk satu surel × IP dijeda 60 detik.

## `POST /keluar` — keluar

Rute `akun.keluar`: sesi diakhiri & cookie "ingat saya" dihapus. Permintaan JSON → 200 `{pesan, masuk}`; selain itu
dialihkan ke halaman Masuk.

## `GET /akun/sesi` — state sesi

Rute `akun.sesi` (terbuka): `{masuk, akun | null, wajib_masuk, mode_demo, tautan: {masuk, keluar, daftar}}`.

## `GET /akun/profil` & `PATCH /akun/profil` — profil akun yang masuk

Rute `akun.profil.data` / `akun.profil.ubah`.

- `GET` → `{id, nama, surel, jabatan, nip, nip_format, peran, label_peran, leading: [{kode, nama_unit, aktif}], aktif,
  terakhir_masuk, dibuat_pada, riwayat_masuk, versi}`.
- `PATCH` `{nama?, jabatan?, versi?}` — pembaruan sebagian lewat `App\Support\Akun\UbahProfil` (kunci yang tidak dikirim
  tetap; nama wajib berhuruf ≤ 150, jabatan ≤ 150, spasi dirapikan; kosongkan jabatan dengan `""`). Surel, NIP, peran, dan
  leading hanya diubah admin — kunci lain → 422 `galat.isian`. 200 `{pesan, ringkasan | null (tanpa perubahan), akun,
  versi}`; 422 `{pesan, galat}`; 409 `{pesan, versi, akun}` bila `versi` usang (profil diubah di tempat lain); 503 gagal.

## `PUT /akun/sandi` — ganti sandi akun yang masuk

Rute `akun.sandi.ganti`; isian `{sandi_lama, sandi_baru, sandi_ulang}` lewat `App\Support\Akun\GantiSandi` (sandi saat
ini wajib & cocok bila akun sudah bersandi; sandi baru ≥ 8 karakter, huruf & angka, tidak memuat nama/surel, berbeda
dari sandi saat ini). 200 `{pesan, diganti_pada}` — id sesi diganti, cookie "ingat saya" perangkat ini diterbitkan ulang
(perangkat lain yang diingat harus masuk lagi). 422 `{pesan, galat}`; sandi saat ini salah 5× → 429 `{pesan, galat,
tunggu}` + `Retry-After`; 503 gagal disimpan.

## `POST /pengaturan/akun/{id}/sandi` — atur ulang sandi akun (admin)

Rute `pengaturan.akun.sandi` (`App\Support\Akun\AturUlangSandi`): sandi sementara 12 karakter dibuat & disimpan
terenkripsi. 200 `{pesan, sandi_sementara, akun: {id, nama, surel}}` — sandi sementara hanya dikirim sekali dan tidak
pernah dicatat. Akun sendiri → 422 (pakai `PUT /akun/sandi`), bukan admin → 403, akun tidak ada → 404.

## `GET /akun/hak` — hak akses akun yang masuk

Rute `akun.hak`: `{peran, hak: {kunci: {label, boleh, alasan}}}` dari `App\Support\Akun\HakAkses::daftar()` — kunci
`area_admin`, `kelola_pengaturan`, `kelola_master`, `kelola_akun`, `putuskan_permohonan`, `periksa_dokumen`, `monitoring`
(admin), `buat_kegiatan` (admin; leading yang tertaut ke leading aktif), `susun_dokumen`, `pakai_ai` (admin & leading).
`alasan` = pesan penolakan siap tampil (belum masuk, akun nonaktif, khusus admin, belum tertaut ke leading aktif).

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

## `GET /pengaturan/peran` — peran beserta hak & akunnya (admin)

Rute `pengaturan.peran` (`App\Support\Akun\KelolaPeran::daftar`): `{peran: [{kode, label, keterangan, perlu_leading,
hak: [{kunci, label}], jumlah_aktif, jumlah_nonaktif, akun: [{id, nama, surel, aktif, leading}]}], versi}`.

## `PUT /pengaturan/akun/{id}/peran` — ubah peran akun (admin)

Rute `pengaturan.akun.peran`; isian `{peran: "admin" | "leading", leadings?: [kode…], versi?}` lewat `KelolaPeran::ubah`:
peran akun sendiri tidak bisa diubah, minimal satu admin aktif tetap ada, akun leading wajib tertaut ≥ 1 leading aktif
(`leadings` kosong = tautan lama), admin tanpa tautan leading; tercatat di Riwayat Perubahan. 200 `{akun, ringkasan |
null, versi, pesan}`; 422 `{pesan, galat: {role | leadings}}`; 403; 404; 409 `{pesan, versi}` bila `versi` (dari
`GET /pengaturan/peran` atau `/pengaturan/akun/data`) usang; 503.

