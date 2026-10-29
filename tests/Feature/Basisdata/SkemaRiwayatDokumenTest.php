<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Models\BerkasDokumenTercatat;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\RiwayatDokumen;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaRiwayatDokumenTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_29_000043_create_riwayat_dokumen_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
    }

    private function ditolak(callable $aksi, string $pesan): void
    {
        try {
            DB::transaction(function () use ($aksi) {
                $aksi();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    private function kegiatan(): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 5_000_000]]]);
    }

    /** @param  array<string, mixed>  $isi */
    private function catatan(array $isi = []): string
    {
        $id = $isi['id'] ?? (string) Str::uuid();
        DB::table('riwayat_dokumen')->insert(['id' => $id] + $isi + [
            'activity_id' => null, 'tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'nama_kegiatan' => 'Rapat Kerja Madrasah',
            'kunci_versi' => 'x:rapat kerja madrasah|RAB|DIPA|pdf', 'jenis' => 'RAB', 'sumber_dana' => 'DIPA', 'format' => 'pdf', 'asal' => 'unduh',
            'nama_berkas' => 'RAB_Rapat_Kerja_Madrasah_DIPA_2026_DRAF.pdf', 'ukuran' => 64_000, 'status' => 'draf', 'bertanda_draf' => true,
            'versi' => 1, 'ringkas' => json_encode(['total_rab' => 5_000_000, 'bagian_lengkap' => null, 'jumlah_bagian' => null]),
            'user_id' => 1, 'dibuat_oleh' => 'Muslimin, M.Pd.',
        ]);

        return $id;
    }

    public function test_isian_sejalan_dengan_konstanta_riwayat(): void
    {
        $id = $this->catatan(['id' => 'rd-2026-1']);
        $model = BerkasDokumenTercatat::query()->findOrFail($id);
        $this->assertEquals(['total_rab' => 5_000_000, 'bagian_lengkap' => null, 'jumlah_bagian' => null], $model->ringkas, 'jsonb mengurutkan ulang kunci.');
        $this->assertTrue($model->bertanda_draf);
        $this->assertNotNull($model->dibuat_pada);

        $versi = 2;
        foreach (array_keys(DokumenKegiatan::JENIS) as $jenis) {
            foreach (array_keys(RiwayatDokumen::FORMAT) as $format) {
                $this->catatan(['jenis' => $jenis, 'format' => $format, 'asal' => 'cetak', 'nama_berkas' => "BERKAS.{$format}", 'versi' => $versi++]);
            }
        }
        foreach (array_keys(RiwayatDokumen::ASAL) as $asal) {
            $this->catatan(['asal' => $asal, 'versi' => $versi++]);
        }
        foreach (StatusKegiatan::cases() as $status) {
            $this->catatan(['status' => $status->value, 'versi' => $versi++, 'kode_leading' => null, 'ukuran' => null, 'ringkas' => '{}']);
        }
        $this->catatan(['jenis' => 'KAK+TOR', 'sumber_dana' => null, 'versi' => $versi++, 'ringkas' => json_encode(['bagian_lengkap' => 9, 'jumlah_bagian' => 9])]);

        foreach ([
            'jenis asing' => ['jenis' => 'SPJ'],
            'RAB tanpa sumber dana' => ['sumber_dana' => null],
            'format asing' => ['format' => 'odt', 'nama_berkas' => 'A.odt'],
            'arsip bukan PDF' => ['asal' => 'arsip', 'format' => 'docx', 'nama_berkas' => 'A.docx'],
            'asal asing' => ['asal' => 'kirim'],
            'status asing' => ['status' => 'ditolak'],
            'ekstensi tidak sesuai format' => ['nama_berkas' => 'RAB.xlsx'],
            'nama berkas kosong' => ['nama_berkas' => ' '],
            'ukuran nol' => ['ukuran' => 0],
            'nomor kosong' => ['nomor_dokumen' => ' '],
            'versi nol' => ['versi' => 0],
            'nama kegiatan kosong' => ['nama_kegiatan' => ' '],
            'pembuat kosong' => ['dibuat_oleh' => ''],
            'ringkas kunci asing' => ['ringkas' => json_encode(['halaman' => 3])],
            'ringkas negatif' => ['ringkas' => json_encode(['total_rab' => -1])],
            'ringkas pecahan' => ['ringkas' => json_encode(['total_rab' => 1.5])],
            'ringkas teks' => ['ringkas' => json_encode(['total_rab' => '100'])],
            'bagian melebihi jumlah' => ['ringkas' => json_encode(['bagian_lengkap' => 10, 'jumlah_bagian' => 9])],
            'ringkas bukan objek' => ['ringkas' => '[]'],
        ] as $kasus => $isi) {
            $this->ditolak(fn () => $this->catatan($isi + ['versi' => 999]), 'riwayat_dokumen_isian_check');
        }
        $this->ditolak(fn () => $this->catatan(['versi' => 1]), 'riwayat_dokumen_kunci_versi_versi_unique');
        $this->ditolak(fn () => $this->catatan(['tahun_anggaran' => 2030, 'versi' => 998]), 'riwayat_dokumen_tahun_foreign');
        $this->ditolak(fn () => $this->catatan(['kode_leading' => '9.9', 'versi' => 998]), 'riwayat_dokumen_leading_foreign');
        $this->ditolak(fn () => $this->catatan(['sumber_dana' => 'BOS', 'versi' => 998]), 'riwayat_dokumen_sumber_dana_foreign');
    }

    public function test_hanya_ditambah_dan_rujukan_dikosongkan(): void
    {
        $kegiatan = $this->kegiatan();
        $dokumen = DB::table('documents')->insertGetId([
            'activity_id' => $kegiatan, 'tahun_anggaran' => 2026, 'jenis' => 'KAK+TOR', 'status' => 'draf', 'konten' => '{}',
        ]);
        $id = $this->catatan(['activity_id' => $kegiatan, 'document_id' => $dokumen, 'kunci_versi' => "{$kegiatan}|RAB|DIPA|pdf"]);

        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->update(['nama_berkas' => 'LAIN.pdf']), 'hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->update(['versi' => 2]), 'hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->update(['user_id' => 2]), 'hanya bisa ditambah');
        $this->ditolak(fn () => DB::table('riwayat_dokumen')->where('id', $id)->delete(), 'hanya bisa ditambah');
        DB::table('riwayat_dokumen')->where('id', $id)->update(['user_id' => null]);

        DB::table('documents')->where('id', $dokumen)->delete();
        DB::table('activities')->where('id', $kegiatan)->delete();
        $baris = DB::table('riwayat_dokumen')->where('id', $id)->first();
        $this->assertSame([null, null, null, 'Rapat Kerja Madrasah', "{$kegiatan}|RAB|DIPA|pdf"], [$baris->activity_id, $baris->document_id, $baris->user_id, $baris->nama_kegiatan, $baris->kunci_versi], 'Catatan tetap ada walau kegiatannya dihapus.');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('riwayat_dokumen'));
        $this->assertNull(DB::selectOne("SELECT to_regproc('riwayat_dokumen_hanya_tambah') AS f")->f);
        $this->assertNull(DB::selectOne("SELECT to_regproc('riwayat_ringkas_sah') AS f")->f);
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('riwayat_dokumen'));
    }
}
