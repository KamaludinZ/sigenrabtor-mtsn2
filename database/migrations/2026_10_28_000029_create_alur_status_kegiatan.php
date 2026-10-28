<?php

use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur status kegiatan di database (PRD F17–F18, aturan 9): rujukan `aksi_status` (aksi → status tujuan, label, peran,
 * catatan wajib) + `aksi_status_dari` (status asal yang boleh) diisi dari StatusKegiatan::semuaAksi() ditambah aksi
 * `dibuat` (→ draf, tanpa asal) — aksi baru wajib lewat migrasi berikutnya (`SkemaAlurStatusTest` membandingkannya).
 * - riwayat_status_kegiatan: (aksi, status) wajib pasangan aksi → status tujuannya (FK komposit), peran admin/leading,
 *   catatan ≤ RiwayatStatus::MAKS_CATATAN dan wajib untuk aksi RiwayatStatus::CATATAN_WAJIB.
 * - activities: jejak kunci (dikunci_pada/oleh) hanya selama Final, dikunci_oleh hanya bersama dikunci_pada (FK users
 *   tertunda, akun dihapus → kosong).
 * - Trigger constraint tertunda (diperiksa saat commit): perubahan activities.status harus perpindahan yang sah
 *   (`status_kegiatan_sah`), dan status kegiatan sama dengan status entri riwayat terakhirnya bila ada
 *   (`status_sesuai_riwayat`) — status tidak bisa berubah tanpa riwayat.
 * - View `pemeriksaan_dokumen` (PRD): keputusan admin (setujui → disetujui, kembalikan → dikembalikan, buka_kunci →
 *   dibuka) dari riwayat beserta pemeriksa & catatannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $status = array_column(StatusKegiatan::cases(), 'value');

        Schema::create('aksi_status', function (Blueprint $table) use ($status) {
            $table->string('aksi', 30)->primary();
            $table->string('label', 60);
            $table->enum('ke', $status);
            $table->json('peran');
            $table->boolean('catatan_wajib')->default(false);
            $table->unique(['aksi', 'ke']);
        });
        Schema::create('aksi_status_dari', function (Blueprint $table) use ($status) {
            $table->string('aksi', 30);
            $table->enum('dari', $status);
            $table->primary(['aksi', 'dari']);
            $table->foreign('aksi')->references('aksi')->on('aksi_status')->cascadeOnUpdate()->cascadeOnDelete();
        });

        DB::table('aksi_status')->insert(['aksi' => 'dibuat', 'label' => StatusKegiatan::labelRiwayat('dibuat'), 'ke' => StatusKegiatan::Draf->value, 'peran' => json_encode(['leading', 'admin']), 'catatan_wajib' => false]);
        foreach (StatusKegiatan::semuaAksi() as $aksi => $a) {
            DB::table('aksi_status')->insert(['aksi' => $aksi, 'label' => $a['label'], 'ke' => $a['ke']->value, 'peran' => json_encode($a['peran']), 'catatan_wajib' => isset(RiwayatStatus::CATATAN_WAJIB[$aksi])]);
            DB::table('aksi_status_dari')->insert(array_map(fn (StatusKegiatan $dari) => ['aksi' => $aksi, 'dari' => $dari->value], $a['dari']));
        }

        $wajib = implode(', ', array_map(fn (string $a) => "'{$a}'", array_keys(RiwayatStatus::CATATAN_WAJIB)));
        DB::statement('ALTER TABLE riwayat_status_kegiatan ADD CONSTRAINT riwayat_status_aksi_foreign FOREIGN KEY (aksi, status) REFERENCES aksi_status (aksi, ke) ON UPDATE CASCADE');
        DB::statement("ALTER TABLE riwayat_status_kegiatan ADD CONSTRAINT riwayat_status_isian_check CHECK (peran IN ('admin', 'leading') AND btrim(oleh) <> ''
            AND (catatan IS NULL OR char_length(catatan) <= ".RiwayatStatus::MAKS_CATATAN.")
            AND (aksi NOT IN ({$wajib}) OR btrim(coalesce(catatan, '')) <> ''))");

        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_kunci_check CHECK ((dikunci_pada IS NULL OR status = 'final') AND (dikunci_oleh IS NULL OR dikunci_pada IS NOT NULL))");
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_dikunci_oleh_foreign FOREIGN KEY (dikunci_oleh) REFERENCES users (id) ON DELETE SET NULL DEFERRABLE INITIALLY DEFERRED');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION status_kegiatan_sah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status IS DISTINCT FROM OLD.status AND NOT EXISTS (
                    SELECT 1 FROM aksi_status a JOIN aksi_status_dari d ON d.aksi = a.aksi WHERE d.dari = OLD.status AND a.ke = NEW.status
                ) THEN
                    RAISE EXCEPTION 'Status kegiatan % tidak bisa berpindah dari % ke %.', NEW.id, OLD.status, NEW.status USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            $$;
            CREATE CONSTRAINT TRIGGER activities_status_sah AFTER UPDATE OF status ON activities
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION status_kegiatan_sah();

            CREATE OR REPLACE FUNCTION status_sesuai_riwayat() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                kegiatan bigint;
                sekarang text;
                terakhir text;
            BEGIN
                IF TG_TABLE_NAME = 'activities' THEN
                    kegiatan := NEW.id;
                ELSE
                    kegiatan := NEW.activity_id;
                END IF;
                SELECT status INTO sekarang FROM activities WHERE id = kegiatan;
                IF sekarang IS NULL THEN
                    RETURN NULL;
                END IF;
                SELECT status INTO terakhir FROM riwayat_status_kegiatan WHERE activity_id = kegiatan ORDER BY id DESC LIMIT 1;
                IF terakhir IS NOT NULL AND terakhir <> sekarang THEN
                    RAISE EXCEPTION 'Status kegiatan % (%) tidak sama dengan riwayat terakhirnya (%).', kegiatan, sekarang, terakhir USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            $$;
            CREATE CONSTRAINT TRIGGER activities_status_sesuai_riwayat AFTER INSERT OR UPDATE OF status ON activities
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION status_sesuai_riwayat();
            CREATE CONSTRAINT TRIGGER riwayat_status_sesuai_kegiatan AFTER INSERT ON riwayat_status_kegiatan
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION status_sesuai_riwayat();

            CREATE VIEW pemeriksaan_dokumen AS
            SELECT r.id, r.activity_id, r.user_id AS pemeriksa_id, r.oleh AS pemeriksa,
                CASE r.aksi WHEN 'setujui' THEN 'disetujui' WHEN 'kembalikan' THEN 'dikembalikan' ELSE 'dibuka' END AS keputusan,
                r.catatan, r.created_at
            FROM riwayat_status_kegiatan r
            WHERE r.aksi IN ('setujui', 'kembalikan', 'buka_kunci') AND r.peran = 'admin';
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS pemeriksaan_dokumen;
            DROP TRIGGER IF EXISTS riwayat_status_sesuai_kegiatan ON riwayat_status_kegiatan;
            DROP TRIGGER IF EXISTS activities_status_sesuai_riwayat ON activities;
            DROP TRIGGER IF EXISTS activities_status_sah ON activities;
            DROP FUNCTION IF EXISTS status_sesuai_riwayat();
            DROP FUNCTION IF EXISTS status_kegiatan_sah();
            SQL);
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_dikunci_oleh_foreign');
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_kunci_check');
        DB::statement('ALTER TABLE riwayat_status_kegiatan DROP CONSTRAINT riwayat_status_isian_check');
        DB::statement('ALTER TABLE riwayat_status_kegiatan DROP CONSTRAINT riwayat_status_aksi_foreign');
        Schema::dropIfExists('aksi_status_dari');
        Schema::dropIfExists('aksi_status');
    }
};
