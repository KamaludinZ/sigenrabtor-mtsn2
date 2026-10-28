<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sumber dana di rangkuman RAB: view rab_per_sumber kini ikut membawa `nama_sumber` dan `urutan_sumber` dari tabel
 * rujukan sumber_dana (urutan baku tampil, sama dengan App\Enums\SumberDana) sehingga laporan di database bisa
 * mengurutkan & melabeli sumber dana tanpa daftar tetap di kueri. Kolom baru ditambahkan di akhir (CREATE OR REPLACE VIEW).
 */
return new class extends Migration
{
    private const DASAR = <<<'SQL'
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
        SQL;

    private const SUMBER = <<<'SQL'
        FROM anggaran_kegiatan a
        JOIN activities k ON k.id = a.activity_id
        LEFT JOIN (SELECT anggaran_kegiatan_id, SUM(jumlah) AS total, COUNT(*) AS jumlah_item FROM budget_items GROUP BY anggaran_kegiatan_id) i
            ON i.anggaran_kegiatan_id = a.id
        LEFT JOIN (SELECT anggaran_kegiatan_id, SUM(nominal) AS total, COUNT(*) AS jumlah_termin FROM termin_pembayaran GROUP BY anggaran_kegiatan_id) t
            ON t.anggaran_kegiatan_id = a.id
        SQL;

    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW rab_per_sumber AS '.self::DASAR.",\n    s.nama AS nama_sumber,\n    s.urutan AS urutan_sumber\n".self::SUMBER."\nJOIN sumber_dana s ON s.kode = a.sumber_dana");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW rab_per_sumber');
        DB::statement('CREATE VIEW rab_per_sumber AS '.self::DASAR."\n".self::SUMBER);
    }
};
