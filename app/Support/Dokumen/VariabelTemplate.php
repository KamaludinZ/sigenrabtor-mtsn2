<?php

namespace App\Support\Dokumen;

/**
 * Variabel yang boleh dipakai di master KAK/TOR .docx (PRD §7C "Pemetaan variabel template"). Hanya variabel terdaftar
 * yang diterima saat admin menyimpan template, sehingga salah tulis (mis. ${nama_kegiaatan}) ketahuan sebelum dipakai.
 * Jenis: teks (diganti teks), daftar (butir bernomor), tabel (diganti tabel utuh), blok (jabatan + nama + NIP), gambar.
 */
final class VariabelTemplate
{
    /** Kelompok tampilan: kunci → judul. */
    public const KELOMPOK = [
        'kegiatan' => 'Kegiatan & tahun',
        'isi' => 'Isi KAK/TOR',
        'master' => 'Master per tahun',
        'kode' => 'Master kode',
        'rab' => 'RAB',
        'matrik' => 'Matrik & jadwal',
        'ttd' => 'Penanda tangan',
        'madrasah' => 'Profil madrasah',
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> nama → [keterangan, kelompok, jenis] */
    public const DAFTAR = [
        'tahun_anggaran' => ['Tahun anggaran aktif', 'kegiatan', 'teks'],
        'nama_kegiatan' => ['Nama kegiatan', 'kegiatan', 'teks'],
        'nama_kegiatan_cover' => ['Nama kegiatan versi cover (kapital)', 'kegiatan', 'teks'],
        'tanggal_dokumen' => ['Tanggal dokumen, mis. 23 Desember 2025', 'kegiatan', 'teks'],
        'tanggal_laporan' => ['Batas tanggal laporan', 'kegiatan', 'teks'],
        'tujuan' => ['Tujuan kegiatan', 'isi', 'teks'],
        'sasaran' => ['Sasaran kegiatan', 'isi', 'teks'],
        'tempat' => ['Tempat pelaksanaan', 'isi', 'teks'],
        'lama_hari' => ['Lama kegiatan (hari)', 'isi', 'teks'],
        'peserta' => ['Jumlah & jenis peserta', 'isi', 'teks'],
        'latar_belakang' => ['Latar belakang (paragraf baku + kegiatan)', 'master', 'teks'],
        'dasar_hukum' => ['Dasar hukum bernomor', 'master', 'daftar'],
        'kode_rktm' => ['Kode RKTM', 'kode', 'teks'],
        'uraian_rktm' => ['Uraian sub kegiatan RKTM', 'kode', 'teks'],
        'kode_sk' => ['Kode SK (sasaran kegiatan)', 'kode', 'teks'],
        'uraian_sk' => ['Uraian SK', 'kode', 'teks'],
        'kode_iksk' => ['Kode IKSK', 'kode', 'teks'],
        'uraian_iksk' => ['Uraian IKSK', 'kode', 'teks'],
        'kode_edm' => ['Kode EDM', 'kode', 'teks'],
        'uraian_edm' => ['Uraian indikator EDM', 'kode', 'teks'],
        'budaya_mutu' => ['5 Budaya Mutu terpilih', 'kode', 'teks'],
        'rab' => ['Tabel rincian RAB (Kode, Uraian, Vol, Satuan, Harga, Jumlah)', 'rab', 'tabel'],
        'total' => ['Total RAB, mis. Rp10.000.000,-', 'rab', 'teks'],
        'terbilang' => ['Terbilang total, mis. Sepuluh Juta Rupiah', 'rab', 'teks'],
        'sumber_dana' => ['Sumber dana dokumen, mis. KOMITE MTsN 2 Kota Malang Tahun 2026', 'rab', 'teks'],
        'matrik' => ['Tabel matrik tahapan × bulan', 'matrik', 'tabel'],
        'jadwal' => ['Tabel jadwal kegiatan', 'matrik', 'tabel'],
        'ttd_kepala' => ['Blok tanda tangan Kepala Madrasah', 'ttd', 'blok'],
        'ttd_leading' => ['Blok tanda tangan pejabat leading', 'ttd', 'blok'],
        'ttd_komite' => ['Blok “Mengetahui” Ketua Komite', 'ttd', 'blok'],
        'nama_madrasah' => ['Nama madrasah', 'madrasah', 'teks'],
        'kop' => ['Kop madrasah (nama, alamat, telepon)', 'madrasah', 'blok'],
        'kota' => ['Kota', 'madrasah', 'teks'],
        'logo' => ['Logo madrasah', 'madrasah', 'gambar'],
    ];

    /** Variabel yang sebaiknya ada di master KAK/TOR agar angka & identitas tidak diketik manual. */
    public const DIANJURKAN = ['nama_kegiatan', 'tahun_anggaran', 'total', 'terbilang'];

    public static function terdaftar(string $nama): bool
    {
        return array_key_exists($nama, self::DAFTAR);
    }

    /**
     * Variabel terdaftar yang paling mirip dengan nama yang salah tulis (huruf kecil, spasi/strip → garis bawah,
     * lalu jarak Levenshtein ≤ 3); null bila tak ada yang cukup mirip.
     */
    public static function saran(string $nama): ?string
    {
        $baku = trim((string) preg_replace('/[\s\-]+/u', '_', mb_strtolower(trim($nama))), '_');

        if (self::terdaftar($baku)) {
            return $baku;
        }

        $terbaik = null;
        $jarak = 4;

        foreach (array_keys(self::DAFTAR) as $kandidat) {
            $d = levenshtein($baku, $kandidat);

            if ($d < $jarak) {
                [$terbaik, $jarak] = [$kandidat, $d];
            }
        }

        return $terbaik;
    }
}
