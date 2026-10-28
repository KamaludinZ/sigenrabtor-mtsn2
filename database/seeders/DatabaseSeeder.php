<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Data baku madrasah untuk instalasi baru (idempoten). Akun & tahun anggaran dibuat admin lewat aplikasi.
        $this->call(LeadingSeeder::class);
    }
}
