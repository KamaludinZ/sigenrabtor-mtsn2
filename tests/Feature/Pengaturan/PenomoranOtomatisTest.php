<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenomoranDokumenRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentPenomoranDokumenRepository;
use App\Support\Dokumen\CatatRiwayatDokumen;
use App\Support\Dokumen\PenomoranDokumen;
use App\Support\Dokumen\PerbaruiRiwayatDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class PenomoranOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function repo(bool $database): PenomoranDokumenRepository
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(PenomoranDokumenRepository::class, EloquentPenomoranDokumenRepository::class);
        }

        return app(PenomoranDokumenRepository::class);
    }

    #[DataProvider('penyimpanan')]
    public function test_repositori_aturan_dan_penghitung_atomik(bool $database): void
    {
        $repo = $this->repo($database);
        $this->assertSame([], $repo->aturan(2025));
        $this->assertNull($repo->meta(2025)['diperbarui_pada']);

        $repo->simpanAturan(2025, ['KAK' => ['pola' => 'KAK/{urut}/{tahun}', 'digit' => 4, 'aktif' => true], 'TOR' => ['pola' => 'TOR/{urut}', 'digit' => 2, 'aktif' => false]]);
        $this->assertSame(['KAK' => ['pola' => 'KAK/{urut}/{tahun}', 'digit' => 4, 'aktif' => true], 'TOR' => ['pola' => 'TOR/{urut}', 'digit' => 2, 'aktif' => false]], $repo->aturan(2025));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+07:00$/', $repo->meta(2025)['diperbarui_pada']);

        $this->assertSame([1, 2], [$repo->ambilBerikutnya(2025, 'TOR'), $repo->ambilBerikutnya(2025, 'TOR')]);
        $repo->catatPakai(2025, 'TOR', 10);
        $repo->catatPakai(2025, 'TOR', 5);
        $this->assertSame(10, $repo->terakhir(2025)['TOR']);
        $this->assertSame(11, $repo->ambilBerikutnya(2025, 'TOR'));

        if ($database) {
            $this->expectException(RuntimeException::class);
            $repo->simpanAturan(2025, ['KAK' => ['pola' => 'KAK/{tahun}', 'digit' => 3, 'aktif' => true]]);
        }
    }

    public function test_terbitkan_memajukan_penghitung_dan_melewati_nomor_terpakai(): void
    {
        // Contoh tiruan: nomor terakhir KAK TA 2026 = 4, pola bawaan KAK/{urut}/{tahun}.
        $this->assertSame('KAK/005/2026', PenomoranDokumen::usulan(['jenis' => 'KAK', 'tahun_anggaran' => 2026]));
        app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri(app(KegiatanRepository::class)->temukan(1), 'KAK', null, 'pdf', 'cetak', 'KAK.pdf', 100, false, self::LEADING, [], 'kak/005/2026'));

        $this->assertSame(['nomor' => 'KAK/006/2026', 'urut' => 6], PenomoranDokumen::terbitkan(['jenis' => 'KAK', 'tahun' => 2026]), 'Nomor 005 sudah dipakai (tanpa beda huruf) → dilewati.');
        $this->assertSame(['nomor' => 'KAK/007/2026', 'urut' => 7], PenomoranDokumen::terbitkan(['jenis' => 'KAK', 'tahun' => 2026]));
        $this->assertSame(7, app(PenomoranDokumenRepository::class)->terakhir(2026)['KAK']);
        $this->assertNull(PenomoranDokumen::terbitkan(['jenis' => 'TOR', 'tahun' => 2026]), 'TOR tidak dinomori otomatis (bawaan).');
    }

    public function test_beri_nomor_usulan_diterbitkan_nomor_sendiri_tidak_memajukan(): void
    {
        $repo = app(PenomoranDokumenRepository::class);
        $c = RiwayatDokumen::semua();
        $rab = collect($c)->firstWhere('id', 'rd-2026-1');
        $usulan = PenomoranDokumen::usulan($rab);
        $this->assertSame('RAB/013/DIPA/1.6/2026', $usulan);

        $hasil = PerbaruiRiwayatDokumen::beriNomor('rd-2026-1', $usulan, self::ADMIN);
        $this->assertSame($usulan, $hasil['dokumen']['nomor_dokumen']);
        $this->assertSame(13, $repo->terakhir(2026)['RAB']);

        PerbaruiRiwayatDokumen::beriNomor('rd-2026-6', 'RAB/KHUSUS/1', self::ADMIN);
        $this->assertSame(13, $repo->terakhir(2026)['RAB'], 'Nomor ketikan sendiri tidak memajukan penghitung.');
    }

    public function test_arsip_final_dicatat_bernomor_otomatis(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan([
            'nama_kegiatan' => 'Supervisi Akademik', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026,
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]],
            'status' => StatusKegiatan::Disetujui->value,
            'riwayat_status' => [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING), RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN)],
        ]);
        $pdf = fn (string $n) => UploadedFile::fake()->createWithContent($n, '%PDF-1.4 bertanda tangan');

        $dipa = KelolaArsipFinal::unggah($id, 'DIPA', $pdf('dipa.pdf'), self::LEADING);
        $this->assertSame('DOK/008/1.6/2026', $dipa['nomor_dokumen']);
        $this->assertStringContainsString('dengan nomor DOK/008/1.6/2026', $dipa['pesan']);
        $catatan = collect(RiwayatDokumen::semua())->firstWhere('id', $dipa['riwayat_dokumen']);
        $this->assertSame(['arsip', 'lengkap', 'DIPA', 'pdf', 'disetujui'], [$catatan['asal'], $catatan['jenis'], $catatan['sumber'], $catatan['format'], $catatan['status']]);

        // Unggah ulang = dokumen yang sama: nomor tetap, versi bertambah, penghitung tidak maju.
        $ulang = KelolaArsipFinal::unggah($id, 'DIPA', $pdf('dipa-2.pdf'), self::LEADING);
        $this->assertSame('DOK/008/1.6/2026', $ulang['nomor_dokumen']);
        $this->assertSame(2, collect(RiwayatDokumen::semua())->firstWhere('id', $ulang['riwayat_dokumen'])['versi']);

        // Arsip terakhir menjadikan kegiatan Final; catatannya berstatus final.
        $komite = KelolaArsipFinal::unggah($id, 'Komite', $pdf('komite.pdf'), self::LEADING);
        $this->assertSame(['DOK/009/1.6/2026', true], [$komite['nomor_dokumen'], $komite['final']]);
        $this->assertSame('final', collect(RiwayatDokumen::semua())->firstWhere('id', $komite['riwayat_dokumen'])['status']);
        $this->assertSame(9, app(PenomoranDokumenRepository::class)->terakhir(2026)['lengkap']);

        // Pencatatan dimatikan (E2E): tidak ada catatan maupun nomor yang diterbitkan.
        config(['sigenrabtor.riwayat.catat_unduhan' => false]);
        $this->assertNull(CatatRiwayatDokumen::dariArsip($repo->temukan($id), 'DIPA', ['ukuran' => 10], self::LEADING));
        $this->assertSame(9, app(PenomoranDokumenRepository::class)->terakhir(2026)['lengkap']);
    }
}
