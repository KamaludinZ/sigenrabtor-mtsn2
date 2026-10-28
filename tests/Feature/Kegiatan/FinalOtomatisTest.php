<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\PanelArsipFinal;
use App\Livewire\Notifikasi;
use App\Repositories\EloquentArsipFinalRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\FinalOtomatis;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class FinalOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    /** @param  list<array{sumber: string, pagu: int}>  $sumber */
    private function disetujui(array $sumber = [['sumber' => 'DIPA', 'pagu' => 2_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]]): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Supervisi Akademik',
            'leading' => '1.6',
            'user_id' => 1,
            'tahun_anggaran' => 2026,
            'sumber_dana' => $sumber,
            'status' => 'disetujui',
            'riwayat_status' => [
                RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING),
                RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN),
            ],
        ]);
    }

    private function pdf(string $nama): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, '%PDF-1.4 bertanda tangan');
    }

    #[DataProvider('penyimpanan')]
    public function test_final_begitu_arsip_semua_sumber_dana_lengkap(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
            $this->app->singleton(ArsipFinalRepository::class, EloquentArsipFinalRepository::class);
        }
        $repo = app(KegiatanRepository::class);
        $id = $this->disetujui();

        $dipa = KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa.pdf'), self::LEADING);
        $this->assertSame([false, 'disetujui', 'Arsip final RAB DIPA tersimpan.'], [$dipa['final'], $dipa['status'], $dipa['pesan']]);
        $this->assertSame(['Komite'], KelolaArsipFinal::data($repo->temukan($id), self::LEADING)['kurang_final_otomatis']);
        $this->assertNull(FinalOtomatis::jalankan($id, self::LEADING), 'Belum lengkap: tetap Disetujui.');

        $komite = KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite.pdf'), self::LEADING);
        $this->assertSame([true, 'final'], [$komite['final'], $komite['status']]);
        $kegiatan = $repo->temukan($id);
        $entri = end($kegiatan['riwayat_status']);
        $this->assertSame(['final', 'finalkan', 'final', 'Muslimin, M.Pd.', 'leading', 'Ditandai final otomatis: arsip final RAB DIPA dan RAB Komite sudah lengkap.'],
            [$kegiatan['status'], $entri['aksi'], $entri['status'], $entri['oleh'], $entri['peran'], $entri['catatan']]);
        $this->assertStringEndsWith('oleh Muslimin, M.Pd.', KunciDokumen::dari($kegiatan)->kalimat());
        if ($database) {
            $this->assertNotNull(DB::table('activities')->where('id', $id)->value('dikunci_pada'), 'Jejak kunci tersimpan.');
        }

        // Status tidak berubah lagi setelah Final.
        $this->assertNull(FinalOtomatis::jalankan($id, self::LEADING));
        $this->assertCount(3, $repo->temukan($id)['riwayat_status']);
    }

    public function test_arsip_putaran_sebelumnya_tidak_menfinalkan_sampai_diganti(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->disetujui();
        KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa-lama.pdf'), self::LEADING);
        KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite-lama.pdf'), self::LEADING);
        $this->assertSame('final', $repo->temukan($id)['status']);

        // Kunci dibuka, diperbaiki, diajukan ulang, lalu disetujui lagi.
        $this->travel(1)->minutes();
        KeputusanPemeriksaan::putuskan($id, 'buka_kunci', 'Salah nominal termin.', self::ADMIN);
        $k = $repo->temukan($id);
        $repo->simpan(['status' => 'diperiksa', 'riwayat_status' => [...$k['riwayat_status'], RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)]] + $k, $id);
        $this->travel(1)->minutes();
        KeputusanPemeriksaan::putuskan($id, 'setujui', '', self::ADMIN);

        $kegiatan = $repo->temukan($id);
        $arsip = app(ArsipFinalRepository::class)->untukKegiatan($id);
        $this->assertSame([], FinalOtomatis::berlaku($kegiatan, $arsip));
        $this->assertSame(['DIPA', 'Komite'], FinalOtomatis::kurang($kegiatan, $arsip));

        $this->travel(1)->minutes();
        $this->assertFalse(KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa-baru.pdf'), self::LEADING)['final'], 'Arsip Komite masih dari putaran lama.');
        $this->assertSame('disetujui', $repo->temukan($id)['status']);
        Livewire::test(PanelArsipFinal::class, ['kegiatanId' => $id])
            ->assertSeeHtml('data-testid="arsip-lama-komite"')
            ->assertDontSeeHtml('data-testid="arsip-lama-dipa"');

        $this->assertTrue(KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite-baru.pdf'), self::LEADING)['final']);
        $this->assertSame('final', $repo->temukan($id)['status']);
    }

    public function test_data_kegiatan_mengikuti_status_final_dan_admin_diberi_tahu(): void
    {
        $id = $this->disetujui([['sumber' => 'DIPA', 'pagu' => 2_000_000]]);

        $form = Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSet('status', 'disetujui')
            ->assertSeeHtml('data-testid="panel-arsip-final"');
        KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa.pdf'), self::LEADING);
        $form->call('arsipFinalBerubah')
            ->assertSet('status', 'final')
            ->assertSeeHtml('data-testid="banner-terkunci"')
            ->assertSee('Ditandai final otomatis: arsip final RAB DIPA sudah lengkap.');

        session(['stub_role' => 'admin']);
        Livewire::test(Notifikasi::class)->assertSee('Supervisi Akademik');

        $this->post(route('kegiatan.arsip.unggah', ['kegiatan' => $id, 'sumber' => 'DIPA']), ['berkas' => $this->pdf('lagi.pdf')], ['Accept' => 'application/json'])
            ->assertForbidden()->assertJsonPath('pesan', KelolaArsipFinal::PESAN_STATUS);
    }
}
