<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\LeadingRepository;
use App\Repositories\EloquentLeadingRepository;
use App\Support\Basisdata\PindahLeading;
use App\Support\Stub\StubLeadingRepository;
use Database\Seeders\LeadingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PindahLeadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_leading_baku_idempoten(): void
    {
        $this->seed(LeadingSeeder::class);
        $this->assertSame(array_column(LeadingSeeder::BAKU, 0), DB::table('leadings')->orderBy('urutan')->pluck('kode')->all());

        DB::table('leadings')->where('kode', '1.8')->update(['nama_unit' => 'PMM Madrasah', 'is_active' => false]);
        $this->seed();
        $this->assertSame(8, DB::table('leadings')->count(), 'DatabaseSeeder memanggil LeadingSeeder; tidak menggandakan.');
        $this->assertSame(['PMM Madrasah', false], [DB::table('leadings')->where('kode', '1.8')->value('nama_unit'), (bool) DB::table('leadings')->where('kode', '1.8')->value('is_active')], 'Ubahan admin tidak ditimpa.');
    }

    public function test_sinkron_leading_tiruan_ke_database_berisi(): void
    {
        $this->seed(LeadingSeeder::class);
        DB::table('leadings')->where('kode', '1.2')->update(['jabatan_penanda_tangan' => 'Waka Kesiswaan Lama']);
        DB::table('leadings')->insert(['kode' => '3.1', 'nama_unit' => 'Unit Khusus Database', 'jabatan_penanda_tangan' => 'Ketua', 'urutan' => 30]);
        app(LeadingRepository::class)->simpan(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi']);

        $uji = PindahLeading::jalankan(uji: true);
        $this->assertSame(['1.2'], $uji['ubah']);
        $this->assertSame('Waka Kesiswaan Lama', DB::table('leadings')->where('kode', '1.2')->value('jabatan_penanda_tangan'), 'Mode uji tidak menyimpan.');

        $hasil = PindahLeading::jalankan();
        $this->assertSame([['1.9', '1.10'], ['1.2'], ['3.1']], [$hasil['tambah'], $hasil['ubah'], $hasil['hanya_database']]);
        $this->assertCount(7, $hasil['tetap']);
        $db = collect((new EloquentLeadingRepository)->semua())->keyBy('kode');
        foreach (app(LeadingRepository::class)->semua() as $l) {
            $this->assertSame($l, $db[$l['kode']]);
        }
        $this->assertSame('Unit Khusus Database', $db['3.1']['nama_unit'], 'Leading khusus database dibiarkan.');

        $this->artisan('sigenrabtor:pindah-leading', ['--uji' => true])->assertSuccessful()->expectsOutputToContain('Uji sinkron leading lolos verifikasi');
        $this->assertSame(['tambah' => [], 'ubah' => [], 'tetap' => array_column(app(LeadingRepository::class)->semua(), 'kode'), 'hanya_database' => ['3.1']], PindahLeading::jalankan());
    }

    public function test_database_menolak_berarti_batal_semua(): void
    {
        $this->seed(LeadingSeeder::class);
        // Nama unit tiruan bentrok (tanpa memandang huruf besar) dengan leading khusus database berkode lain.
        DB::table('leadings')->insert(['kode' => '3.1', 'nama_unit' => 'laboratorium terpadu', 'jabatan_penanda_tangan' => 'Ketua', 'urutan' => 30]);
        $this->assertContains('Laboratorium Terpadu', array_column(StubLeadingRepository::contoh(), 'nama_unit'));

        try {
            PindahLeading::jalankan();
            $this->fail('Seharusnya ditolak database.');
        } catch (QueryException) {
            $this->assertSame(9, DB::table('leadings')->count(), 'Tidak ada yang tersimpan.');
        }
    }
}
