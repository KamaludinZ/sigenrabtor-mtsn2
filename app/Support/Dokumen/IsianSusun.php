<?php

namespace App\Support\Dokumen;

/**
 * Isian data kegiatan yang bisa dilengkapi langsung dari kartu bagian tab Susun KAK/TOR (PRD F4 "Susun Otomatis"):
 * `BAGIAN` nomor bagian KAK/TOR → kolom (bentuk UbahDataKegiatan::ISIAN), `KOLOM` kolom → [label, jenis input
 * teks|uraian|tanggal|angka, panjang/nilai maksimal (= ValidasiKegiatan), contoh isian]. Disimpan lewat
 * UbahDataKegiatan::ubah (aturan & bentrok sama dengan Data Kegiatan); isian lain tetap di halamannya masing-masing.
 */
final class IsianSusun
{
    public const KOLOM = [
        'latar_belakang' => ['Latar belakang khusus', 'uraian', 5000, 'Alasan khusus kegiatan ini diselenggarakan (opsional) — mis. tindak lanjut temuan EDM.'],
        'tujuan' => ['Tujuan', 'uraian', 2000, 'mis. menghasilkan dokumen kurikulum yang terlegitimasi sesuai prinsip partisipatif.'],
        'sasaran' => ['Sasaran', 'uraian', 2000, 'mis. dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah.'],
        'tanggal_mulai' => ['Tanggal mulai', 'tanggal', null, null],
        'lama_hari' => ['Lama kegiatan (hari)', 'angka', 366, 'mis. 2'],
        'peserta_jumlah' => ['Jumlah peserta', 'angka', 100000, 'mis. 40'],
        'peserta_jenis' => ['Jenis peserta', 'teks', 150, 'mis. guru dan tenaga kependidikan'],
        'uraian_pelaksanaan' => ['Bentuk pelaksanaan', 'uraian', 3000, 'mis. workshop tatap muka dengan narasumber, dilanjutkan kerja kelompok.'],
        'tempat' => ['Tempat pelaksanaan', 'teks', 255, 'mis. Aula MTsN 2 Kota Malang'],
        'tanggal_dokumen' => ['Tanggal dokumen (lembar pengesahan)', 'tanggal', null, null],
    ];

    /** Nomor bagian KAK/TOR → kolom yang bisa dilengkapi di tempat, urut tampil. */
    public const BAGIAN = [
        1 => ['latar_belakang'],
        3 => ['tujuan', 'sasaran'],
        4 => ['tanggal_mulai', 'lama_hari', 'peserta_jumlah', 'peserta_jenis', 'uraian_pelaksanaan'],
        5 => ['tempat'],
        9 => ['tanggal_dokumen'],
    ];

    /** @return list<string> */
    public static function untuk(int $nomor): array
    {
        return self::BAGIAN[$nomor] ?? [];
    }

    /**
     * Nilai tersimpan kolom bagian itu sebagai isian formulir (teks; kosong = '').
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string>
     */
    public static function isian(array $kegiatan, int $nomor): array
    {
        $hasil = [];
        foreach (self::untuk($nomor) as $kolom) {
            $nilai = $kegiatan[$kolom] ?? null;
            $hasil[$kolom] = is_scalar($nilai) ? (string) $nilai : '';
        }

        return $hasil;
    }

    /**
     * Isian formulir → kiriman UbahDataKegiatan: hanya kolom bagian itu, teks kosong = null, angka bulat dibaca sebagai
     * bilangan (bukan angka dibiarkan agar ditolak aturannya).
     *
     * @param  array<string, mixed>  $isian
     * @return array<string, mixed>
     */
    public static function kiriman(int $nomor, array $isian): array
    {
        $hasil = [];
        foreach (self::untuk($nomor) as $kolom) {
            $nilai = is_string($isian[$kolom] ?? null) ? trim($isian[$kolom]) : ($isian[$kolom] ?? null);
            $hasil[$kolom] = match (true) {
                $nilai === '' || $nilai === null => null,
                self::KOLOM[$kolom][1] === 'angka' && is_string($nilai) && ctype_digit($nilai) => (int) $nilai,
                default => $nilai,
            };
        }

        return $hasil;
    }
}
