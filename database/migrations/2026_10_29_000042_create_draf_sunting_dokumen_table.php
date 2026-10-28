<?php

use App\Support\Dokumen\SuntingDokumen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draf sunting teks di pratinjau yang belum diterapkan ke data kegiatan (PRD F5 "Edit Teks Langsung", N5 "draf tidak
 * hilang saat pengguna keluar atau koneksi terputus"; App\Support\Dokumen\DrafSunting): satu draf per kegiatan × akun ×
 * bidang SuntingDokumen::BIDANG — teks ketikan (boleh kosong = akan mengosongkan/kembali ke teks baku, ≤
 * SuntingDokumen::maksTeks + baris kosong), teks tersimpan saat draf mulai ditulis (`teks_awal`, null = kosong/baku) untuk
 * mengenali data yang berubah sesudahnya, dan waktu bermikrodetik (`diperbarui_pada` = versi draf). Ikut terhapus bersama
 * kegiatan atau akunnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draf_sunting_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bidang', 30);
            $table->text('teks');
            $table->text('teks_awal')->nullable();
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->timestampTz('diperbarui_pada', 6)->useCurrent();
            $table->unique(['activity_id', 'user_id', 'bidang']);
        });

        $bidang = implode(', ', array_map(fn (string $k) => "'{$k}'", array_keys(SuntingDokumen::BIDANG)));
        $maks = implode(' ', array_map(fn (string $k) => "WHEN '{$k}' THEN ".SuntingDokumen::maksDraf($k), array_keys(SuntingDokumen::BIDANG)));
        DB::statement(<<<SQL
            ALTER TABLE draf_sunting_dokumen ADD CONSTRAINT draf_sunting_isian_check CHECK (
                bidang IN ({$bidang})
                AND char_length(teks) <= CASE bidang {$maks} END
                AND (teks_awal IS NULL OR (btrim(teks_awal) <> '' AND teks_awal = btrim(teks_awal)))
                AND diperbarui_pada >= dibuat_pada)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('draf_sunting_dokumen');
    }
};
