<?php

namespace Tests\Feature;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Support\Stub\StubAnggaran;
use Tests\TestCase;

class StubAnggaranTest extends TestCase
{
    public function test_anggaran_kegiatan_contoh_memuat_total_pagu_dan_termin(): void
    {
        $anggaran = collect(StubAnggaran::anggaranKegiatan())->where('kegiatan_id', 1);

        $this->assertSame(
            [['total', 10_000_000], ['pagu', 10_000_000], ['termin', 7_680_000], ['termin', 2_320_000]],
            $anggaran->map(fn ($a) => [$a['jenis'], $a['nilai']])->values()->all(),
        );
        $this->assertSame('Total RAB Komite', $anggaran->first()['label']);
    }

    public function test_termin_contoh_berjumlah_sama_dengan_total_rab(): void
    {
        $repo = app(RabRepository::class);

        $this->assertSame(10_000_000, array_sum(array_column($repo->termin(1, 'Komite'), 'nominal')));
        $this->assertSame([], $repo->termin(1, 'DIPA'));
    }

    public function test_anggaran_mengikuti_data_kegiatan_baru(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Baru',
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 5_000_000]],
        ]);

        $baru = collect(StubAnggaran::anggaranKegiatan())->where('kegiatan_id', $id)->values();

        $this->assertSame(['total', 'pagu'], $baru->pluck('jenis')->all());
        $this->assertSame([0, 5_000_000], $baru->pluck('nilai')->all());
    }

    public function test_kasus_uji_terbilang_unik_dan_berformat_baku(): void
    {
        $kasus = StubAnggaran::kasusUji();
        $nilai = array_column($kasus, 'nilai');

        $this->assertSame(count($nilai), count(array_unique($nilai)), 'Tidak ada nilai ganda.');

        foreach ($kasus as $k) {
            $this->assertStringEndsWith(' Rupiah', $k['terbilang']);
            $this->assertMatchesRegularExpression('/^([A-Z][a-z]+ )+Rupiah$/', $k['terbilang'], "Huruf awal kapital: {$k['terbilang']}");
        }
    }

    public function test_contoh_kesalahan_dokumen_lama_tersedia(): void
    {
        $this->assertContains('#NAME?', array_column(StubAnggaran::kesalahanDokumenLama(), 'tertulis'));
    }
}
