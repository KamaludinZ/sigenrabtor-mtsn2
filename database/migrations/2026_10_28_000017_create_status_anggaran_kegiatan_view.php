<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status anggaran per kegiatan (PRD F12 "Batas pagu", aturan 5): view `status_anggaran_kegiatan` merangkum RAB sumber
 * dana yang dipilih (dari rab_per_sumber) untuk setiap kegiatan — jumlah sumber, item, total RAB, pagu, jumlah RAB yang
 * melebihi pagu / tanpa pagu — dan `kondisi_anggaran` dengan aturan yang sama persis dengan
 * App\Support\Rab\StatusAnggaran::kegiatan() (tanpa_sumber, rab_kosong, melebihi, tanpa_pagu, sesuai, sisa), sehingga
 * saringan kondisi anggaran Data Kegiatan & rekap Monitoring bisa dikerjakan di database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW status_anggaran_kegiatan AS
            SELECT
                k.id AS activity_id,
                k.tahun_anggaran,
                k.kode_leading,
                k.status,
                COUNT(r.anggaran_kegiatan_id)::integer AS jumlah_sumber,
                COALESCE(SUM(r.jumlah_item), 0)::integer AS jumlah_item,
                COALESCE(SUM(r.total_rab), 0)::bigint AS total_rab,
                COALESCE(SUM(r.pagu), 0)::bigint AS pagu,
                COUNT(*) FILTER (WHERE r.status_pagu = 'melebihi')::integer AS jumlah_melebihi,
                COUNT(*) FILTER (WHERE r.status_pagu = 'tanpa_pagu')::integer AS jumlah_tanpa_pagu,
                CASE
                    WHEN COUNT(r.anggaran_kegiatan_id) = 0 THEN 'tanpa_sumber'
                    WHEN COALESCE(SUM(r.jumlah_item), 0) = 0 THEN 'rab_kosong'
                    WHEN bool_or(r.status_pagu = 'melebihi') THEN 'melebihi'
                    WHEN bool_or(r.status_pagu = 'tanpa_pagu') THEN 'tanpa_pagu'
                    WHEN bool_and(r.status_pagu = 'sesuai') THEN 'sesuai'
                    ELSE 'sisa'
                END AS kondisi_anggaran
            FROM activities k
            LEFT JOIN rab_per_sumber r ON r.activity_id = k.id AND r.dipilih
            GROUP BY k.id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW status_anggaran_kegiatan');
    }
};
