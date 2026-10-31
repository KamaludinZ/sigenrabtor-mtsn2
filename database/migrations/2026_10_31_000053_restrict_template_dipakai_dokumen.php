<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Template master yang sudah dipakai dokumen tidak boleh dihapus (PRD §6 `document_templates`: "template yang sudah dipakai
 * dokumen tidak boleh dihapus (on delete restrict), hanya dinonaktifkan"):
 * - `documents.template_id` (master KAK/TOR yang berlaku saat hasil disusun, PembuatDokumen::rekam) kini ON DELETE RESTRICT
 *   (sebelumnya SET NULL) dan hanya untuk dokumen yang memuat KAK/TOR (bukan RAB);
 * - `riwayat_dokumen.template_id` (master yang berlaku saat dokumen dicetak/diunduh/diarsipkan, CatatRiwayatDokumen) =
 *   FK komposit (template_id, tahun_anggaran) → document_templates ON DELETE RESTRICT, bukan untuk RAB, dan ikut dijaga
 *   trigger `riwayat_dokumen_hanya_tambah` (tidak bisa diubah sesudah dicatat).
 * Kegiatan yang sekadar memilih template (activities.kak_template_id) tetap SET NULL — penolakannya di aplikasi
 * (PemakaianTemplate, UnggahMasterTemplate::hapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE documents
                DROP CONSTRAINT documents_template_foreign,
                ADD CONSTRAINT documents_template_foreign FOREIGN KEY (template_id, tahun_anggaran)
                    REFERENCES document_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE RESTRICT,
                ADD CONSTRAINT documents_template_kak_check CHECK (template_id IS NULL OR jenis <> 'RAB');
            ALTER TABLE riwayat_dokumen
                ADD COLUMN template_id uuid,
                ADD CONSTRAINT riwayat_dokumen_template_foreign FOREIGN KEY (template_id, tahun_anggaran)
                    REFERENCES document_templates (id, tahun_anggaran) ON DELETE RESTRICT,
                ADD CONSTRAINT riwayat_dokumen_template_kak_check CHECK (template_id IS NULL OR jenis <> 'RAB');
            CREATE INDEX riwayat_dokumen_template_id_index ON riwayat_dokumen (template_id) WHERE template_id IS NOT NULL;
            CREATE INDEX documents_template_id_index ON documents (template_id) WHERE template_id IS NOT NULL;
            SQL);
        $this->fungsi(true);
    }

    public function down(): void
    {
        $this->fungsi(false);
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS documents_template_id_index;
            ALTER TABLE riwayat_dokumen DROP COLUMN template_id;
            ALTER TABLE documents
                DROP CONSTRAINT documents_template_kak_check,
                DROP CONSTRAINT documents_template_foreign,
                ADD CONSTRAINT documents_template_foreign FOREIGN KEY (template_id, tahun_anggaran)
                    REFERENCES document_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE SET NULL (template_id);
            SQL);
    }

    /** Trigger hanya-tambah riwayat dokumen (bentuk migrasi 000045) — dengan template_id sebagai kolom yang tidak boleh berubah. */
    private function fungsi(bool $denganTemplate): void
    {
        $baru = $denganTemplate ? ', NEW.template_id' : '';
        $lama = $denganTemplate ? ', OLD.template_id' : '';
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION riwayat_dokumen_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;
                IF TG_OP = 'DELETE'
                    OR (NEW.id, NEW.tahun_anggaran, NEW.kode_leading, NEW.nama_kegiatan, NEW.kunci_versi, NEW.jenis, NEW.sumber_dana, NEW.format, NEW.asal,
                        NEW.nama_berkas, NEW.ukuran, NEW.status, NEW.bertanda_draf, NEW.versi, NEW.ringkas, NEW.dibuat_oleh, NEW.dibuat_pada, NEW.isi{$baru})
                    IS DISTINCT FROM (OLD.id, OLD.tahun_anggaran, OLD.kode_leading, OLD.nama_kegiatan, OLD.kunci_versi, OLD.jenis, OLD.sumber_dana, OLD.format, OLD.asal,
                        OLD.nama_berkas, OLD.ukuran, OLD.status, OLD.bertanda_draf, OLD.versi, OLD.ringkas, OLD.dibuat_oleh, OLD.dibuat_pada, OLD.isi{$lama})
                    OR (NEW.nomor_dokumen IS DISTINCT FROM OLD.nomor_dokumen AND (OLD.nomor_dokumen IS NOT NULL OR NEW.nomor_dokumen IS NULL))
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
