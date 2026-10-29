<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen pada riwayat (App\Support\Dokumen\PerbaruiRiwayatDokumen::beriNomor, PATCH `dokumen.perbarui`):
 * trigger `riwayat_dokumen_hanya_tambah` kini mengizinkan satu-satunya perubahan isi — `nomor_dokumen` dari kosong menjadi
 * terisi (sekali); nomor yang sudah ada tetap tidak bisa diganti atau dikosongkan, kolom lain tetap terkunci.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->fungsi(true);
    }

    public function down(): void
    {
        $this->fungsi(false);
    }

    private function fungsi(bool $bolehNomor): void
    {
        $nomorBaru = $bolehNomor ? '' : ', NEW.nomor_dokumen';
        $nomorLama = $bolehNomor ? '' : ', OLD.nomor_dokumen';
        $syaratNomor = $bolehNomor ? "\n                    OR (NEW.nomor_dokumen IS DISTINCT FROM OLD.nomor_dokumen AND (OLD.nomor_dokumen IS NOT NULL OR NEW.nomor_dokumen IS NULL))" : '';
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION riwayat_dokumen_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;
                IF TG_OP = 'DELETE'
                    OR (NEW.id, NEW.tahun_anggaran, NEW.kode_leading, NEW.nama_kegiatan, NEW.kunci_versi, NEW.jenis, NEW.sumber_dana, NEW.format, NEW.asal,
                        NEW.nama_berkas, NEW.ukuran, NEW.status, NEW.bertanda_draf{$nomorBaru}, NEW.versi, NEW.ringkas, NEW.dibuat_oleh, NEW.dibuat_pada, NEW.isi)
                    IS DISTINCT FROM (OLD.id, OLD.tahun_anggaran, OLD.kode_leading, OLD.nama_kegiatan, OLD.kunci_versi, OLD.jenis, OLD.sumber_dana, OLD.format, OLD.asal,
                        OLD.nama_berkas, OLD.ukuran, OLD.status, OLD.bertanda_draf{$nomorLama}, OLD.versi, OLD.ringkas, OLD.dibuat_oleh, OLD.dibuat_pada, OLD.isi){$syaratNomor}
                    OR (NEW.activity_id IS DISTINCT FROM OLD.activity_id AND NEW.activity_id IS NOT NULL)
                    OR (NEW.document_id IS DISTINCT FROM OLD.document_id AND NEW.document_id IS NOT NULL)
                    OR (NEW.user_id IS DISTINCT FROM OLD.user_id AND NEW.user_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'riwayat_dokumen hanya bisa ditambah' USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END
            \$\$;
            SQL);
    }
};
