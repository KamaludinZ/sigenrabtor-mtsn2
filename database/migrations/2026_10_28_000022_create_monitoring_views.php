<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring anggaran di database (PRD Fase 5 §11) — dua view berisi RAB sumber dana yang dipilih saja (seperti
 * AnggaranKegiatan::untuk yang dipakai RingkasanAnggaran):
 * - `monitoring_rab`: satu baris per kegiatan × sumber dana dengan kolom saringan Monitoring (tahun, leading, status,
 *   pembuat, tanggal mulai/selesai, bulan pelaksanaan), pagu, total RAB, jumlah item, selisih, status pagu, dan
 *   `kondisi_rab` (aturan StatusAnggaran::rab: pagu kosong → tanpa_pagu, tanpa rincian → rab_kosong, selain itu status
 *   pagu) — dasar perSumber/perLeading/perOperator/rincian kegiatan.
 * - `rekap_kelompok_belanja`: jumlah item & total per kegiatan × sumber dana × kategori belanja (bahan/jasa/lain) — dasar
 *   perKelompok (RekapKelompok).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW monitoring_rab AS
            SELECT
                r.anggaran_kegiatan_id,
                r.activity_id,
                r.tahun_anggaran,
                r.kode_leading,
                r.status,
                k.user_id,
                k.tanggal_mulai,
                k.tanggal_selesai,
                k.bulan_pelaksanaan,
                r.sumber_dana,
                r.urutan_sumber,
                r.pagu,
                r.total_rab,
                r.jumlah_item,
                r.selisih_pagu,
                r.status_pagu,
                CASE
                    WHEN r.pagu IS NULL THEN 'tanpa_pagu'
                    WHEN r.jumlah_item = 0 THEN 'rab_kosong'
                    ELSE r.status_pagu
                END AS kondisi_rab,
                GREATEST(r.total_rab - COALESCE(r.pagu, r.total_rab), 0)::bigint AS kelebihan
            FROM rab_per_sumber r
            JOIN activities k ON k.id = r.activity_id
            WHERE r.dipilih
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW rekap_kelompok_belanja AS
            SELECT
                a.activity_id,
                k.tahun_anggaran,
                k.kode_leading,
                k.status,
                a.sumber_dana,
                i.kategori,
                COUNT(*)::integer AS jumlah_item,
                SUM(i.jumlah)::bigint AS total
            FROM budget_items i
            JOIN anggaran_kegiatan a ON a.id = i.anggaran_kegiatan_id AND a.dipilih
            JOIN activities k ON k.id = a.activity_id
            GROUP BY a.activity_id, k.tahun_anggaran, k.kode_leading, k.status, a.sumber_dana, i.kategori
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW rekap_kelompok_belanja');
        DB::statement('DROP VIEW monitoring_rab');
    }
};
