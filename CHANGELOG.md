# Catatan Perubahan

## Belum dirilis
- Halaman **Data Kegiatan** (`/kegiatan`) berupa tabel: No, Kegiatan (kode RKTM, penanda impor), Leading, Bulan, Sumber dana & pagu, Total RAB + kondisi anggaran, Status, Diubah, dan Aksi (Data/Kode/RAB). Filter leading, sumber dana, status, bulan pelaksanaan, kondisi anggaran, dan asal data; pencarian nama/kode RKTM; chip filter aktif; urut per kolom; ringkasan & baris jumlah pagu/total RAB; halaman 10–100 baris. Semua filter tersimpan di URL. Tombol *Kegiatan baru*, *Unduh template*, dan *Impor dari Excel*.
- Impor kegiatan massal dari Excel/CSV: pratinjau per baris (siap / dilewati / gagal beserta alasannya), proses bertahap dengan bilah kemajuan, laporan berhasil/gagal yang bisa diunduh.

## 1.0.0 — 25 Oktober 2026
- Nama aplikasi menjadi **SIRABTOR** (sebelumnya SIGENRABTOR); versi pertama ditetapkan 1.0.0.
- Nama & versi tampilan diatur di `config/sigenrabtor.php`; nama database, volume Docker, dan repositori tetap `sigenrabtor`.
