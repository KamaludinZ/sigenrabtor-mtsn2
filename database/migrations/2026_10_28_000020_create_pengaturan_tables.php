<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan admin (PRD §6, Fase 5–7) — bentuk repositori pengaturan yang kini masih tiruan:
 * - tahun_anggaran (tepat satu aktif, SK RKTM/RKAM berpasangan, jejak aktivasi), leadings (kode `n.n` unik, nama unit
 *   unik tanpa beda huruf besar), kolom akun di users (peran admin/leading, NIP 18 angka, status, terakhir masuk; surel
 *   huruf kecil) + user_leading;
 * - profil_madrasah (satu baris), pejabat_penanda_tangan (kepala/komite/leading per tahun), aturan_mengetahui (blok
 *   "Mengetahui" per tahun × sumber dana), dasar_hukum (butir berurutan ≤ 500 karakter) + latar_belakang_baku (penutup
 *   memuat {nama_kegiatan} tepat sekali);
 * - cover_templates & document_templates (satu default per tahun [× jenis] [× leading × sumber]), daftar_isi_templates
 *   (bawaan per tahun atau khusus leading × sumber), page_number_settings;
 * - prompt_templates (per bagian BagianAi + baris `umum` = gaya bahasa baku), ai_settings (satu baris) +
 *   ai_provider_settings (per penyedia, kunci API terenkripsi beserta 4 karakter akhirnya);
 * - activity_logs (riwayat perubahan, hanya bisa ditambah — PRD N7).
 * Tahun & leading tetap kunci alami (tahun, kode). Rujukan dari tabel yang sudah ada (activities, master kode, data_pagu,
 * riwayat_status_kegiatan → tahun_anggaran/leadings/users) ditambahkan DEFERRABLE INITIALLY DEFERRED: diperiksa saat
 * commit sehingga impor massal boleh menyimpan baris sebelum rujukannya dalam satu transaksi.
 */
