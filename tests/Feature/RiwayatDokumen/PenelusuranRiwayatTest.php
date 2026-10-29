<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Livewire\Dokumen\Riwayat;
use App\Support\Dokumen\PenelusuranRiwayat;
use App\Support\Dokumen\RiwayatDokumen;
use Livewire\Livewire;
use Tests\TestCase;

class PenelusuranRiwayatTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function telusur(array $nilai): PenelusuranRiwayat
    {
        return PenelusuranRiwayat::dari(RiwayatDokumen::untuk(self::LEADING), $nilai);
    }

    public function test_kata_kunci_dan_saringan_satu_alur_beserta_hitungan_pilihan(): void
    {
        $tanpa = $this->telusur([]);
        $this->assertFalse($tanpa->aktif());
        $this->assertNull($tanpa->keterangan());
        $this->assertSame(9, $tanpa->total);
        $this->assertCount(9, $tanpa->cocok);
        $this->assertEquals(['lengkap' => 6, 'KAK+TOR' => 1, 'KAK' => 0, 'TOR' => 0, 'RAB' => 2], $tanpa->pilihan('jenis'));
        $this->assertSame(['pdf' => 7, 'docx' => 1, 'xlsx' => 1], $tanpa->pilihan('format'));

        $pdf = $this->telusur(['format' => 'pdf']);
        $this->assertSame(6, $pdf->pilihan('jenis')['lengkap']);
        $this->assertSame(1, $pdf->pilihan('jenis')['RAB'], 'Hitungan jenis mengikuti saringan format.');
        $this->assertSame(0, $pdf->pilihan('jenis')['KAK+TOR']);
        $this->assertSame(['pdf' => 7, 'docx' => 1, 'xlsx' => 1], $pdf->pilihan('format'), 'Pilihan format sendiri diabaikan saat menghitung format.');

        $workshop = $this->telusur(['cari' => '  workshop ', 'jenis' => 'RAB']);
        $this->assertSame('workshop', $workshop->kriteria['cari']);
        $this->assertSame(['rd-2026-6'], array_column($workshop->cocok, 'id'));
        $this->assertSame(['pdf' => 0, 'docx' => 0, 'xlsx' => 1], $workshop->pilihan('format'));
        $this->assertSame(1, $workshop->pilihan('jenis')['lengkap'], 'Hitungan jenis mengikuti kata kunci.');
        $this->assertSame('1 dari 9 dokumen cocok dengan “workshop” · RAB', $workshop->keterangan());

        $rab = $this->telusur(['jenis' => 'RAB']);
        $ringkasan = $rab->ringkasan();
        $this->assertSame([2, 2], [$ringkasan['jumlah'], $ringkasan['kegiatan']]);
        $this->assertSame([9, 4], [$ringkasan['semua']['jumlah'], $ringkasan['semua']['kegiatan']]);
        $this->assertSame('2 dari 9 dokumen · RAB', $rab->keterangan());

        $terbalik = $this->telusur(['dari' => '2026-03-01', 'sampai' => '2026-01-01']);
        $this->assertTrue($terbalik->kriteria['terbalik']);
        $this->assertFalse($terbalik->aktif(), 'Rentang terbalik tidak diterapkan.');
        $this->assertCount(9, $terbalik->cocok);

        $this->expectException(\InvalidArgumentException::class);
        $tanpa->pilihan('dari');
    }

    public function test_per_kegiatan_menandai_versi_lebih_baru_yang_tersaring_dan_halaman(): void
    {
        $lomba = fn (PenelusuranRiwayat $t) => collect($t->perKegiatan())->firstWhere('nama_kegiatan', 'Lomba Kreativitas Siswa Akhir Tahun');

        $semua = $lomba($this->telusur([]));
        $this->assertSame([2, 2], [$semua['jumlah'], $semua['jumlah_semua']]);
        $this->assertSame('rd-2025-5', $semua['dokumen'][0]['terbaru']['id']);
        $this->assertNull($semua['dokumen'][0]['lebih_baru']);

        $lama = $lomba($this->telusur(['sampai' => '2025-09-30']));
        $this->assertSame([1, 2], [$lama['jumlah'], $lama['jumlah_semua']]);
        $this->assertSame('rd-2025-4', $lama['dokumen'][0]['terbaru']['id']);
        $this->assertSame('rd-2025-5', $lama['dokumen'][0]['lebih_baru']['id'], 'Versi terbaru di luar rentang tetap diberitahukan.');

        [$kelompok, $jumlah] = $this->telusur([])->halaman('kronologis', 'terlama', 3);
        $this->assertSame(9, $jumlah);
        $this->assertSame([2025, 2026], array_keys($kelompok));
        $this->assertSame(['rd-2025-4', 'rd-2025-5'], array_column($kelompok[2025], 'id'));
        $this->assertSame(['rd-2026-1'], array_column($kelompok[2026], 'id'));

        [$kegiatan, $jumlahKegiatan] = $this->telusur(['format' => 'pdf'])->halaman('kegiatan', 'terbaru', 20);
        $this->assertSame(4, $jumlahKegiatan);
        $this->assertSame([2026, 2025], array_keys($kegiatan));
        $workshop = collect($kegiatan[2026])->firstWhere('kegiatan_id', 1);
        $this->assertSame([1, 3], [$workshop['jumlah'], $workshop['jumlah_semua']]);
    }

    public function test_halaman_memakai_penelusuran_untuk_ringkasan_pilihan_dan_keterangan(): void
    {
        Livewire::test(Riwayat::class)
            ->assertSeeHtml('data-tersaring="0"')
            ->assertSeeHtml('value="lengkap" data-jumlah="6"')
            ->assertSeeText('Semua format (9)')
            ->assertDontSee('data-testid="hasil-riwayat"', false)
            ->set('format', 'pdf')
            ->assertSeeHtml('data-tersaring="1"')
            ->assertSeeText('dari 9 dokumen')
            ->assertSeeText('dari 4 kegiatan')
            ->assertSeeHtml('value="RAB" data-jumlah="1"')
            ->assertSeeHtml('data-testid="hasil-riwayat" data-jumlah="7"')
            ->assertSeeText('1 dari 3 dokumen')
            ->set('cari', 'workshop')
            ->assertDontSeeText('cocok dengan “workshop” dari', 'Keterangan kotak cari diganti keterangan hasil gabungan.')
            ->assertSeeHtml('data-testid="hasil-riwayat" data-jumlah="1"')
            ->assertSeeText('1 dari 9 dokumen cocok dengan “workshop” · PDF')
            ->set('format', '')
            ->assertSeeText('3 dokumen cocok dengan “workshop” dari 9.')
            ->set('cari', '')
            ->set('sampai', '2025-09-30')
            ->assertSeeHtml('data-testid="versi-lebih-baru" data-versi="2"')
            ->assertSeeText('Ada versi lebih baru: v2')
            ->assertSeeText('1 dari 2 dokumen');
    }
}
