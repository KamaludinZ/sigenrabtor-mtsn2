<?php

namespace Tests\Feature\Basisdata;

use App\Support\Dokumen\FormatSeragam;
use App\Support\Dokumen\PenomoranDokumen;
use App\Support\Dokumen\PilihanTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaFormatSeragamTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_29_000047_create_format_seragam_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
    }

    private function master(int $tahun = 2026, string $jenis = 'KAK+TOR'): string
    {
        DB::table('document_templates')->insert(['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => $tahun, 'nama' => "Master {$jenis}", 'jenis' => $jenis, 'file_path' => "template/master/{$id}.docx", 'nama_berkas' => 'master.docx']);

        return $id;
    }

    private function cover(int $tahun = 2026): string
    {
        DB::table('cover_templates')->insert(['id' => $id = (string) Str::uuid(), 'tahun_anggaran' => $tahun, 'nama' => 'Cover', 'gambar_path' => "template/cover/{$id}.png", 'lebar' => 1240, 'tinggi' => 1754]);

        return $id;
    }

    /** @param  array<string, mixed>  $baris */
    private function gagal(string $tabel, array $baris, string $pesan = ''): void
    {
        try {
            DB::transaction(fn () => DB::table($tabel)->insert($baris));
            $this->fail('Seharusnya ditolak: '.json_encode($baris));
        } catch (QueryException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    public function test_pilihan_template_per_jenis_dan_leading(): void
    {
        $kak = $this->master();
        $cover = $this->cover();
        DB::table('pilihan_template')->insert([
            ['tahun_anggaran' => 2026, 'kode_leading' => null, 'jenis_kegiatan' => 'workshop', 'kak_template_id' => $kak, 'cover_template_id' => null],
            ['tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'jenis_kegiatan' => 'umum', 'kak_template_id' => null, 'cover_template_id' => $cover],
        ]);
        $this->assertSame(2, DB::table('pilihan_template')->count());

        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'kode_leading' => null, 'jenis_kegiatan' => 'workshop', 'kak_template_id' => $kak], 'pilihan_template_unik');
        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'jenis_kegiatan' => 'rapat'], 'pilihan_template_jenis_check');
        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'kode_leading' => '9.9', 'jenis_kegiatan' => 'umum'], 'foreign key');
        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'jenis_kegiatan' => 'lomba', 'kak_template_id' => $this->master(jenis: 'RAB')], 'master KAK/TOR');
        // Template dari tahun lain tidak bisa dipilih.
        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'jenis_kegiatan' => 'siswa', 'kak_template_id' => $this->master(2025)], 'pilihan_template_kak_fk');
        $this->gagal('pilihan_template', ['tahun_anggaran' => 2026, 'jenis_kegiatan' => 'siswa', 'cover_template_id' => $this->cover(2025)], 'pilihan_template_cover_fk');

        // Template dihapus → pilihan kembali ke bawaan (kolom kosong), baris tetap.
        DB::table('document_templates')->where('id', $kak)->delete();
        DB::table('cover_templates')->where('id', $cover)->delete();
        $this->assertSame([[null, null], [null, null]], DB::table('pilihan_template')->orderBy('id')->get(['kak_template_id', 'cover_template_id'])->map(fn ($r) => [$r->kak_template_id, $r->cover_template_id])->all());
        // Kode leading ikut berganti.
        DB::table('leadings')->where('kode', '1.6')->update(['kode' => '1.10']);
        $this->assertSame('1.10', DB::table('pilihan_template')->whereNotNull('kode_leading')->value('kode_leading'));
    }

    public function test_baris_khusus_leading_dibatasi(): void
    {
        // 9 leading contoh × 5 jenis = 45 < batas: tambah dua leading agar pasangan melebihi batas.
        DB::table('leadings')->insert([
            ['kode' => '2.1', 'nama_unit' => 'Unit Uji Satu', 'jabatan_penanda_tangan' => 'Koordinator Uji Satu', 'urutan' => 21, 'is_active' => true],
            ['kode' => '2.2', 'nama_unit' => 'Unit Uji Dua', 'jabatan_penanda_tangan' => 'Koordinator Uji Dua', 'urutan' => 22, 'is_active' => true],
        ]);
        $kode = array_column(DB::table('leadings')->get(['kode'])->all(), 'kode');
        $jenis = ['workshop', 'supervisi', 'lomba', 'siswa', 'umum'];
        $baris = [];
        foreach ($kode as $k) {
            foreach ($jenis as $j) {
                $baris[] = ['tahun_anggaran' => 2026, 'kode_leading' => $k, 'jenis_kegiatan' => $j];
            }
        }
        $this->assertGreaterThan(PilihanTemplate::MAKS_LEADING, count($baris));
        DB::table('pilihan_template')->insert(array_slice($baris, 0, PilihanTemplate::MAKS_LEADING));
        DB::statement('SET CONSTRAINTS pilihan_template_maks_leading IMMEDIATE');
        try {
            DB::transaction(fn () => DB::table('pilihan_template')->insert($baris[PilihanTemplate::MAKS_LEADING]));
            $this->fail('Baris khusus leading melebihi batas seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Paling banyak '.PilihanTemplate::MAKS_LEADING, $e->getMessage());
        }
        DB::statement('SET CONSTRAINTS pilihan_template_maks_leading DEFERRED');
        // Baris per jenis (tanpa leading) tidak dihitung.
        DB::table('pilihan_template')->insert(['tahun_anggaran' => 2026, 'jenis_kegiatan' => 'umum']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertSame(PilihanTemplate::MAKS_LEADING + 1, DB::table('pilihan_template')->count());
    }

    /** @return iterable<string, array{string}> */
    public static function kasusPola(): iterable
    {
        foreach ([...array_column(PenomoranDokumen::BAWAAN, 'pola'), '{urut}', 'SK/{urut}/{leading}/{sumber}/{bulan}/{tahun}', '', 'KAK/{tahun}', '{urut}/{asing}',
            '{URUT}', str_repeat('A', FormatSeragam::MAKS_POLA - 6).'{urut}', str_repeat('A', FormatSeragam::MAKS_POLA - 5).'{urut}', '{urut}/{bulan_romawi}', 'NO.{urut}-{jenis}'] as $pola) {
            yield "pola {$pola}" => [$pola];
        }
    }

    #[DataProvider('kasusPola')]
    public function test_pola_nomor_sah_sama_dengan_format_seragam(string $pola): void
    {
        $this->assertSame(FormatSeragam::galatPola($pola) === null, (bool) DB::selectOne('SELECT pola_nomor_sah(?) AS sah', [$pola])->sah);
    }

    public function test_aturan_penomoran_dan_penghitung(): void
    {
        DB::table('aturan_penomoran')->insert(['tahun_anggaran' => 2026, 'jenis' => 'RAB', 'pola' => 'RAB/{urut}/{tahun}', 'digit' => 4, 'aktif' => false]);
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2026, 'jenis' => 'RAB', 'pola' => 'RAB/{urut}', 'digit' => 3], 'aturan_penomoran_pkey');
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2026, 'jenis' => 'KAK', 'pola' => 'KAK/{tahun}', 'digit' => 3], 'aturan_penomoran_isian_check');
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2026, 'jenis' => 'KAK', 'pola' => 'KAK / {urut}', 'digit' => 3], 'aturan_penomoran_isian_check');
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2026, 'jenis' => 'KAK', 'pola' => 'KAK/{urut}', 'digit' => 7], 'aturan_penomoran_isian_check');
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2026, 'jenis' => 'SK', 'pola' => 'SK/{urut}', 'digit' => 3], 'aturan_penomoran_isian_check');
        $this->gagal('aturan_penomoran', ['tahun_anggaran' => 2031, 'jenis' => 'KAK', 'pola' => 'KAK/{urut}', 'digit' => 3], 'foreign key');

        $this->assertSame(1, DB::selectOne("SELECT nomor_dokumen_berikutnya('RAB', 2026) AS n")->n);
        $this->gagal('number_sequences', ['jenis' => 'KAK', 'tahun' => 2026, 'nomor_terakhir' => -1], 'number_sequences_nomor_check');

        DB::table('pengaturan_format')->insert(['tahun_anggaran' => 2026, 'pilihan_diperbarui_pada' => now()->format('Y-m-d H:i:s.uP')]);
        $this->gagal('pengaturan_format', ['tahun_anggaran' => 2026], 'pengaturan_format_pkey');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        foreach (['pilihan_template', 'aturan_penomoran', 'pengaturan_format'] as $tabel) {
            $this->assertFalse(Schema::hasTable($tabel));
        }
        $this->assertNull(DB::selectOne("SELECT to_regprocedure('pola_nomor_sah(text)') AS f")->f);
        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('pilihan_template'));
    }
}
