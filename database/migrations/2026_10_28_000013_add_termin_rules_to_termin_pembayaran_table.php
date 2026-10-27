<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Termin pembayaran (PRD F11 "Termin Pembayaran", aturan 6): aturan termin di form (TerminPembayaran::rules) juga dijaga
 * database — paling banyak MekanismePembayaran::MAKS_TERMIN (12) termin, nomor berurutan 1..n tanpa celah, tanggal tidak
 * mundur dari termin sebelumnya, dan tanggal di tahun anggaran kegiatannya. Aturan antarbaris diperiksa constraint
 * trigger DEFERRABLE INITIALLY DEFERRED (saat transaksi selesai) sehingga termin satu sumber dana bisa diganti seluruhnya
 * (hapus lalu tulis ulang) dalam satu transaksi. Kecocokan jumlah termin dengan total RAB tetap aturan aplikasi
 * (TerminPembayaran::pesanTidakPas) karena rincian dan termin disimpan terpisah. Fungsi memakai CREATE OR REPLACE karena
 * migrate:fresh membuang tabel tetapi tidak membuang fungsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE termin_pembayaran ADD CONSTRAINT termin_pembayaran_nomor_maks_check CHECK (nomor_termin <= 12)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION periksa_termin_pembayaran() RETURNS trigger AS $$
            DECLARE
                anggaran bigint := COALESCE(NEW.anggaran_kegiatan_id, OLD.anggaran_kegiatan_id);
                tahun smallint;
                jumlah integer;
                terbesar integer;
                salah record;
            BEGIN
                SELECT count(*), COALESCE(max(nomor_termin), 0) INTO jumlah, terbesar FROM termin_pembayaran WHERE anggaran_kegiatan_id = anggaran;
                IF jumlah <> terbesar THEN
                    RAISE EXCEPTION 'Nomor termin harus berurutan 1 sampai %.', jumlah USING ERRCODE = 'check_violation';
                END IF;

                SELECT k.tahun_anggaran INTO tahun FROM anggaran_kegiatan a JOIN activities k ON k.id = a.activity_id WHERE a.id = anggaran;
                SELECT nomor_termin INTO salah FROM termin_pembayaran
                    WHERE anggaran_kegiatan_id = anggaran AND tanggal IS NOT NULL AND EXTRACT(YEAR FROM tanggal) <> tahun LIMIT 1;
                IF FOUND THEN
                    RAISE EXCEPTION 'Tanggal termin % harus di Tahun Anggaran %.', salah.nomor_termin, tahun USING ERRCODE = 'check_violation';
                END IF;

                SELECT b.nomor_termin INTO salah FROM termin_pembayaran b JOIN termin_pembayaran a
                    ON a.anggaran_kegiatan_id = b.anggaran_kegiatan_id AND a.nomor_termin = b.nomor_termin - 1
                    WHERE b.anggaran_kegiatan_id = anggaran AND a.tanggal IS NOT NULL AND b.tanggal IS NOT NULL AND b.tanggal < a.tanggal LIMIT 1;
                IF FOUND THEN
                    RAISE EXCEPTION 'Tanggal termin % tidak boleh sebelum termin %.', salah.nomor_termin, salah.nomor_termin - 1 USING ERRCODE = 'check_violation';
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER termin_pembayaran_aturan
                AFTER INSERT OR UPDATE OR DELETE ON termin_pembayaran
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION periksa_termin_pembayaran();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER termin_pembayaran_aturan ON termin_pembayaran; DROP FUNCTION periksa_termin_pembayaran();');
        DB::statement('ALTER TABLE termin_pembayaran DROP CONSTRAINT termin_pembayaran_nomor_maks_check');
    }
};
