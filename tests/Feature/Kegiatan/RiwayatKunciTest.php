<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatKunciRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentArsipFinalRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentRiwayatKunciRepository;
use App\Repositories\EloquentRiwayatPengaturanRepository;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Kegiatan\KunciFinal;
use App\Support\Kegiatan\RiwayatKunci;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class RiwayatKunciTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** @param  array<string, mixed>  $isi */
    private function kegiatan(string $status = 'disetujui', array $isi = []): int
    {
        $riwayat = [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING), RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN)];
        if ($status === 'final') {
            $riwayat[] = RiwayatStatus::entri('finalkan', StatusKegiatan::Final, self::LEADING);
        }

        return app(KegiatanRepository::class)->simpan($isi + [
            'nama_kegiatan' => 'Supervisi Akademik',
            'leading' => '1.6',
            'user_id' => 1,
            'tahun_anggaran' => 2026,
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]],
            'status' => $status,
            'riwayat_status' => $riwayat,
        ]);
    }

    private function pdf(string $nama): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, '%PDF-1.4 bertanda tangan');
    }

    /** @return list<array{0: string, 1: string, 2: string}> [aksi, keterangan, oleh] */
    private function ringkas(int $id): array
    {
        return array_map(fn (array $e) => [$e['aksi'], $e['keterangan'], $e['oleh']], RiwayatKunci::untuk(app(KegiatanRepository::class)->temukan($id)));
    }

    #[DataProvider('penyimpanan')]
    public function test_unggah_kunci_dan_buka_tercatat_berurutan(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
            $this->app->singleton(ArsipFinalRepository::class, EloquentArsipFinalRepository::class);
            $this->app->singleton(RiwayatKunciRepository::class, EloquentRiwayatKunciRepository::class);
            $this->app->singleton(RiwayatPengaturanRepository::class, EloquentRiwayatPengaturanRepository::class);
        }
        $id = $this->kegiatan();
        $langkah = function (callable $aksi) {
            $this->travel(1)->seconds();
            $aksi();
        };

        $langkah(fn () => KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa.pdf'), self::LEADING));
        $langkah(fn () => KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa-revisi.pdf'), self::LEADING));
        $langkah(fn () => KelolaArsipFinal::hapus($id, 'DIPA', self::ADMIN));
        $langkah(fn () => KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa-ttd.pdf'), self::LEADING));
        $langkah(fn () => KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite.pdf'), self::LEADING));
        $langkah(fn () => KunciFinal::buka($id, 'Nominal termin salah.', self::ADMIN));

        $this->assertSame([
            ['buka_kunci', 'Nominal termin salah.', 'Koordinator PUSKOM'],
            ['kunci_final', 'Ditandai final otomatis: arsip final RAB DIPA dan RAB Komite sudah lengkap.', 'Muslimin, M.Pd.'],
            ['unggah_final', 'RAB Komite — komite.pdf (1 KB)', 'Muslimin, M.Pd.'],
            ['unggah_final', 'RAB DIPA — dipa-ttd.pdf (1 KB)', 'Muslimin, M.Pd.'],
            ['hapus_final', 'RAB DIPA — dipa-revisi.pdf (1 KB)', 'Koordinator PUSKOM'],
            ['ganti_final', 'RAB DIPA — dipa-revisi.pdf (1 KB)', 'Muslimin, M.Pd.'],
            ['unggah_final', 'RAB DIPA — dipa.pdf (1 KB)', 'Muslimin, M.Pd.'],
        ], $this->ringkas($id));
        $this->assertSame(['log'], array_values(array_unique(array_column(RiwayatKunci::untuk(app(KegiatanRepository::class)->temukan($id)), 'asal'))), 'Kunci & buka dari log, tidak dobel dengan riwayat_status.');

        if ($database) {
            $this->assertSame(7, DB::table('activity_logs')->where('activity_id', $id)->where('tahun_anggaran', 2026)->count());
            $this->assertSame([], app(RiwayatPengaturanRepository::class)->semua(), 'Riwayat pengaturan hanya baris tanpa kegiatan.');
        }
    }

    public function test_kunci_lama_dari_riwayat_status_dan_kunci_manual(): void
    {
        $lama = $this->kegiatan('final');
        $this->assertSame([['kunci_final', '', 'Muslimin, M.Pd.']], $this->ringkas($lama));
        $this->assertSame('riwayat_status', RiwayatKunci::untuk(app(KegiatanRepository::class)->temukan($lama))[0]['asal']);
        $this->travel(1)->seconds();
        KunciFinal::buka($lama, 'Salah ketik cover.', self::ADMIN);
        $this->assertSame([['buka_kunci', 'Salah ketik cover.', 'Koordinator PUSKOM'], ['kunci_final', '', 'Muslimin, M.Pd.']], $this->ringkas($lama));

        $tanpaSumber = $this->kegiatan(isi: ['sumber_dana' => []]);
        KunciFinal::kunci($tanpaSumber, self::ADMIN);
        $this->assertSame([['kunci_final', '', 'Koordinator PUSKOM']], $this->ringkas($tanpaSumber));
        $this->assertSame('log', RiwayatKunci::untuk(app(KegiatanRepository::class)->temukan($tanpaSumber))[0]['asal']);
    }

    public function test_endpoint_per_kegiatan_dan_semua_kegiatan(): void
    {
        $id = $this->kegiatan();
        KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa.pdf'), self::LEADING);
        $this->travel(1)->seconds();
        KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite.pdf'), self::LEADING);
        $lain = $this->kegiatan('final', ['leading' => '1.2', 'user_id' => 4, 'nama_kegiatan' => 'Pentas Seni']);
        $this->kegiatan('final', ['tahun_anggaran' => 2025, 'nama_kegiatan' => 'Tahun Lalu']);

        $this->getJson(route('kegiatan.riwayat_kunci', ['kegiatan' => $id]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $id, 'status' => 'final', 'terkunci' => true, 'jumlah' => 3])
            ->assertJsonPath('jejak.oleh', 'Muslimin, M.Pd.')
            ->assertJsonPath('riwayat.0.aksi', 'kunci_final')
            ->assertJsonPath('riwayat.0.label', 'Dikunci final');
        $this->getJson(route('kegiatan.riwayat_kunci', ['kegiatan' => $lain]))->assertForbidden();
        $this->getJson(route('kegiatan.riwayat_kunci', ['kegiatan' => 999]))->assertNotFound();
        $this->getJson(route('pemeriksaan.riwayat_kunci'))->assertForbidden();

        $this->withSession(['stub_role' => 'admin']);
        $semua = $this->getJson(route('pemeriksaan.riwayat_kunci'))->assertOk()
            ->assertJson(['tahun' => 2026, 'aksi' => '', 'jumlah' => 4, 'halaman' => 1, 'per_halaman' => RiwayatKunci::PER_HALAMAN])
            ->json('riwayat');
        $this->assertEqualsCanonicalizing([$id, $lain], array_unique(array_column(array_column($semua, 'kegiatan'), 'id')));
        $kunci = $this->getJson(route('pemeriksaan.riwayat_kunci', ['aksi' => 'kunci_final']))->assertJson(['aksi' => 'kunci_final', 'jumlah' => 2])->json('riwayat');
        $this->assertSame(['Pentas Seni', 'Supervisi Akademik'], array_column(array_column($kunci, 'kegiatan'), 'nama'), 'Terbaru dulu.');
        $this->getJson(route('pemeriksaan.riwayat_kunci', ['tahun' => 2025]))->assertJson(['tahun' => 2025, 'jumlah' => 1])->assertJsonPath('riwayat.0.kegiatan.nama', 'Tahun Lalu');
        $this->getJson(route('pemeriksaan.riwayat_kunci', ['aksi' => 'acak', 'hal' => 2]))->assertJson(['aksi' => '', 'halaman' => 2, 'riwayat' => []]);
    }
}
