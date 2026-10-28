<?php

use App\Support\Kegiatan\JadwalKegiatan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal kegiatan (PRD §6 `schedules`: urutan, uraian, waktu_pelaksanaan, is_baku). Waktu pelaksanaan tetap disimpan
 * sebagai daftar bulan bersambung (`bulan`; tahunnya selalu tahun anggaran aktif, PRD aturan 3) dan kini juga tersedia
 * sebagai kolom terhitung `bulan_mulai`/`bulan_selesai` (null bila bulan kosong) untuk kueri per bulan. `is_baku` = uraian
 * baris salah satu uraian baku template KAK (JadwalKegiatan::uraianBaku), diisi repositori saat simpan; baris lama diisi
 * ulang di sini dengan pembanding yang sama (huruf kecil, spasi dirapikan).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE schedules ADD COLUMN bulan_mulai smallint GENERATED ALWAYS AS (CASE WHEN jsonb_array_length(bulan) > 0 THEN (bulan ->> 0)::smallint END) STORED');
        DB::statement('ALTER TABLE schedules ADD COLUMN bulan_selesai smallint GENERATED ALWAYS AS (CASE WHEN jsonb_array_length(bulan) > 0 THEN (bulan ->> -1)::smallint END) STORED');
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('is_baku')->default(false);
        });

        $baku = array_map(fn (string $u) => mb_strtolower($u), JadwalKegiatan::URAIAN_BAKU);
        DB::update(
            "UPDATE schedules SET is_baku = lower(regexp_replace(btrim(uraian), '\\s+', ' ', 'g')) IN (".implode(', ', array_fill(0, count($baku), '?')).')',
            $baku,
        );
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['bulan_mulai', 'bulan_selesai', 'is_baku']);
        });
    }
};
