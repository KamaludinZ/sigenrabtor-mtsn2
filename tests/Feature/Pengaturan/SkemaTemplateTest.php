<?php

namespace Tests\Feature\Pengaturan;

use App\Models\TemplateCover;
use App\Models\TemplateDokumen;
use App\Support\Dokumen\TeksCover;
use App\Support\Dokumen\VariabelTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000021_create_template_variabel_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
    }

    private function ditolak(callable $aksi, string $catatan): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("Seharusnya ditolak database: {$catatan}");
    }

    private function master(array $variabel): TemplateDokumen
    {
        return TemplateDokumen::query()->create(['tahun_anggaran' => 2026, 'nama' => 'Master KAK', 'jenis' => 'KAK+TOR', 'file_path' => 'master.docx', 'nama_berkas' => 'master.docx', 'variabel' => $variabel]);
    }

    private function cover(array $teks): TemplateCover
    {
        return TemplateCover::query()->create(['tahun_anggaran' => 2026, 'nama' => 'Cover', 'gambar_path' => 'cover.png', 'lebar' => 595, 'tinggi' => 842, 'teks_items' => $teks]);
    }

    public function test_rujukan_sama_dengan_konstanta_aplikasi(): void
    {
        $this->assertSame(
            array_map(fn (array $v) => ['keterangan' => $v[0], 'kelompok' => $v[1], 'jenis' => $v[2]], VariabelTemplate::DAFTAR),
            DB::table('template_variabel')->orderBy('urutan')->get()->mapWithKeys(fn ($v) => [$v->kunci => ['keterangan' => $v->keterangan, 'kelompok' => $v->kelompok, 'jenis' => $v->jenis]])->all(),
            'Variabel baru di VariabelTemplate::DAFTAR wajib ditambahkan lewat migrasi.',
        );
        $this->assertSame(TeksCover::FIELD, DB::table('cover_field')->orderBy('urutan')->pluck('label', 'kunci')->all());
        $this->assertSame([], array_diff(array_column(VariabelTemplate::DAFTAR, 1), array_keys(VariabelTemplate::KELOMPOK)), 'Setiap variabel berkelompok yang dikenal.');
    }

    public function test_variabel_master_hanya_yang_terdaftar(): void
    {
        $master = $this->master(['nama_kegiatan', 'total', 'terbilang', 'ttd_kepala']);
        $this->assertSame(['nama_kegiatan', 'total', 'terbilang', 'ttd_kepala'], $master->refresh()->variabel);
        $this->master([]);

        $this->ditolak(fn () => $this->master(['nama_kegiaatan']), 'salah tulis');
        $this->ditolak(fn () => $this->master(['total', 'total']), 'variabel ganda');
        $this->ditolak(fn () => $this->master([['nama' => 'total']]), 'bukan daftar nama');
        $this->ditolak(fn () => $master->update(['variabel' => ['total', 'pagu']]), 'diubah ke variabel asing');
        $this->assertSame(['nama_kegiatan', 'total', 'terbilang', 'ttd_kepala'], $master->refresh()->variabel);
    }

    public function test_kotak_teks_cover_sesuai_editor(): void
    {
        $this->cover(TeksCover::bawaan());
        $this->cover([]);
        $this->cover(TeksCover::rapikan([['field' => 'judul', 'x' => 120, 'y' => -3, 'width' => 1, 'font_size' => 80, 'align' => 'justify', 'weight' => 'heavy', 'color' => '#ABCDEF']]));

        $kotak = TeksCover::bawaan()[0];
        foreach ([
            'field asing' => ['field' => 'pagu'] + $kotak,
            'lebar melewati tepi' => ['x' => 30.0, 'width' => 75.0] + $kotak,
            'huruf terlalu besar' => ['font_size' => 60] + $kotak,
            'perataan asing' => ['align' => 'justify'] + $kotak,
            'ketebalan asing' => ['weight' => 'heavy'] + $kotak,
            'warna bukan #rrggbb huruf kecil' => ['color' => '#FFFFFF'] + $kotak,
            'tanpa posisi x' => array_diff_key($kotak, ['x' => 1]),
            'posisi berupa teks' => ['y' => '10'] + $kotak,
        ] as $catatan => $teks) {
            $this->ditolak(fn () => $this->cover([$teks]), $catatan);
        }
        $this->ditolak(fn () => $this->cover([$kotak, $kotak]), 'kotak ganda');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('template_variabel'));
        $this->master(['bebas']);

        DB::table('document_templates')->delete();
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->ditolak(fn () => $this->master(['bebas']), 'aturan kembali berlaku');
    }
}
