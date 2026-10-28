<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Validasi data pejabat penanda tangan di database, sejalan dengan KelolaPejabat::aturan & Nip::galat: jabatan berisi
 * huruf, nama (bila diisi) berisi huruf tanpa angka, dan NIP berangka jenis kelamin 1/2 dengan nomor urut bukan 000
 * (tanggal lahir & TMT tetap diperiksa aplikasi karena bergantung pada tanggal hari ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pejabat_penanda_tangan ADD CONSTRAINT pejabat_isian_check CHECK (jabatan ~ '[[:alpha:]]' AND (nama IS NULL OR (nama ~ '[[:alpha:]]' AND nama !~ '[0-9]')))");
        DB::statement("ALTER TABLE pejabat_penanda_tangan ADD CONSTRAINT pejabat_nip_bagian_check CHECK (nip IS NULL OR (substr(nip, 15, 1) IN ('1', '2') AND substr(nip, 16, 3) <> '000' AND substr(nip, 13, 2) BETWEEN '01' AND '12'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pejabat_penanda_tangan DROP CONSTRAINT pejabat_nip_bagian_check');
        DB::statement('ALTER TABLE pejabat_penanda_tangan DROP CONSTRAINT pejabat_isian_check');
    }
};
