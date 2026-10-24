<?php

namespace Tests\Unit;

use App\Support\Kegiatan\JadwalPelaksanaan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class JadwalPelaksanaanTest extends TestCase
{
    private function t(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal);
    }

    public function test_tanggal_selesai_hari_kalender(): void
    {
        $this->assertSame('2026-07-14', JadwalPelaksanaan::tanggalSelesai($this->t('2026-07-14'), 1)->toDateString());
        $this->assertSame('2026-07-16', JadwalPelaksanaan::tanggalSelesai($this->t('2026-07-14'), 3)->toDateString());
        $this->assertSame('2026-08-02', JadwalPelaksanaan::tanggalSelesai($this->t('2026-07-24'), 10)->toDateString());
    }

    public function test_tanggal_selesai_hari_kerja_melewati_akhir_pekan(): void
    {
        // Jumat 17 Juli 2026 + 3 hari kerja → Jumat, Senin, Selasa.
        $this->assertSame('2026-07-21', JadwalPelaksanaan::tanggalSelesai($this->t('2026-07-17'), 3, true)->toDateString());
        // Mulai Sabtu: hitungan dimulai Senin.
        $this->assertSame('2026-07-20', JadwalPelaksanaan::tanggalSelesai($this->t('2026-07-18'), 1, true)->toDateString());
    }

    public function test_bulan_tercakup_dan_batas_tahun(): void
    {
        $this->assertSame([7], JadwalPelaksanaan::bulanTercakup($this->t('2026-07-14'), $this->t('2026-07-16')));
        $this->assertSame([7, 8], JadwalPelaksanaan::bulanTercakup($this->t('2026-07-30'), $this->t('2026-08-02')));
        $this->assertSame([12], JadwalPelaksanaan::bulanTercakup($this->t('2026-12-30'), $this->t('2027-01-03')));
    }

    public function test_batas_laporan_sepuluh_hari_setelah_selesai(): void
    {
        $this->assertSame('2026-07-26', JadwalPelaksanaan::batasLaporan($this->t('2026-07-16'))->toDateString());
    }

    public function test_teks_bulan_untuk_dokumen(): void
    {
        $this->assertSame('Juli 2026', JadwalPelaksanaan::teksBulan([7], 2026));
        $this->assertSame('Juli – September 2026', JadwalPelaksanaan::teksBulan([9, 7, 8], 2026));
        $this->assertSame('Maret, Juni, September 2026', JadwalPelaksanaan::teksBulan([3, 6, 9], 2026));
        $this->assertNull(JadwalPelaksanaan::teksBulan([], 2026));
    }

    public function test_teks_rentang_tanggal(): void
    {
        $this->assertSame('14 Juli 2026', JadwalPelaksanaan::teksRentang($this->t('2026-07-14'), $this->t('2026-07-14')));
        $this->assertSame('14 – 16 Juli 2026', JadwalPelaksanaan::teksRentang($this->t('2026-07-14'), $this->t('2026-07-16')));
        $this->assertSame('30 Juli – 2 Agustus 2026', JadwalPelaksanaan::teksRentang($this->t('2026-07-30'), $this->t('2026-08-02')));
    }
}
