<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RAB per sumber dana (PRD F12 & §11 Monitoring): view `rab_per_sumber` merangkum tiap baris anggaran_kegiatan — kegiatan
 * (tahun, leading, status), sumber dana & apakah dipilih, pagu, total RAB dari rinciannya (bukan salinan total_rab),
 * jumlah item, jumlah & total termin — beserta status pagu (aturan StatusPagu: tanpa_pagu/sesuai/sisa/melebihi; selisih =
 * pagu − total) dan status termin (aturan MekanismePembayaran: sekaligus/pas/kurang/lebih). Untuk laporan & rekap di
 * database; tampilan tetap memakai AnggaranKegiatan. Indeks (sumber_dana, dipilih) untuk saringan per sumber dana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->index(['sumber_dana', 'dipilih']);
        });

        DB::statement(<<<'SQL'
            CREATE VIEW rab_per_sumber AS
            SELECT
                a.id AS anggaran_kegiatan_id,
                a.activity_id,
                k.tahun_anggaran,
                k.kode_leading,
                k.status,
                a.sumber_dana,
                a.dipilih,
                a.pagu,
                COALESCE(i.total, 0)::bigint AS total_rab,
                COALESCE(i.jumlah_item, 0)::integer AS jumlah_item,
                COALESCE(t.jumlah_termin, 0)::integer AS jumlah_termin,
                COALESCE(t.total, 0)::bigint AS total_termin,
                CASE
                    WHEN a.pagu IS NULL THEN 'tanpa_pagu'
                    WHEN COALESCE(i.total, 0) = a.pagu THEN 'sesuai'
                    WHEN COALESCE(i.total, 0) < a.pagu THEN 'sisa'
                    ELSE 'melebihi'
                END AS status_pagu,
                (a.pagu - COALESCE(i.total, 0))::bigint AS selisih_pagu,
                CASE
                    WHEN t.jumlah_termin IS NULL THEN 'sekaligus'
                    WHEN COALESCE(i.total, 0) = t.total THEN 'pas'
                    WHEN COALESCE(i.total, 0) > t.total THEN 'kurang'
                    ELSE 'lebih'
                END AS status_termin
            FROM anggaran_kegiatan a
            JOIN activities k ON k.id = a.activity_id
            LEFT JOIN (SELECT anggaran_kegiatan_id, SUM(jumlah) AS total, COUNT(*) AS jumlah_item FROM budget_items GROUP BY anggaran_kegiatan_id) i
                ON i.anggaran_kegiatan_id = a.id
            LEFT JOIN (SELECT anggaran_kegiatan_id, SUM(nominal) AS total, COUNT(*) AS jumlah_termin FROM termin_pembayaran GROUP BY anggaran_kegiatan_id) t
                ON t.anggaran_kegiatan_id = a.id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW rab_per_sumber');

        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->dropIndex(['sumber_dana', 'dipilih']);
        });
    }
};
