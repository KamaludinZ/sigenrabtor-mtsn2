<?php

use App\Support\Akun\PendaftaranAkun;
use App\Support\Akun\PeranAkun;
use App\Support\Pengaturan\AturanAkun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peran & pendaftaran akun (PRD §8 "Daftar & Masuk"/"Peran Pengguna", §6 users; bentuk PermohonanAkunRepository):
 * - peran: rujukan peran akun dari PeranAkun::DAFTAR (kode, label, keterangan, perlu_leading, urutan); users.role kini FK ke
 *   sana (ON UPDATE CASCADE) menggantikan CHECK users_role_check — peran baru wajib lewat migrasi;
 * - permohonan_akun: permohonan pendaftaran (PendaftaranAkun) — nama & jabatan satu baris berspasi tunggal (≤
 *   AturanAkun::MAKS_NAMA/MAKS_JABATAN), surel huruf kecil, NIP 18 angka, catatan ≤ PendaftaranAkun::MAKS_CATATAN, sandi
 *   hanya hash bcrypt, status menunggu|disetujui|ditolak dengan waktu/pemutus keputusan sejalan, akun hasil hanya saat
 *   disetujui, satu permohonan menunggu per surel (indeks unik parsial), surel permohonan menunggu tidak boleh sudah menjadi
 *   akun (trigger `permohonan_akun_surel_baru`); leading yang diminta di permohonan_akun_leading (FK leadings, ikut terhapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $table) {
            $table->string('kode', 10)->primary();
            $table->string('label', 50);
            $table->string('keterangan', 300);
            $table->boolean('perlu_leading');
            $table->unsignedSmallInteger('urutan');
        });
        $urutan = 0;
        DB::table('peran')->insert(array_map(function (string $kode, array $p) use (&$urutan) {
            return ['kode' => $kode, 'label' => $p['label'], 'keterangan' => $p['keterangan'], 'perlu_leading' => $p['perlu_leading'], 'urutan' => ++$urutan];
        }, array_keys(PeranAkun::DAFTAR), PeranAkun::DAFTAR));
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_role_foreign FOREIGN KEY (role) REFERENCES peran (kode) ON UPDATE CASCADE');

        Schema::create('permohonan_akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', AturanAkun::MAKS_NAMA);
            $table->string('surel', 255);
            $table->string('jabatan', AturanAkun::MAKS_JABATAN)->nullable();
            $table->string('nip', 18)->nullable();
            $table->text('catatan')->nullable();
            $table->string('sandi_hash')->nullable();
            $table->string('status', 10)->default('menunggu');
            $table->timestampTz('diajukan_pada', 6);
            $table->timestampTz('diputuskan_pada', 6)->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan')->nullable();
            $table->foreignId('akun_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });
        $maksCatatan = PendaftaranAkun::MAKS_CATATAN;
        DB::unprepared(<<<SQL
            ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_akun_isian_check CHECK (
                btrim(nama) <> '' AND nama = btrim(nama) AND nama !~ '[[:space:]]{2,}' AND nama ~ '[[:alpha:]]'
                AND (jabatan IS NULL OR (btrim(jabatan) <> '' AND jabatan = btrim(jabatan) AND jabatan !~ '[[:space:]]{2,}'))
                AND surel = lower(btrim(surel)) AND surel ~ '^[^@[:space:]]+@[^@[:space:]]+\\.[^@[:space:]]+$'
                AND (nip IS NULL OR nip ~ '^[0-9]{18}$')
                AND (catatan IS NULL OR (btrim(catatan) <> '' AND char_length(catatan) <= {$maksCatatan}))
                AND (sandi_hash IS NULL OR sandi_hash ~ '^\\\$2[aby]\\\$[0-9]{2}\\\$.{53}$'));
            ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_akun_status_check CHECK (
                status IN ('menunggu', 'disetujui', 'ditolak')
                AND (status = 'menunggu') = (diputuskan_pada IS NULL)
                AND (akun_id IS NULL OR status = 'disetujui')
                AND (diputuskan_pada IS NULL OR diputuskan_pada >= diajukan_pada));
            CREATE UNIQUE INDEX permohonan_akun_satu_menunggu ON permohonan_akun (surel) WHERE status = 'menunggu';

            CREATE OR REPLACE FUNCTION permohonan_akun_surel_baru() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.status = 'menunggu' AND EXISTS (SELECT 1 FROM users WHERE lower(email) = NEW.surel) THEN
                    RAISE EXCEPTION 'Surel % sudah terdaftar sebagai akun.', NEW.surel USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END
            \$\$;
            CREATE TRIGGER permohonan_akun_surel_baru BEFORE INSERT OR UPDATE OF surel, status ON permohonan_akun
                FOR EACH ROW EXECUTE FUNCTION permohonan_akun_surel_baru();
            SQL);

        Schema::create('permohonan_akun_leading', function (Blueprint $table) {
            $table->uuid('permohonan_id');
            $table->string('kode_leading', 10);
            $table->primary(['permohonan_id', 'kode_leading']);
            $table->foreign('permohonan_id')->references('id')->on('permohonan_akun')->cascadeOnDelete();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permohonan_akun_leading');
        Schema::dropIfExists('permohonan_akun');
        DB::unprepared('DROP FUNCTION IF EXISTS permohonan_akun_surel_baru()');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_foreign');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'leading'))");
        Schema::dropIfExists('peran');
    }
};
