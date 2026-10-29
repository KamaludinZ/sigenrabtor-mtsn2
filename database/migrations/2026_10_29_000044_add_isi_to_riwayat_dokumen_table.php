<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cuplikan isi berkas pada riwayat dokumen (App\Support\Dokumen\IsiRiwayatDokumen::cuplikan; endpoint `dokumen.isi`): kolom
 * `riwayat_dokumen.isi` jsonb (NULL = isi tidak disimpan) berbentuk objek {sidik md5, cover objek|null, bagian larik,
 * rab objek|null} (CHECK `riwayat_dokumen_isi_check` lewat fungsi `riwayat_isi_sah(jsonb)`), dan trigger `riwayat_dokumen_hanya_tambah` kini juga menjaga isi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION riwayat_isi_sah(isi jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS $$
                SELECT COALESCE(jsonb_typeof(isi) = 'object'
                    AND NOT EXISTS (SELECT 1 FROM jsonb_object_keys(isi) k WHERE k NOT IN ('sidik', 'cover', 'bagian', 'rab'))
                    AND jsonb_typeof(isi->'sidik') = 'string' AND (isi->>'sidik') ~ '^[0-9a-f]{32}$'
                    AND jsonb_typeof(isi->'bagian') = 'array'
                    AND jsonb_typeof(isi->'cover') IN ('object', 'null')
                    AND jsonb_typeof(isi->'rab') IN ('object', 'null'), false)
            $$;
            ALTER TABLE riwayat_dokumen ADD COLUMN isi jsonb NULL;
            ALTER TABLE riwayat_dokumen ADD CONSTRAINT riwayat_dokumen_isi_check CHECK (isi IS NULL OR riwayat_isi_sah(isi));
            SQL);
        $this->fungsi(true);
    }

    public function down(): void
    {
        $this->fungsi(false);
        DB::unprepared('ALTER TABLE riwayat_dokumen DROP CONSTRAINT IF EXISTS riwayat_dokumen_isi_check; ALTER TABLE riwayat_dokumen DROP COLUMN IF EXISTS isi; DROP FUNCTION IF EXISTS riwayat_isi_sah(jsonb);');
    }

    /** Trigger hanya-tambah; `$denganIsi` = kolom isi ikut dijaga. */
    private function fungsi(bool $denganIsi): void
    {
        $isiBaru = $denganIsi ? ', NEW.isi' : '';
        $isiLama = $denganIsi ? ', OLD.isi' : '';
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION riwayat_dokumen_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;
                IF TG_OP = 'DELETE'
                    OR (NEW.id, NEW.tahun_anggaran, NEW.kode_leading, NEW.nama_kegiatan, NEW.kunci_versi, NEW.jenis, NEW.sumber_dana, NEW.format, NEW.asal,
                        NEW.nama_berkas, NEW.ukuran, NEW.status, NEW.bertanda_draf, NEW.nomor_dokumen, NEW.versi, NEW.ringkas, NEW.dibuat_oleh, NEW.dibuat_pada{$isiBaru})
                    IS DISTINCT FROM (OLD.id, OLD.tahun_anggaran, OLD.kode_leading, OLD.nama_kegiatan, OLD.kunci_versi, OLD.jenis, OLD.sumber_dana, OLD.format, OLD.asal,
                        OLD.nama_berkas, OLD.ukuran, OLD.status, OLD.bertanda_draf, OLD.nomor_dokumen, OLD.versi, OLD.ringkas, OLD.dibuat_oleh, OLD.dibuat_pada{$isiLama})
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
