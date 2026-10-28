<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentArsipFinalRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class KelolaArsipFinalTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(ArsipFinalRepository::class, EloquentArsipFinalRepository::class);
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
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000], ['sumber' => 'Komite', 'pagu' => 1_000_000]],
            'status' => $status,
            'riwayat_status' => $status === 'draf' ? [] : $riwayat,
        ]);
    }

    private function pdf(string $nama = 'arsip-dipa.pdf', string $isi = '%PDF-1.4 bertanda tangan'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    #[DataProvider('penyimpanan')]
    public function test_unggah_ganti_dan_hapus_arsip(bool $database): void
    {
        $database && $this->pakaiDatabase();
        $id = $this->kegiatan();
        $repo = app(ArsipFinalRepository::class);

        $hasil = KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf(), self::LEADING);
        $this->assertSame([false, ['Komite'], 'Arsip final RAB DIPA tersimpan.'], [$hasil['ganti'], $hasil['kurang'], $hasil['pesan']]);
        $this->assertSame(['DIPA', 'arsip-dipa.pdf', 24, '1 KB', 'Muslimin, M.Pd.', route('kegiatan.arsip', ['kegiatan' => $id, 'sumber' => 'DIPA'])],
            [$hasil['arsip']['sumber'], $hasil['arsip']['nama_berkas'], $hasil['arsip']['ukuran'], $hasil['arsip']['ukuran_teks'], $hasil['arsip']['diunggah_oleh'], $hasil['arsip']['tautan']]);
        $lama = $repo->untukKegiatan($id)['DIPA'];
        $this->assertSame(1, $lama['diunggah_oleh_id']);
        Storage::disk('local')->assertExists($lama['path']);

        $ganti = KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('dipa-revisi.pdf'), self::ADMIN);
        $this->assertSame([true, 'Arsip final RAB DIPA diganti.'], [$ganti['ganti'], $ganti['pesan']]);
        Storage::disk('local')->assertMissing($lama['path']);
        $this->assertSame(['dipa-revisi.pdf', 'Koordinator PUSKOM'], [$repo->untukKegiatan($id)['DIPA']['nama_berkas'], $repo->untukKegiatan($id)['DIPA']['diunggah_oleh']]);

        $path = $repo->untukKegiatan($id)['DIPA']['path'];
        $this->assertSame(['kurang' => ['DIPA', 'Komite'], 'pesan' => 'Arsip final RAB DIPA dihapus.'], KelolaArsipFinal::hapus($id, 'DIPA', self::LEADING));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], $repo->untukKegiatan($id));

        // Melengkapi arsip semua sumber dana langsung memfinalkan kegiatan (FinalOtomatis).
        $this->assertFalse(KelolaArsipFinal::unggah($id, 'Komite', $this->pdf('komite.pdf'), self::LEADING)['final']);
        $lengkap = KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf(), self::LEADING);
        $this->assertSame([true, 'final', 'Arsip final RAB DIPA tersimpan. '.KelolaArsipFinal::PESAN_FINAL], [$lengkap['final'], $lengkap['status'], $lengkap['pesan']]);
        $data = KelolaArsipFinal::data(app(KegiatanRepository::class)->temukan($id), self::LEADING);
        $this->assertSame([false, KelolaArsipFinal::PESAN_STATUS, [], true, [], ['DIPA', 'Komite']], [$data['boleh_unggah'], $data['alasan'], $data['kurang'], $data['lengkap'], $data['kurang_final_otomatis'], array_column($data['sumber'], 'sumber')]);
        $this->assertArrayNotHasKey('path', $data['sumber'][0]['arsip'], 'Path penyimpanan tidak dibuka.');
        $this->assertTrue($data['sumber'][0]['arsip']['berlaku']);
        $this->assertSame(['DIPA', 'Komite'], array_keys($repo->untukKegiatan($id)), 'Urut sumber dana baku.');
    }

    public function test_aturan_unggah_dan_hapus(): void
    {
        $id = $this->kegiatan();
        $galat = function (callable $aksi): array {
            try {
                $aksi();
            } catch (ValidationException $e) {
                return array_map(fn ($p) => $p[0], $e->errors());
            }
            $this->fail('Seharusnya ditolak.');
        };
        $tolak = function (callable $aksi): string {
            try {
                $aksi();
            } catch (AuthorizationException $e) {
                return $e->getMessage();
            }
            $this->fail('Seharusnya ditolak.');
        };

        $this->assertSame(['berkas' => 'Pilih berkas PDF hasil pindaian.'], $galat(fn () => KelolaArsipFinal::unggah($id, 'DIPA', null, self::LEADING)));
        $this->assertSame(['berkas' => 'Arsip final harus berupa berkas PDF.'], $galat(fn () => KelolaArsipFinal::unggah($id, 'DIPA', UploadedFile::fake()->image('foto.jpg'), self::LEADING)));
        $this->assertSame(['berkas' => 'Berkas ini bukan PDF yang sah — simpan ulang hasil pindaian sebagai PDF.'], $galat(fn () => KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf('palsu.pdf', 'bukan pdf'), self::LEADING)));
        $this->assertStringStartsWith('Sumber dana BOS tidak dipilih', $galat(fn () => KelolaArsipFinal::unggah($id, 'BOS', $this->pdf(), self::LEADING))['sumber']);
        $this->assertSame(['sumber' => 'Belum ada arsip final RAB DIPA.'], $galat(fn () => KelolaArsipFinal::hapus($id, 'DIPA', self::LEADING)));

        $this->assertSame(KelolaArsipFinal::PESAN_STATUS, $tolak(fn () => KelolaArsipFinal::unggah($this->kegiatan('diperiksa'), 'DIPA', $this->pdf(), self::LEADING)));
        $final = $this->kegiatan('final');
        $this->assertSame(KelolaArsipFinal::PESAN_STATUS, $tolak(fn () => KelolaArsipFinal::hapus($final, 'DIPA', self::ADMIN)), 'Selama Final arsip hanya dilihat.');
        $this->assertSame('Tahun Anggaran 2025 terkunci — kegiatan tahun itu hanya bisa dilihat.', $tolak(fn () => KelolaArsipFinal::unggah($this->kegiatan(isi: ['tahun_anggaran' => 2025]), 'DIPA', $this->pdf(), self::LEADING)));
        $lain = ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'role' => 'leading', 'leadings' => ['1.2']];
        $this->assertSame(AksesKegiatan::PESAN_TOLAK, $tolak(fn () => KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf(), $lain)));
        $this->assertSame([], app(ArsipFinalRepository::class)->untukKegiatan($id));

        $this->expectException(\RuntimeException::class);
        KelolaArsipFinal::unggah(999, 'DIPA', $this->pdf(), self::LEADING);
    }

    public function test_tabel_menjaga_isi_dan_pasangan_anggaran(): void
    {
        $this->pakaiDatabase();
        $id = $this->kegiatan();
        $lain = $this->kegiatan();
        KelolaArsipFinal::unggah($id, 'DIPA', $this->pdf(), self::LEADING);
        $baris = (array) DB::table('arsip_final')->where('activity_id', $id)->first();
        $komiteLain = DB::table('anggaran_kegiatan')->where('activity_id', $lain)->where('sumber_dana', 'Komite')->value('id');

        $isi = ['activity_id' => $id, 'sumber_dana' => 'Komite', 'file_path' => 'arsip-final/x.pdf', 'nama_berkas' => 'x.pdf', 'ukuran' => 10, 'nama_pengunggah' => 'Admin'];
        foreach ([
            'arsip_final_anggaran_foreign' => ['anggaran_kegiatan_id' => $komiteLain] + $isi,
            'arsip_final_isian_check' => ['anggaran_kegiatan_id' => DB::table('anggaran_kegiatan')->where('activity_id', $id)->where('sumber_dana', 'Komite')->value('id'), 'ukuran' => 0] + $isi,
            'arsip_final_anggaran_kegiatan_id_unique' => ['anggaran_kegiatan_id' => $baris['anggaran_kegiatan_id'], 'sumber_dana' => 'DIPA'] + $isi,
        ] as $batasan => $data) {
            try {
                DB::transaction(fn () => DB::table('arsip_final')->insert($data));
                $this->fail("{$batasan} seharusnya menolak.");
            } catch (QueryException $e) {
                $this->assertStringContainsString($batasan, $e->getMessage());
            }
        }

        DB::table('activities')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('arsip_final')->count(), 'Ikut terhapus bersama kegiatan.');
    }

    public function test_endpoint_baca_unggah_dan_hapus(): void
    {
        $id = $this->kegiatan();
        $this->getJson(route('kegiatan.arsip.data', ['kegiatan' => $id]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kegiatan_id' => $id, 'status' => 'disetujui', 'label_status' => 'Disetujui', 'boleh_unggah' => true, 'alasan' => null, 'kurang' => ['DIPA', 'Komite'], 'lengkap' => false])
            ->assertJsonPath('sumber.0', ['sumber' => 'DIPA', 'arsip' => null]);
        $this->getJson(route('kegiatan.arsip.data', ['kegiatan' => 999]))->assertNotFound();

        $unggah = route('kegiatan.arsip.unggah', ['kegiatan' => $id, 'sumber' => 'DIPA']);
        $this->post($unggah, ['berkas' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated()
            ->assertJson(['ganti' => false, 'kurang' => ['Komite'], 'pesan' => 'Arsip final RAB DIPA tersimpan.'])
            ->assertJsonPath('arsip.nama_berkas', 'arsip-dipa.pdf');
        $this->post($unggah, ['berkas' => $this->pdf('ganti.pdf')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ganti', true);
        $this->post($unggah, ['berkas' => $this->pdf('palsu.pdf', 'bukan')], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['galat' => ['berkas']]);
        $this->postJson(route('kegiatan.arsip.unggah', ['kegiatan' => 999, 'sumber' => 'DIPA']))->assertNotFound();
        $this->post(route('kegiatan.arsip.unggah', ['kegiatan' => $this->kegiatan('draf'), 'sumber' => 'DIPA']), ['berkas' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertForbidden()->assertJsonPath('pesan', KelolaArsipFinal::PESAN_STATUS);

        $this->getJson(route('kegiatan.arsip', ['kegiatan' => $id, 'sumber' => 'DIPA']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $hapus = route('kegiatan.arsip.hapus', ['kegiatan' => $id, 'sumber' => 'DIPA']);
        $this->deleteJson($hapus)->assertOk()->assertJson(['kurang' => ['DIPA', 'Komite'], 'pesan' => 'Arsip final RAB DIPA dihapus.']);
        $this->deleteJson($hapus)->assertStatus(422)->assertJsonPath('galat.sumber.0', 'Belum ada arsip final RAB DIPA.');

        $milikLain = $this->kegiatan(isi: ['leading' => '1.2', 'user_id' => 4]);
        $this->getJson(route('kegiatan.arsip.data', ['kegiatan' => $milikLain]))->assertForbidden();
        $this->post(route('kegiatan.arsip.unggah', ['kegiatan' => $milikLain, 'sumber' => 'DIPA']), ['berkas' => $this->pdf()], ['Accept' => 'application/json'])->assertForbidden();
    }
}
