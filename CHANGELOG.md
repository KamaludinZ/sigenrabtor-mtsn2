# Catatan Perubahan

## Belum dirilis
- Halaman **Data Kegiatan** (`/kegiatan`) berupa tabel: No, Kegiatan (kode RKTM, penanda impor), Leading, Bulan, Sumber dana & pagu, Total RAB + kondisi anggaran, Status, Diubah, dan Aksi (Data/Kode/RAB). Filter leading, sumber dana, status, bulan pelaksanaan, kondisi anggaran, dan asal data; pencarian nama/kode RKTM; chip filter aktif; urut per kolom; ringkasan & baris jumlah pagu/total RAB; halaman 10–100 baris. Semua filter tersimpan di URL. Tombol *Kegiatan baru*, *Unduh template*, dan *Impor dari Excel*.
- Impor kegiatan massal dari Excel/CSV: pratinjau per baris (siap / dilewati / gagal beserta alasannya), proses bertahap dengan bilah kemajuan, laporan berhasil/gagal yang bisa diunduh.
- Impor rincian RAB per sumber dana dari Excel/CSV (tombol *Impor Excel* di Rincian belanja): tabel dari RAB lama langsung dikenali (judul kolom bervariasi, baris TOTAL/JUMLAH, tanda tangan diabaikan) dan disesuaikan otomatis — "6 OJ" dipisah jadi volume & satuan, harga dihitung dari jumlah bila kosong, volume disesuaikan bila jumlah memuat pengali di uraian (mis. 6 orang), kelompok belanja ditebak dari uraian, kode judul kelompok diturunkan. Pratinjau mencocokkan total dengan baris TOTAL berkas dan menunjukkan status pagu sesudah impor. Format tabel rincian bisa diunduh.
- Halaman **Termin pembayaran** di RAB (per sumber dana): sekaligus atau bertahap, termin bertanggal dengan nominal, persen, dan terbilang; bantuan *Bagi rata* dan *Sesuaikan termin terakhir*; status jumlah termin terhadap total RAB; pratinjau blok "Mekanisme Pembayaran" seperti Form RAB.
- Perbaikan: CSV dibaca sebagai teks sehingga "600.000" (titik ribuan) tidak terbaca 600.

## 1.0.0 — 25 Oktober 2026
- Nama aplikasi menjadi **SIRABTOR** (sebelumnya SIGENRABTOR); versi pertama ditetapkan 1.0.0.
- Nama & versi tampilan diatur di `config/sigenrabtor.php`; nama database, volume Docker, dan repositori tetap `sigenrabtor`.
