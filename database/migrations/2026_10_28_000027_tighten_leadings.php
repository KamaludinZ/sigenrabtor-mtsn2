<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leading (PRD tabel leadings, F30) — aturan disamakan dengan App\Support\Pengaturan\AturanLeading: kode "n.n" tanpa nol
 * di depan (hasil AturanLeading::normalkanKode, sehingga "1.10" ≠ "1.1" dan "01.1" tidak tersimpan), nama unit & jabatan
 * penanda tangan satu baris berspasi tunggal tanpa spasi di tepi (AturanLeading::rapikan), urutan 0–999, dan minimal satu
 * leading aktif (trigger constraint tertunda sampai commit sehingga menukar status dalam satu transaksi tetap boleh).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leadings DROP CONSTRAINT leadings_kode_check');
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_kode_check CHECK (kode ~ '^(0|[1-9][0-9]{0,2})\\.(0|[1-9][0-9]{0,2})$')");
        DB::statement('ALTER TABLE leadings DROP CONSTRAINT leadings_isian_check');
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_isian_check CHECK (btrim(nama_unit) <> '' AND btrim(jabatan_penanda_tangan) <> ''
            AND nama_unit = btrim(nama_unit) AND nama_unit !~ '[[:space:]]{2,}' AND jabatan_penanda_tangan = btrim(jabatan_penanda_tangan) AND jabatan_penanda_tangan !~ '[[:space:]]{2,}'
            AND urutan BETWEEN 0 AND 999)");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION leadings_minimal_satu_aktif() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM leadings) AND NOT EXISTS (SELECT 1 FROM leadings WHERE is_active) THEN
                    RAISE EXCEPTION 'Minimal satu leading harus aktif.' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            $$;
            CREATE CONSTRAINT TRIGGER leadings_minimal_satu_aktif AFTER UPDATE OF is_active OR DELETE ON leadings
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION leadings_minimal_satu_aktif();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS leadings_minimal_satu_aktif ON leadings; DROP FUNCTION IF EXISTS leadings_minimal_satu_aktif()');
        DB::statement('ALTER TABLE leadings DROP CONSTRAINT leadings_isian_check');
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_isian_check CHECK (btrim(nama_unit) <> '' AND btrim(jabatan_penanda_tangan) <> '' AND urutan <= 999)");
        DB::statement('ALTER TABLE leadings DROP CONSTRAINT leadings_kode_check');
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_kode_check CHECK (kode ~ '^[0-9]{1,3}\\.[0-9]{1,3}$')");
    }
};
