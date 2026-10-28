<?php

namespace Tests\Feature\Kegiatan;

use App\Repositories\EloquentKegiatanRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaKonteksTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000012_add_konteks_columns_to_activities_table.php';

    public function test_konteks_paling_panjang_3000_karakter(): void
    {
        $kegiatan = fn (string $konteks) => DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum', 'konteks' => $konteks]);
        $kegiatan(str_repeat('é', 3000));

        try {
            DB::transaction(fn () => $kegiatan(str_repeat('a', 3001)));
            $this->fail('Konteks lebih dari 3.000 karakter seharusnya ditolak.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_waktu_perubahan_konteks_dicatat_terpisah(): void
    {
        $repo = new EloquentKegiatanRepository;
        $id = $repo->simpan(['nama_kegiatan' => 'Workshop Kurikulum', 'konteks' => 'Peserta 56 guru.']);
        $pertama = $repo->temukan($id)['konteks_diperbarui_pada'];
        $this->assertNotNull($pertama);

        // Isian lain berubah atau konteks dikirim sama → waktu konteks tetap; versi Data Kegiatan tetap bergerak.
        $repo->simpan(['tempat' => 'Aula Madrasah'], $id);
        $repo->simpan(['konteks' => 'Peserta 56 guru.'], $id);
        $this->assertSame($pertama, $repo->temukan($id)['konteks_diperbarui_pada']);
        $this->assertNotSame($pertama, $repo->temukan($id)['diperbarui_pada']);

        $repo->simpan(['konteks' => 'Peserta 56 guru; narasumber pengawas madrasah.'], $id);
        $this->assertNotSame($pertama, $repo->temukan($id)['konteks_diperbarui_pada']);

        $repo->simpan(['konteks' => ''], $id);
        $this->assertNull($repo->temukan($id)['konteks_diperbarui_pada']);
    }

    public function test_migrasi_mengisi_waktu_konteks_lama(): void
    {
        // ALTER TABLE activities ditolak selama ada pemeriksaan rujukan tertunda: periksa seketika.
        RujukanDb::segera();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $berisi = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop', 'konteks' => 'Catatan lama.', 'updated_at' => '2026-01-03 08:00:00+07']);
        $kosong = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Rapat', 'konteks' => '', 'updated_at' => '2026-01-03 08:00:00+07']);

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();

        $this->assertSame(DB::table('activities')->where('id', $berisi)->value('updated_at'), DB::table('activities')->where('id', $berisi)->value('konteks_diperbarui_pada'));
        $this->assertNull(DB::table('activities')->where('id', $kosong)->value('konteks_diperbarui_pada'));
    }
}
