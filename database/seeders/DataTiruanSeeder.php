<?php

namespace Database\Seeders;

use App\Support\Basisdata\PindahMasterKode;
use App\Support\Stub\StubTahunAnggaranRepository;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data tiruan untuk pengembangan & demo (BUKAN untuk instalasi madrasah; tidak dipanggil DatabaseSeeder):
 * `php artisan db:seed --class=DataTiruanSeeder`. Idempoten: leading baku (LeadingSeeder), tahun anggaran contoh
 * (StubTahunAnggaranRepository::contoh — 2026 aktif, 2025 terkunci) bila tabelnya masih kosong, lalu master kode tiruan
 * semua tahun lewat PindahMasterKode (bagian yang sama dibiarkan). Kegiatan & RAB contoh dipindahkan terpisah lewat
 * `sigenrabtor:pindah-anggaran` (hanya database kosong).
 */
class DataTiruanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(LeadingSeeder::class);

        if (! DB::table('tahun_anggaran')->exists()) {
            foreach (StubTahunAnggaranRepository::contoh() as $t) {
                DB::table('tahun_anggaran')->insert([
                    'tahun' => $t['tahun'], 'is_active' => $t['is_active'], 'is_locked' => $t['is_locked'],
                    'no_sk_rktm' => $t['no_sk_rktm'], 'tanggal_sk_rktm' => $t['tanggal_sk_rktm'],
                    'diaktifkan_oleh' => $t['diaktifkan_oleh'], 'diaktifkan_pada' => $t['diaktifkan_pada'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $hasil = PindahMasterKode::jalankan();
        $this->command?->info('Master kode tiruan: '.count($hasil['ganti']).' tahun diganti, '.count($hasil['tetap']).' tahun sudah sama.');
    }
}
