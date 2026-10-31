<?php

use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\KunciTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pilihan template dokumen per kegiatan (PRD §9: "Setelah dokumen final dikunci, pilihan template tidak dapat
 * diubah kecuali dibuka admin (perubahan dicatat siapa & kapan)"): activities.riwayat_template = daftar entri
 * App\Support\Dokumen\KunciTemplate::catat() {aksi ubah|kunci, pada, oleh, user_id, template_lama/baru, nama_lama/baru,
 * format_lama/baru} (json: urutan kunci tetap), maks. KunciTemplate::BATAS_RIWAYAT; NULL = belum pernah berubah. CHECK
 * `activities_riwayat_template_check` (dibangkitkan dari KunciTemplate::AKSI & FormatKakTor::FORMAT): larik 1–BATAS_RIWAYAT
 * berisi objek ber-aksi terdaftar, ber-`pada` & `oleh`, dan format baru terdaftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->json('riwayat_template')->nullable());
        $batas = KunciTemplate::BATAS_RIWAYAT;
        $aksi = implode(' || ', array_map(fn (string $a) => "@.aksi == \"{$a}\"", array_keys(KunciTemplate::AKSI)));
        $format = implode(' || ', array_map(fn (string $f) => "@.format_baru == \"{$f}\"", array_keys(FormatKakTor::FORMAT)));
        DB::statement(<<<SQL
            ALTER TABLE activities ADD CONSTRAINT activities_riwayat_template_check CHECK (
                riwayat_template IS NULL OR (
                    json_typeof(riwayat_template) = 'array'
                    AND json_array_length(riwayat_template) BETWEEN 1 AND {$batas}
                    AND jsonb_path_exists(riwayat_template::jsonb, '\$[*] ? (@.type() != "object" || !({$aksi}))') = false
                    AND jsonb_path_exists(riwayat_template::jsonb, '\$[*] ? (exists(@.format_baru) && @.format_baru != null && !({$format}))') = false
                    AND jsonb_path_exists(riwayat_template::jsonb, '\$[*] ? (!exists(@.pada) || !exists(@.oleh))') = false
                )
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_riwayat_template_check');
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('riwayat_template'));
    }
};