return new class extends Migration
{
    /** Tabel lama → kolom rujukannya yang kini diberi FK tertunda. */
    private const RUJUKAN = [
        ['activities', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['activities', 'kode_leading', 'leadings(kode)', 'ON UPDATE CASCADE'],
        ['activities', 'user_id', 'users(id)', 'ON DELETE SET NULL'],
        ['riwayat_status_kegiatan', 'user_id', 'users(id)', 'ON DELETE SET NULL'],
        ['kode_sk', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['kode_iksk', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['kode_rktm', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['komponen_edm', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['kode_edm', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['budaya_mutu', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['data_pagu', 'tahun_anggaran', 'tahun_anggaran(tahun)', 'ON UPDATE CASCADE'],
        ['data_pagu', 'kode_leading', 'leadings(kode)', 'ON UPDATE CASCADE'],
    ];

    private const NIP = "~ '^[0-9]{18}$'";

    public function up(): void
    {
        Schema::create('tahun_anggaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('no_sk_rktm', 100)->nullable();
            $table->date('tanggal_sk_rktm')->nullable();
            $table->string('diaktifkan_oleh', 150)->nullable();
            $table->timestampTz('diaktifkan_pada', 6)->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE tahun_anggaran ADD CONSTRAINT tahun_anggaran_tahun_check CHECK (tahun BETWEEN 2000 AND 2100)');
        DB::statement('ALTER TABLE tahun_anggaran ADD CONSTRAINT tahun_anggaran_sk_check CHECK ((no_sk_rktm IS NULL) = (tanggal_sk_rktm IS NULL) AND (no_sk_rktm IS NULL OR btrim(no_sk_rktm) <> \'\'))');
        DB::statement('CREATE UNIQUE INDEX tahun_anggaran_satu_aktif ON tahun_anggaran (is_active) WHERE is_active');

        Schema::create('leadings', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama_unit', 100);
            $table->string('jabatan_penanda_tangan', 150);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_kode_check CHECK (kode ~ '^[0-9]{1,3}\\.[0-9]{1,3}$')");
        DB::statement("ALTER TABLE leadings ADD CONSTRAINT leadings_isian_check CHECK (btrim(nama_unit) <> '' AND btrim(jabatan_penanda_tangan) <> '' AND urutan <= 999)");
        DB::statement('CREATE UNIQUE INDEX leadings_nama_unit_unik ON leadings (lower(btrim(nama_unit)))');

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('leading');
            $table->string('nip', 18)->nullable();
            $table->string('jabatan', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('terakhir_masuk', 6)->nullable();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'leading'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_nip_check CHECK (nip IS NULL OR nip '.self::NIP.')');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_check CHECK (email = lower(email))');

        Schema::create('user_leading', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kode_leading', 10);
            $table->primary(['user_id', 'kode_leading']);
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
        });

        Schema::create('profil_madrasah', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary()->default(1);
            $table->string('nama', 150);
            $table->string('nama_singkat', 60);
            $table->string('kota', 60);
            $table->string('alamat')->nullable();
            $table->string('telepon', 50)->nullable();
            $table->text('visi')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE profil_madrasah ADD CONSTRAINT profil_madrasah_satu_baris CHECK (id = 1)');
        DB::statement("ALTER TABLE profil_madrasah ADD CONSTRAINT profil_madrasah_isian_check CHECK (btrim(nama) <> '' AND btrim(nama_singkat) <> '' AND btrim(kota) <> '' AND (telepon IS NULL OR telepon ~ '^[0-9 ()+./-]+$'))");

        Schema::create('pejabat_penanda_tangan', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('peran', 10);
            $table->string('kode_leading', 10)->nullable();
            $table->string('jabatan', 150);
            $table->string('nama', 150)->nullable();
            $table->string('nip', 18)->nullable();
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
        });
        DB::statement("ALTER TABLE pejabat_penanda_tangan ADD CONSTRAINT pejabat_peran_check CHECK (peran IN ('kepala', 'komite', 'leading') AND (peran = 'leading') = (kode_leading IS NOT NULL))");
        DB::statement('ALTER TABLE pejabat_penanda_tangan ADD CONSTRAINT pejabat_nip_check CHECK (nip IS NULL OR nip '.self::NIP.')');
        DB::statement("CREATE UNIQUE INDEX pejabat_penanda_tangan_unik ON pejabat_penanda_tangan (tahun_anggaran, peran, COALESCE(kode_leading, ''))");

        Schema::create('aturan_mengetahui', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('sumber_dana', 10);
            $table->boolean('tampil')->default(true);
            $table->primary(['tahun_anggaran', 'sumber_dana']);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana');
        });

        Schema::create('dasar_hukum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('urutan');
            $table->string('teks', 500);
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_isian_check CHECK (btrim(teks) <> '' AND urutan BETWEEN 1 AND 30)");
        DB::statement('ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_urutan_unik UNIQUE (tahun_anggaran, urutan) DEFERRABLE INITIALLY IMMEDIATE');

        Schema::create('latar_belakang_baku', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran')->primary();
            $table->text('latar_belakang');
            $table->text('kalimat_penutup');
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE latar_belakang_baku ADD CONSTRAINT latar_belakang_penutup_check CHECK (char_length(kalimat_penutup) - char_length(replace(kalimat_penutup, '{nama_kegiatan}', '')) = char_length('{nama_kegiatan}'))");

        Schema::create('cover_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10)->nullable();
            $table->string('sumber_dana', 10)->nullable();
            $table->string('nama', 150);
            $table->string('gambar_path');
            $table->unsignedInteger('lebar');
            $table->unsignedInteger('tinggi');
            $table->jsonb('teks_items')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana');
        });
        DB::statement("ALTER TABLE cover_templates ADD CONSTRAINT cover_templates_isian_check CHECK (lebar > 0 AND tinggi > 0 AND jsonb_typeof(teks_items) = 'array')");
        DB::statement("CREATE UNIQUE INDEX cover_templates_satu_default ON cover_templates (tahun_anggaran, COALESCE(kode_leading, ''), COALESCE(sumber_dana, '')) WHERE is_default");

        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10)->nullable();
            $table->string('nama', 150);
            $table->string('jenis', 10);
            $table->string('file_path');
            $table->string('nama_berkas');
            $table->jsonb('variabel')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
        });
        DB::statement("ALTER TABLE document_templates ADD CONSTRAINT document_templates_isian_check CHECK (jenis IN ('KAK+TOR', 'KAK', 'TOR', 'RAB') AND jsonb_typeof(variabel) = 'array')");
        DB::statement("CREATE UNIQUE INDEX document_templates_satu_default ON document_templates (tahun_anggaran, jenis, COALESCE(kode_leading, '')) WHERE is_default");

        Schema::create('daftar_isi_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10)->nullable();
            $table->string('sumber_dana', 10)->nullable();
            $table->jsonb('konfigurasi');
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana');
        });
        // Bawaan: tanpa leading & sumber; khusus: keduanya terisi.
        DB::statement("ALTER TABLE daftar_isi_templates ADD CONSTRAINT daftar_isi_isian_check CHECK ((kode_leading IS NULL) = (sumber_dana IS NULL) AND jsonb_typeof(konfigurasi) = 'object')");
        DB::statement("CREATE UNIQUE INDEX daftar_isi_templates_unik ON daftar_isi_templates (tahun_anggaran, COALESCE(kode_leading, ''), COALESCE(sumber_dana, ''))");

        Schema::create('page_number_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran')->primary();
            $table->jsonb('aturan');
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate()->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE page_number_settings ADD CONSTRAINT page_number_settings_aturan_check CHECK (jsonb_typeof(aturan) = 'object')");

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->string('bagian', 20)->primary();
            $table->text('instruksi')->nullable();
            $table->string('gaya_bahasa', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });
        // Baris `umum` hanya menyimpan gaya bahasa baku semua bagian; bagian lain wajib berinstruksi 20–1000 karakter.
        DB::statement("ALTER TABLE prompt_templates ADD CONSTRAINT prompt_templates_bagian_check CHECK (bagian IN ('umum', 'nama_cover', 'latar_belakang', 'tujuan', 'sasaran', 'pelaksanaan', 'jadwal'))");
        DB::statement("ALTER TABLE prompt_templates ADD CONSTRAINT prompt_templates_instruksi_check CHECK (CASE WHEN bagian = 'umum' THEN instruksi IS NULL ELSE char_length(btrim(instruksi)) BETWEEN 20 AND 1000 END)");

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary()->default(1);
            $table->string('provider_aktif', 10)->nullable();
            $table->string('provider_cadangan', 10)->nullable();
            $table->jsonb('parameter')->default('{}');
            $table->unsignedSmallInteger('batas_per_user_per_hari')->default(20);
            $table->jsonb('batas_khusus')->default('{}');
            $table->jsonb('simulasi_gagal')->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_satu_baris CHECK (id = 1)');
        DB::statement("ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_penyedia_check CHECK ((provider_aktif IS NULL OR provider_aktif IN ('ollama', 'claude', 'openai', 'gemini')) AND (provider_cadangan IS NULL OR (provider_aktif IS NOT NULL AND provider_cadangan IN ('ollama', 'claude', 'openai', 'gemini') AND provider_cadangan <> provider_aktif)))");
        DB::statement("ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_isian_check CHECK (batas_per_user_per_hari BETWEEN 1 AND 200 AND jsonb_typeof(parameter) = 'object' AND jsonb_typeof(batas_khusus) = 'object')");

        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->string('penyedia', 10)->primary();
            $table->string('base_url')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('batas_waktu')->nullable();
            $table->text('kunci_terenkripsi')->nullable();
            $table->string('kunci_akhir', 4)->nullable();
            $table->timestampsTz(6);
        });
        DB::statement("ALTER TABLE ai_provider_settings ADD CONSTRAINT ai_provider_settings_check CHECK (penyedia IN ('ollama', 'claude', 'openai', 'gemini') AND (batas_waktu IS NULL OR batas_waktu BETWEEN 10 AND 300) AND (kunci_terenkripsi IS NULL) = (kunci_akhir IS NULL))");

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 150);
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('tahun_anggaran')->nullable();
            $table->string('aksi', 50);
            $table->text('keterangan')->default('');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['tahun_anggaran', 'created_at']);
        });
        // Hanya bisa ditambah: ubah/hapus ditolak, kecuali rujukan yang dikosongkan karena akun/kegiatannya dihapus.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION activity_logs_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' OR (NEW.pengguna, NEW.tahun_anggaran, NEW.aksi, NEW.keterangan, NEW.created_at)
                    IS DISTINCT FROM (OLD.pengguna, OLD.tahun_anggaran, OLD.aksi, OLD.keterangan, OLD.created_at)
                    OR (NEW.user_id IS DISTINCT FROM OLD.user_id AND NEW.user_id IS NOT NULL)
                    OR (NEW.activity_id IS DISTINCT FROM OLD.activity_id AND NEW.activity_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'activity_logs hanya bisa ditambah' USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END $$
            SQL);
        DB::statement('CREATE TRIGGER activity_logs_hanya_tambah BEFORE UPDATE OR DELETE ON activity_logs FOR EACH ROW EXECUTE FUNCTION activity_logs_hanya_tambah()');

        foreach (self::RUJUKAN as [$tabel, $kolom, $rujukan, $aksi]) {
            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_{$kolom}_rujukan FOREIGN KEY ({$kolom}) REFERENCES {$rujukan} {$aksi} DEFERRABLE INITIALLY DEFERRED");
        }
    }

    public function down(): void
    {
        foreach (self::RUJUKAN as [$tabel, $kolom]) {
            DB::statement("ALTER TABLE {$tabel} DROP CONSTRAINT {$tabel}_{$kolom}_rujukan");
        }
        Schema::dropIfExists('activity_logs');
        DB::statement('DROP FUNCTION IF EXISTS activity_logs_hanya_tambah()');
        foreach (['ai_provider_settings', 'ai_settings', 'prompt_templates', 'page_number_settings', 'daftar_isi_templates', 'document_templates', 'cover_templates', 'latar_belakang_baku', 'dasar_hukum', 'aturan_mengetahui', 'pejabat_penanda_tangan', 'profil_madrasah', 'user_leading'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check, DROP CONSTRAINT users_nip_check, DROP CONSTRAINT users_email_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'nip', 'jabatan', 'is_active', 'terakhir_masuk']);
        });
        Schema::dropIfExists('leadings');
        Schema::dropIfExists('tahun_anggaran');
    }
};
