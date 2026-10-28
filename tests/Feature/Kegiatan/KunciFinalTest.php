<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentArsipFinalRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\KunciFinal;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KunciFinalTest extends TestCase
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
        $riwayat = [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)];
        if (in_array($status, ['disetujui', 'final'], true)) {
            $riwayat[] = RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN);
        }
        if ($status === 'final') {
            $riwayat[] = RiwayatStatus::entri('finalkan', StatusKegiatan::Final, self::LEADING);
        }

        return app(KegiatanRepository::class)->simpan($isi + [
            'nama_kegiatan' => 'Supervisi Akademik',
            'leading' => '1.6',
            'user_id' => 1,
            'tahun_anggaran' => 2026,
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]],
            'status' => $status,
            'riwayat_status' => $status === 'draf' ? [] : $riwayat,
        ]);
    }

    private function arsip(int $id): void
    {
        // Langsung lewat repositori: unggahan lewat KelolaArsipFinal sudah memfinalkan otomatis.
        app(ArsipFinalRepository::class)->simpan($id, 'DIPA', UploadedFile::fake()->createWithContent('dipa.pdf', '%PDF-1.4 ttd'), self::LEADING);
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    private function tolak(callable $aksi): string
    {
        try {
            $aksi();
        } catch (AuthorizationException $e) {
            return $e->getMessage();
        }
        $this->fail('Seharusnya ditolak.');
    }

    #[DataProvider('penyimpanan')]
    public function test_kunci_lalu_buka_kunci(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
            $this->app->singleton(ArsipFinalRepository::class, EloquentArsipFinalRepository::class);
        }
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatan();

        $data = KunciFinal::data($repo->temukan($id), self::LEADING);
        $this->assertSame([false, null, false, ['DIPA']], [$data['terkunci'], $data['jejak'], $data['kunci']['tersedia'], $data['kunci']['arsip_kurang']]);
        $this->assertSame(['arsip' => ArsipFinal::pesanKurang(['DIPA'])], $this->galat(fn () => KunciFinal::kunci($id, self::LEADING)));

        $this->arsip($id);
        $versi = KunciFinal::versi($repo->temukan($id));
        $hasil = KunciFinal::kunci($id, self::LEADING, $versi);
        $this->assertSame(['final', 'finalkan', 'Muslimin, M.Pd.', KunciFinal::PESAN_KUNCI], [$hasil['kegiatan']['status'], $hasil['entri']['aksi'], $hasil['entri']['oleh'], $hasil['pesan']]);
        $this->assertNotSame($versi, $hasil['versi']);
        $data = KunciFinal::data($repo->temukan($id), self::ADMIN);
        $this->assertTrue($data['terkunci']);
        $this->assertSame('Muslimin, M.Pd.', $data['jejak']['oleh']);
        $this->assertSame([false, true], [$data['kunci']['tersedia'], $data['buka']['tersedia']]);
        if ($database) {
            $this->assertNotNull(DB::table('activities')->where('id', $id)->value('dikunci_pada'));
        }

        $this->assertSame('Hanya admin yang dapat membuka kunci kegiatan final.', $this->tolak(fn () => KunciFinal::buka($id, 'Alasan.', self::LEADING)));
        $this->assertSame(['catatan' => RiwayatStatus::CATATAN_WAJIB['buka_kunci']], $this->galat(fn () => KunciFinal::buka($id, '  ', self::ADMIN)));
        $buka = KunciFinal::buka($id, 'Nama narasumber salah ketik.', self::ADMIN, $hasil['versi']);
        $this->assertSame(['dikembalikan', 'buka_kunci', 'Nama narasumber salah ketik.'], [$buka['kegiatan']['status'], $buka['entri']['aksi'], $buka['entri']['catatan']]);
        $this->assertArrayHasKey('sidik', $buka['entri']);
        $this->assertSame(['DIPA'], array_keys(app(ArsipFinalRepository::class)->untukKegiatan($id)), 'Arsip lama tetap tersimpan.');
        if ($database) {
            $this->assertNull(DB::table('activities')->where('id', $id)->value('dikunci_pada'));
        }
    }

    public function test_aturan_kunci_dan_buka(): void
    {
        $this->assertSame('Kegiatan berstatus Draf — hanya kegiatan Disetujui yang bisa dikunci final.', $this->tolak(fn () => KunciFinal::kunci($this->kegiatan('draf'), self::LEADING)));
        $lalu = $this->kegiatan(isi: ['tahun_anggaran' => 2025]);
        $this->assertSame('Tahun Anggaran 2025 terkunci — kegiatan tahun itu hanya bisa dilihat.', $this->tolak(fn () => KunciFinal::kunci($lalu, self::LEADING)));
        $lain = ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'role' => 'leading', 'leadings' => ['1.2']];
        $id = $this->kegiatan();
        $this->assertSame(AksesKegiatan::PESAN_TOLAK, $this->tolak(fn () => KunciFinal::kunci($id, $lain)));
        $this->assertSame('Kegiatan berstatus Disetujui — tidak sedang terkunci final.', $this->tolak(fn () => KunciFinal::buka($id, 'Alasan.', self::ADMIN)));

        // Tanpa sumber dana tidak ada arsip wajib.
        $tanpaSumber = $this->kegiatan(isi: ['sumber_dana' => []]);
        $this->assertSame('final', KunciFinal::kunci($tanpaSumber, self::ADMIN)['kegiatan']['status']);

        $this->arsip($id);
        $versi = KunciFinal::versi(app(KegiatanRepository::class)->temukan($id));
        KeputusanPemeriksaan::putuskan($id, 'kembalikan', 'Perbaiki sasaran.', self::ADMIN);
        $this->expectException(PengaturanBerubah::class);
        KunciFinal::kunci($id, self::LEADING, $versi);
    }

    public function test_endpoint_baca_kunci_dan_buka(): void
    {
        $id = $this->kegiatan();
        $baca = route('kegiatan.kunci', ['kegiatan' => $id]);
        $pasang = route('kegiatan.kunci.pasang', ['kegiatan' => $id]);
        $buka = route('kegiatan.kunci.buka', ['kegiatan' => $id]);

        $this->getJson($baca)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $id, 'status' => 'disetujui', 'terkunci' => false, 'jejak' => null, 'kunci' => ['tersedia' => false, 'arsip_kurang' => ['DIPA']], 'buka' => ['tersedia' => false, 'alasan_wajib' => true]]);
        $this->postJson($pasang)->assertStatus(422)->assertJsonPath('galat.arsip.0', ArsipFinal::pesanKurang(['DIPA']));
        $this->arsip($id);
        $this->postJson($pasang, ['versi' => 'usang'])->assertStatus(409);
        $this->postJson($pasang)->assertOk()
            ->assertJson(['id' => $id, 'status' => 'final', 'label_status' => 'Final', 'entri' => ['aksi' => 'finalkan'], 'pesan' => KunciFinal::PESAN_KUNCI])
            ->assertJsonPath('jejak.oleh', 'Muslimin, M.Pd.');
        $this->postJson($pasang)->assertForbidden();
        $this->deleteJson($buka, ['alasan' => 'Salah.'])->assertForbidden()->assertJsonPath('pesan', 'Hanya admin yang dapat membuka kunci kegiatan final.');

        $this->withSession(['stub_role' => 'admin']);
        $this->deleteJson($buka)->assertStatus(422)->assertJsonPath('galat.alasan.0', RiwayatStatus::CATATAN_WAJIB['buka_kunci']);
        $this->deleteJson($buka, ['alasan' => 'Nominal termin salah.'])->assertOk()
            ->assertJson(['status' => 'dikembalikan', 'jejak' => null, 'entri' => ['aksi' => 'buka_kunci', 'catatan' => 'Nominal termin salah.']]);

        $this->getJson(route('kegiatan.kunci', ['kegiatan' => 999]))->assertNotFound();
        $this->postJson(route('kegiatan.kunci.pasang', ['kegiatan' => 999]))->assertNotFound();
        $this->withSession(['stub_role' => 'leading']);
        $milikLain = $this->kegiatan(isi: ['leading' => '1.2', 'user_id' => 4]);
        $this->getJson(route('kegiatan.kunci', ['kegiatan' => $milikLain]))->assertForbidden();
        $this->postJson(route('kegiatan.kunci.pasang', ['kegiatan' => $milikLain]))->assertForbidden();
    }
}
