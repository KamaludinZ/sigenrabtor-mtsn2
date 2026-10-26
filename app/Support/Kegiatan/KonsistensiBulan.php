<?php

namespace App\Support\Kegiatan;

/**
 * Pemeriksaan konsistensi bulan Matrik & Jadwal (PRD F10, masalah "isi tersalin dari kegiatan lain"): bulan matrik
 * tahapan dan jadwal dibandingkan dengan bulan pelaksanaan & bulan batas laporan dari Data Kegiatan, serta satu sama lain.
 * Hasilnya temuan "peringatan" (hampir pasti keliru) atau "saran" (perlu dicek) — tidak memblokir simpan/cetak (PRD
 * aturan 8: dokumen belum rapi tetap boleh dicetak sebagai DRAF).
 *
 * Kembaran klien: periksaKonsistensiBulan() di resources/js/konsistensi-bulan.js (kasus bersama
 * tests/fixtures/konsistensi-bulan.json) — pesan harus sama persis.
 */
final readonly class KonsistensiBulan
{
    /** Persiapan wajar dimulai paling awal sekian bulan sebelum pelaksanaan; lebih awal lagi kemungkinan tersalin dari kegiatan lain. */
    public const BATAS_PERSIAPAN = 3;

    /**
     * @param  list<int>  $pelaksanaan  Bulan pelaksanaan (Data Kegiatan), urut naik.
     * @param  int|null  $laporan  Bulan batas laporan di tahun anggaran aktif; null bila tanggal laporan belum diisi.
     * @param  array<string, list<array{uraian: string, bulan: list<int>}>>  $matrik  Bentuk MatrikTahapan::$butir.
     * @param  list<array{uraian: string, bulan: list<int>}>  $jadwal  Bentuk simpan (MatrikJadwal::jadwalUntukDisimpan()).
     */
    public function __construct(
        public array $pelaksanaan,
        public ?int $laporan,
        public array $matrik,
        public array $jadwal,
    ) {}

    /**
     * @return list<array{kunci: string, tautan: string, tingkat: 'peringatan'|'saran', pesan: string}>
     */
    public function temuan(): array
    {
        if ($this->pelaksanaan === []) {
            return [self::temuanBaru('tanpa-bulan', 'acuan', 'saran', 'Bulan pelaksanaan belum diisi di Data Kegiatan — konsistensi bulan belum bisa diperiksa.')];
        }

        $awal = $this->pelaksanaan[0];
        $teksAwal = self::nama([$awal]);
        $teksLaporan = $this->laporan === null ? null : self::nama([$this->laporan]);
        $sebelum = fn (array $bulan, int $batas) => array_values(array_filter($bulan, fn (int $b) => $b < $batas));
        $sesudah = fn (array $bulan, ?int $batas) => $batas === null ? [] : array_values(array_filter($bulan, fn (int $b) => $b > $batas));
        $tahap = fn (string $kunci) => MatrikTahapan::rapikanBulan(array_merge([], ...array_column($this->matrik[$kunci] ?? [], 'bulan')));
        [$persiapan, $pelaksanaan, $evaluasi] = [$tahap('persiapan'), $tahap('pelaksanaan'), $tahap('evaluasi_pelaporan')];
        $hasil = [];

        // Matrik tahapan (tahapan yang belum bertanda sudah ditangani cek kelengkapan).
        if ($pelaksanaan !== [] && ($kurang = array_values(array_diff($this->pelaksanaan, $pelaksanaan))) !== []) {
            $hasil[] = self::temuanBaru('matrik-pelaksanaan-kurang', 'matrik', 'peringatan', 'Tahap Pelaksanaan belum ditandai di '.self::nama($kurang).' (bulan pelaksanaan).');
        }
        if (($dini = $sebelum($pelaksanaan, $awal - 1)) !== []) {
            $hasil[] = self::temuanBaru('matrik-pelaksanaan-dini', 'matrik', 'peringatan', 'Tahap Pelaksanaan ditandai di '.self::nama($dini).", jauh sebelum bulan pelaksanaan ({$teksAwal}).");
        }
        if (($lewat = $sesudah($pelaksanaan, $this->laporan)) !== []) {
            $hasil[] = self::temuanBaru('matrik-pelaksanaan-lewat', 'matrik', 'peringatan', 'Tahap Pelaksanaan ditandai di '.self::nama($lewat).", melewati bulan batas laporan ({$teksLaporan}).");
        }
        if (($telat = $sesudah($persiapan, $awal)) !== []) {
            $hasil[] = self::temuanBaru('matrik-persiapan-sesudah', 'matrik', 'peringatan', 'Tahap Persiapan ditandai di '.self::nama($telat).", sesudah pelaksanaan dimulai ({$teksAwal}).");
        }
        if (($dini = $sebelum($persiapan, $awal - self::BATAS_PERSIAPAN)) !== []) {
            $hasil[] = self::temuanBaru('matrik-persiapan-dini', 'matrik', 'saran', 'Tahap Persiapan ditandai di '.self::nama($dini).', lebih dari '.self::BATAS_PERSIAPAN." bulan sebelum pelaksanaan ({$teksAwal}).");
        }
        if (($dini = $sebelum($evaluasi, $awal)) !== []) {
            $hasil[] = self::temuanBaru('matrik-evaluasi-sebelum', 'matrik', 'peringatan', 'Tahap Evaluasi dan Pelaporan ditandai di '.self::nama($dini).", sebelum pelaksanaan dimulai ({$teksAwal}).");
        }
        if (($lewat = $sesudah($evaluasi, $this->laporan)) !== []) {
            $hasil[] = self::temuanBaru('matrik-evaluasi-lewat', 'matrik', 'peringatan', 'Tahap Evaluasi dan Pelaporan ditandai di '.self::nama($lewat).", melewati bulan batas laporan ({$teksLaporan}).");
        }

        // Jadwal kegiatan.
        $bulanJadwal = MatrikTahapan::rapikanBulan(array_merge([], ...array_column($this->jadwal, 'bulan')));

        if ($bulanJadwal !== [] && ($kurang = array_values(array_diff($this->pelaksanaan, $bulanJadwal))) !== []) {
            $hasil[] = self::temuanBaru('jadwal-pelaksanaan-kurang', 'jadwal', 'peringatan', 'Jadwal belum memuat bulan pelaksanaan ('.self::nama($kurang).').');
        }

        $sebelumnya = null;
        foreach ($this->jadwal as $i => $baris) {
            $bulan = MatrikTahapan::rapikanBulan($baris['bulan'] ?? []);

            if ($bulan === []) {
                continue;
            }

            $nomor = $i + 1;
            if (($lewat = $sesudah($bulan, $this->laporan)) !== []) {
                $hasil[] = self::temuanBaru("jadwal-{$nomor}-lewat", "baris-jadwal-{$nomor}", 'peringatan', "Baris {$nomor} dijadwalkan ".self::nama($lewat).", melewati bulan batas laporan ({$teksLaporan}).");
            }
            if (($dini = $sebelum($bulan, $awal - self::BATAS_PERSIAPAN)) !== []) {
                $hasil[] = self::temuanBaru("jadwal-{$nomor}-dini", "baris-jadwal-{$nomor}", 'peringatan', "Baris {$nomor} dijadwalkan ".self::nama($dini).', lebih dari '.self::BATAS_PERSIAPAN." bulan sebelum pelaksanaan ({$teksAwal}) — mungkin tersalin dari kegiatan lain.");
            }
            if ($sebelumnya !== null && $bulan[0] < $sebelumnya['mulai']) {
                $hasil[] = self::temuanBaru("jadwal-{$nomor}-urutan", "baris-jadwal-{$nomor}", 'saran', "Baris {$nomor} (".self::nama([$bulan[0]]).") lebih awal dari baris {$sebelumnya['nomor']} (".self::nama([$sebelumnya['mulai']]).') — urutkan agar kronologis.');
            }

            $sebelumnya = ['nomor' => $nomor, 'mulai' => $bulan[0]];
        }

        // Silang matrik ↔ jadwal: keduanya menggambarkan kegiatan yang sama.
        $bulanMatrik = MatrikTahapan::rapikanBulan(array_merge($persiapan, $pelaksanaan, $evaluasi));

        if ($bulanMatrik !== [] && $bulanJadwal !== []) {
            if (($hanyaJadwal = array_values(array_diff($bulanJadwal, $bulanMatrik))) !== []) {
                $hasil[] = self::temuanBaru('jadwal-tanpa-matrik', 'jadwal', 'saran', 'Jadwal memuat '.self::nama($hanyaJadwal).' yang tidak ditandai di matrik tahapan.');
            }
            if (($hanyaMatrik = array_values(array_diff($bulanMatrik, $bulanJadwal))) !== []) {
                $hasil[] = self::temuanBaru('matrik-tanpa-jadwal', 'matrik', 'saran', 'Matrik tahapan menandai '.self::nama($hanyaMatrik).' yang tidak ada di jadwal.');
            }
        }

        return $hasil;
    }

    /** Ada bulan bertanda di matrik atau jadwal (tanpa itu belum ada yang bisa dibandingkan). */
    public function adaIsian(): bool
    {
        $bulan = array_merge(array_column(array_merge([], ...array_values($this->matrik)), 'bulan'), array_column($this->jadwal, 'bulan'));

        return MatrikTahapan::rapikanBulan(array_merge([], ...$bulan)) !== [];
    }

    /** Jumlah temuan bertingkat peringatan. */
    public function jumlahPeringatan(): int
    {
        return count(array_filter($this->temuan(), fn (array $t) => $t['tingkat'] === 'peringatan'));
    }

    /**
     * @param  list<int>  $bulan
     */
    private static function nama(array $bulan): string
    {
        return implode(', ', array_map(fn (int $b) => JadwalPelaksanaan::NAMA_BULAN[$b], $bulan));
    }

    /**
     * @param  'peringatan'|'saran'  $tingkat
     * @return array{kunci: string, tautan: string, tingkat: 'peringatan'|'saran', pesan: string}
     */
    private static function temuanBaru(string $kunci, string $tautan, string $tingkat, string $pesan): array
    {
        return ['kunci' => $kunci, 'tautan' => $tautan, 'tingkat' => $tingkat, 'pesan' => $pesan];
    }
}
