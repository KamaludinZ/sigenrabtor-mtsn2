<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun & riwayatnya (PRD tabel users & user_leading F29, N7) — aturan disamakan dengan App\Support\Pengaturan\AturanAkun:
 * nama wajib satu baris berspasi tunggal, jabatan (bila diisi) juga; akun admin tidak bertaut leading dan minimal satu
 * admin aktif (trigger constraint tertunda sampai commit sehingga tukar peran/status dalam satu transaksi tetap boleh).
 * riwayat_masuk: jejak tiap kali akun masuk (waktu, alamat IP, agen peramban); nama akun saat itu tetap tercatat walau
 * akunnya dihapus. users.terakhir_masuk tetap ringkasan masuk terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_isian_check CHECK (btrim(name) <> '' AND name = btrim(name) AND name !~ '[[:space:]]{2,}'
            AND (jabatan IS NULL OR (btrim(jabatan) <> '' AND jabatan = btrim(jabatan) AND jabatan !~ '[[:space:]]{2,}')))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION akun_peran_sah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM user_leading ul JOIN users u ON u.id = ul.user_id WHERE u.role = 'admin') THEN
                    RAISE EXCEPTION 'Akun admin tidak bertaut ke leading.' USING ERRCODE = 'check_violation';
                END IF;
                IF EXISTS (SELECT 1 FROM users) AND NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin' AND is_active) THEN
                    RAISE EXCEPTION 'Minimal satu akun admin harus aktif.' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            $$;
            CREATE CONSTRAINT TRIGGER users_peran_sah AFTER UPDATE OF role, is_active OR DELETE ON users
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION akun_peran_sah();
            CREATE CONSTRAINT TRIGGER user_leading_peran_sah AFTER INSERT OR UPDATE ON user_leading
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION akun_peran_sah();
            SQL);

        Schema::create('riwayat_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 255);
            $table->timestampTz('waktu', 6)->useCurrent();
            $table->string('alamat_ip', 45)->nullable();
            $table->string('agen', 255)->nullable();
            $table->index(['user_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_masuk');
        DB::unprepared('DROP TRIGGER IF EXISTS user_leading_peran_sah ON user_leading; DROP TRIGGER IF EXISTS users_peran_sah ON users; DROP FUNCTION IF EXISTS akun_peran_sah()');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_isian_check');
    }
};
