<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Dokumen\StatusCetak;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubRiwayatDokumenRepository;
use RuntimeException;
use Tests\TestCase;

class StrukturRiwayatDokumenTest extends TestCase
{
    private const KUNCI = ['id', 'kegiatan_id', 'tahun_anggaran', 'kode_leading', 'nama_kegiatan', 'jenis', 'sumber', 'format', 'asal', 'nama_berkas', 'ukuran', 'status', 'bertanda_draf', 'nomor_dokumen', 'versi', 'ringkas', 'user_id', 'dibuat_oleh', 'dibuat_pada', 'isi'];

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.'];

    public function test_contoh_riwayat_lintas_tahun_terbaru_dulu(): void
    {
        $semua = app(RiwayatDokumenRepository::class)->semua();

        $this->assertCount(12, $semua);
        $this->assertSame(self::KUNCI, array_keys($semua[0]));
        $this->assertSame('rd-2026-7', $semua[0]['id'], 'Terbaru dulu.');
        $this->assertSame([2025, 2026], array_values(array_unique(array_map('intval', array_column(array_reverse($semua), 'tahun_anggaran')))));
        foreach ($semua as $c) {
            $this->assertArrayHasKey($c['jenis'], DokumenKegiatan::JENIS);
            $this->assertArrayHasKey($c['format'], RiwayatDokumen::FORMAT);
            $this->assertArrayHasKey($c['asal'], RiwayatDokumen::ASAL);
            $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $c['dibuat_pada']);
            $this->assertStringEndsWith('.'.$c['format'], $c['nama_berkas']);
            $this->assertSame($c['bertanda_draf'], str_contains($c['nama_berkas'], '_DRAF.'));
        }

        // Kegiatan contoh TA 2026 memakai nama & leading yang sama dengan data kegiatan tiruan.
        $contoh = StubKegiatanRepository::contohSemua();
        foreach (array_filter($semua, fn ($c) => $c['kegiatan_id'] !== null) as $c) {
            $this->assertSame([$contoh[$c['kegiatan_id']]['nama_kegiatan'], $contoh[$c['kegiatan_id']]['leading'], 2026], [$c['nama_kegiatan'], $c['kode_leading'], $c['tahun_anggaran']]);
        }
        $arsip = collect($semua)->firstWhere('id', 'rd-2025-5');
        $this->assertSame([null, 'final', false, 'KAK/031/2025', 2, 'arsip'], [$arsip['kegiatan_id'], $arsip['status'], $arsip['bertanda_draf'], $arsip['nomor_dokumen'], $arsip['versi'], $arsip['asal']], 'Versi kedua dokumen yang sama.');
        $this->assertSame('KAK-TOR_RAB_Lomba_Kreativitas_Siswa_Akhir_Tahun_KOMITE_2025.pdf', $arsip['nama_berkas']);
        $this->assertSame($arsip, app(RiwayatDokumenRepository::class)->temukan('rd-2025-5'));
        $this->assertNull(app(RiwayatDokumenRepository::class)->temukan('tidak-ada'));
    }

    public function test_nama_berkas_sejalan_dengan_status_cetak(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $this->assertSame(StatusCetak::dari($kegiatan, 'DIPA')->namaBerkas('RAB', 'xlsx'), RiwayatDokumen::namaBerkas('RAB', $kegiatan['nama_kegiatan'], 'DIPA', 2026, true, 'xlsx'));
        $this->assertSame(StatusCetak::dari($kegiatan, 'DIPA')->namaBerkas('KAK-TOR_RAB', 'pdf'), RiwayatDokumen::namaBerkas('lengkap', $kegiatan['nama_kegiatan'], 'DIPA', 2026, true, 'pdf'));
        $this->assertSame('KAK-TOR_Kegiatan_2025.docx', RiwayatDokumen::namaBerkas('KAK+TOR', '!!!', null, 2025, false, 'docx'));
    }

    public function test_catat_menambah_versi_dan_tampil(): void
    {
        $repo = app(RiwayatDokumenRepository::class);
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $entri = RiwayatDokumen::entri($kegiatan, 'RAB', 'DIPA', 'pdf', 'unduh', 'RAB_x.pdf', 70_000, true, self::LEADING, ['total_rab' => 5_400_000]);

        $catatan = $repo->catat($entri);
        $this->assertSame(self::KUNCI, array_keys($catatan));
        $this->assertSame([2, 2, 'DIPA', 'draf', 1, 'Muslimin, M.Pd.'], [$catatan['versi'], $catatan['kegiatan_id'], $catatan['sumber'], $catatan['status'], $catatan['user_id'], $catatan['dibuat_oleh']], 'Versi melanjutkan rd-2026-1.');
        $this->assertSame(['total_rab' => 5_400_000, 'bagian_lengkap' => null, 'jumlah_bagian' => null], $catatan['ringkas']);
        $this->assertSame($catatan['id'], $repo->semua()[0]['id']);
        $this->assertCount(13, $repo->semua(), 'Contoh ikut tersimpan bersama catatan baru.');

        $tampil = RiwayatDokumen::tampil($catatan, [1, 2, 3]);
        $this->assertSame(['RAB', 'PDF', 'Diunduh', 'Draf', '68 KB', true], [$tampil['label_jenis'], $tampil['label_format'], $tampil['label_asal'], $tampil['label_status'], $tampil['ukuran_teks'], $tampil['tersedia']]);
        $this->assertNotNull($tampil['nama_leading']);
        $this->assertFalse(RiwayatDokumen::tampil($repo->temukan('rd-2025-3'), [1, 2, 3])['tersedia'], 'Kegiatan tahun lalu tidak tersimpan.');
        $this->assertSame('1,8 MB', RiwayatDokumen::tampil($repo->temukan('rd-2025-3'))['ukuran_teks']);
    }

    public function test_gagal_menulis_dilaporkan(): void
    {
        $repo = new class extends StubRiwayatDokumenRepository
        {
            protected function tulis(array $semua): bool
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $repo->catat(RiwayatDokumen::entri(['id' => 1, 'nama_kegiatan' => 'A', 'tahun_anggaran' => 2026], 'RAB', 'DIPA', 'pdf', 'unduh', 'a.pdf', null, true, self::LEADING));
    }
}
