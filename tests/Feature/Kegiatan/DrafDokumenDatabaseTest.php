<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\DrafDokumen;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class DrafDokumenDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const KUNCI = ['id', 'kegiatan_id', 'jenis', 'sumber', 'status', 'bertanda_draf', 'alasan_draf', 'lengkap', 'jumlah_bagian', 'total_rab', 'sidik', 'user_id', 'oleh', 'disimpan_pada'];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(DrafDokumenRepository::class, EloquentDrafDokumenRepository::class);
    }

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]]]);
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_timpa_hapus_dan_keadaan(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $pertama = DrafDokumen::simpan($id, 'lengkap', null, self::LEADING)['draf'];
        $this->assertSame(self::KUNCI, array_keys($pertama));
        $this->assertSame([$id, 'lengkap', 'Komite', 'draf', true, 1, 'Muslimin, M.Pd.', 9], [$pertama['kegiatan_id'], $pertama['jenis'], $pertama['sumber'], $pertama['status'], $pertama['bertanda_draf'], $pertama['user_id'], $pertama['oleh'], $pertama['jumlah_bagian']]);
        $this->assertSame(['cover', 'kak', 'rab'], array_keys($pertama['sidik']));
        $this->assertSame(range(1, 9), array_keys($pertama['sidik']['kak']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $pertama['disimpan_pada']);
        $this->assertSame($pertama, app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        $this->assertSame('sesuai', DrafDokumen::keadaanUntuk($repo->temukan($id), $pertama)['keadaan'], 'Sidik tersimpan utuh.');

        $this->travel(1)->seconds();
        $kedua = DrafDokumen::simpan($id, 'lengkap', 'Komite', self::LEADING);
        $this->assertSame([false, $pertama['id']], [$kedua['baru'], $kedua['draf']['id']]);
        $this->assertGreaterThan($pertama['disimpan_pada'], $kedua['draf']['disimpan_pada']);
        $this->travel(1)->seconds();
        DrafDokumen::simpan($id, 'RAB', 'Komite', self::LEADING);
        $this->assertSame(['RAB', 'lengkap'], array_column(app(DrafDokumenRepository::class)->untukKegiatan($id), 'jenis'));

        $repo->simpan(['tempat' => 'Aula Utama MTsN 2 Kota Malang'] + $repo->temukan($id), $id);
        $this->assertSame(['kak-5'], array_column(DrafDokumen::keadaanUntuk($repo->temukan($id), $kedua['draf'])['berubah'], 'kunci'));

        try {
            DrafDokumen::hapus($id, 'lengkap', 'Komite', self::LEADING, $pertama['disimpan_pada']);
            $this->fail('Versi usang harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertNotNull(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        }
        $this->assertTrue(DrafDokumen::hapus($id, 'lengkap', 'Komite', self::LEADING, $kedua['draf']['disimpan_pada']));
        $this->assertFalse(app(DrafDokumenRepository::class)->hapus($id, 'lengkap', 'Komite'));
        $this->assertSame(['RAB'], array_column(app(DrafDokumenRepository::class)->untukKegiatan($id), 'jenis'));

        // Kegiatan tanpa sumber dana: draf KAK/TOR tanpa sumber.
        $tanpa = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Tanpa Dana', 'sumber_dana' => []]);
        $draf = DrafDokumen::simpan($tanpa, 'KAK+TOR', null, self::LEADING)['draf'];
        $this->assertSame([null, null], [$draf['sumber'], $draf['total_rab']]);
        $this->assertSame($draf['id'], app(DrafDokumenRepository::class)->temukan($tanpa, 'KAK+TOR', null)['id']);
    }

    public function test_baris_documents_dan_batasannya(): void
    {
        $this->pakaiDatabase();
        $id = $this->kegiatanBaru();
        $draf = DrafDokumen::simpan($id, 'lengkap', null, self::LEADING)['draf'];

        $baris = DB::table('documents')->where('id', (int) $draf['id'])->first();
        $anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'Komite')->value('id');
        $this->assertSame([$id, 2026, $anggaran, 'Komite', 'lengkap', 'draf', true], [(int) $baris->activity_id, (int) $baris->tahun_anggaran, $baris->anggaran_kegiatan_id, $baris->sumber_dana, $baris->jenis, $baris->status, (bool) $baris->bertanda_draf]);
        $konten = json_decode($baris->konten, true);
        $this->assertSame(['draf'], array_keys($konten));
        $this->assertSame('Muslimin, M.Pd.', $konten['draf']['oleh']);

        // Isi lain di konten & nomor dokumen dipertahankan; hapus draf hanya membuang konten.draf.
        DB::table('documents')->where('id', $baris->id)->update(['nomor_dokumen' => 'KAK/001/2026', 'konten' => json_encode(['draf' => $konten['draf'], 'sunting' => ['tujuan' => 'x']])]);
        $this->travel(1)->seconds();
        DrafDokumen::simpan($id, 'lengkap', null, self::LEADING);
        $this->assertSame(['draf', 'sunting'], array_keys(json_decode(DB::table('documents')->where('id', $baris->id)->value('konten'), true)));
        $this->assertTrue(app(DrafDokumenRepository::class)->hapus($id, 'lengkap', 'Komite'));
        $sisa = DB::table('documents')->where('id', $baris->id)->first();
        $this->assertSame(['KAK/001/2026', ['sunting' => ['tujuan' => 'x']]], [$sisa->nomor_dokumen, json_decode($sisa->konten, true)]);
        $this->assertNull(app(DrafDokumenRepository::class)->temukan($id, 'lengkap', 'Komite'));
        $this->assertSame([], app(DrafDokumenRepository::class)->untukKegiatan($id));

        // Baris tanpa isi lain ikut dihapus bersama drafnya; kegiatan dihapus → dokumennya ikut terhapus.
        $rab = DrafDokumen::simpan($id, 'RAB', 'Komite', self::LEADING)['draf'];
        app(DrafDokumenRepository::class)->hapus($id, 'RAB', 'Komite');
        $this->assertFalse(DB::table('documents')->where('id', (int) $rab['id'])->exists());
        DrafDokumen::simpan($id, 'KAK', null, self::LEADING);
        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('documents')->where('activity_id', $id)->count());

        // Penolakan: sumber tanpa baris anggaran, status di luar StatusKegiatan, kegiatan tak ada.
        $lain = $this->kegiatanBaru(['nama_kegiatan' => 'Rapat Lain']);
        $repo = app(DrafDokumenRepository::class);
        $rekam = DrafDokumen::rekam(app(KegiatanRepository::class)->temukan($lain), 'RAB', 'Komite', self::LEADING);
        foreach ([
            [['sumber' => 'DIPA'] + $rekam, $lain, 'Sumber dana DIPA belum tersimpan untuk kegiatan ini.'],
            [['status' => 'asing'] + $rekam, $lain, EloquentDrafDokumenRepository::PESAN_GAGAL],
            [$rekam, 999, 'Kegiatan tidak ditemukan.'],
        ] as [$isi, $kegiatan, $pesan]) {
            try {
                $repo->simpan($kegiatan, $isi);
                $this->fail("Harus ditolak: {$pesan}");
            } catch (RuntimeException $e) {
                $this->assertSame($pesan, $e->getMessage());
            }
        }
        $this->assertSame([], $repo->untukKegiatan($lain));
    }

    public function test_endpoint_draf_dengan_database(): void
    {
        $this->pakaiDatabase();
        $id = $this->kegiatanBaru();

        $baru = $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertCreated()->assertJsonPath('draf.keadaan.keadaan', 'sesuai');
        $this->putJson(route('kegiatan.draf.simpan', ['kegiatan' => $id]), ['jenis' => 'lengkap'])->assertOk()->assertJsonPath('draf.id', $baru->json('draf.id'));
        $data = $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))->assertOk()->assertJsonCount(1, 'draf');
        $this->deleteJson(route('kegiatan.draf.hapus', ['kegiatan' => $id]), ['jenis' => 'lengkap', 'sumber' => 'Komite', 'versi' => $data->json('draf.0.versi')])->assertOk();
        $this->getJson(route('kegiatan.draf', ['kegiatan' => $id]))->assertOk()->assertJsonCount(0, 'draf');
        $this->assertSame(0, DB::table('documents')->where('activity_id', $id)->count());
    }
}
