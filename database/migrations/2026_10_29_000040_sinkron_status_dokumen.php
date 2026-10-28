<?php

use App\Support\Dokumen\StatusDraf;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status dokumen mengikuti status kegiatannya (App\Support\Dokumen\StatusDokumen; PRD §6 `documents.status`, aturan 8):
 * - trigger `activities_status_dokumen` (AFTER UPDATE OF status ON activities): dokumen kegiatan itu ikut berstatus baru
 *   dan bercap DRAF bila status barunya bukan StatusDraf::STATUS_TANPA_CAP (cap sesudah Disetujui dihitung ulang
 *   aplikasi — StatusDokumen::sinkronkan — karena bergantung pada kelengkapan & pagu);
 * - CHECK `documents_cap_draf_check`: dokumen berstatus selain STATUS_TANPA_CAP selalu bertanda_draf;
 * - constraint trigger tertunda `documents_status_sesuai_kegiatan` (INSERT/UPDATE documents): saat commit status dokumen
 *   = status kegiatannya.
 * Dokumen lama disamakan lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tanpaCap = implode(', ', array_map(fn ($s) => "'{$s->value}'", StatusDraf::STATUS_TANPA_CAP));

        DB::unprepared(<<<SQL
            UPDATE documents d SET status = a.status FROM activities a WHERE a.id = d.activity_id AND d.status <> a.status;
            UPDATE documents SET bertanda_draf = true WHERE NOT bertanda_draf AND status NOT IN ({$tanpaCap});
            ALTER TABLE documents ADD CONSTRAINT documents_cap_draf_check CHECK (bertanda_draf OR status IN ({$tanpaCap}));

            CREATE OR REPLACE FUNCTION dokumen_ikut_status_kegiatan() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                UPDATE documents SET status = NEW.status, bertanda_draf = bertanda_draf OR NEW.status NOT IN ({$tanpaCap}), updated_at = now()
                WHERE activity_id = NEW.id AND status IS DISTINCT FROM NEW.status;
                RETURN NULL;
            END
            \$\$;
            CREATE TRIGGER activities_status_dokumen AFTER UPDATE OF status ON activities
                FOR EACH ROW WHEN (OLD.status IS DISTINCT FROM NEW.status) EXECUTE FUNCTION dokumen_ikut_status_kegiatan();

            CREATE OR REPLACE FUNCTION status_dokumen_sesuai_kegiatan() RETURNS trigger LANGUAGE plpgsql AS \$\$
            DECLARE
                dokumen text;
                kegiatan text;
            BEGIN
                SELECT d.status, a.status INTO dokumen, kegiatan FROM documents d JOIN activities a ON a.id = d.activity_id WHERE d.id = NEW.id;
                IF dokumen IS NOT NULL AND dokumen <> kegiatan THEN
                    RAISE EXCEPTION 'documents_status_sesuai_kegiatan: status dokumen % (%) tidak sama dengan status kegiatannya (%).', NEW.id, dokumen, kegiatan USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            \$\$;
            CREATE CONSTRAINT TRIGGER documents_status_sesuai_kegiatan AFTER INSERT OR UPDATE OF status, activity_id ON documents
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION status_dokumen_sesuai_kegiatan();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS documents_status_sesuai_kegiatan ON documents;
            DROP TRIGGER IF EXISTS activities_status_dokumen ON activities;
            DROP FUNCTION IF EXISTS status_dokumen_sesuai_kegiatan();
            DROP FUNCTION IF EXISTS dokumen_ikut_status_kegiatan();
            ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_cap_draf_check;
            SQL);
    }
};
