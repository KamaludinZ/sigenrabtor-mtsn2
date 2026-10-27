<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master kode per tahun anggaran (PRD §6 kode_sk/kode_iksk/kode_rktm/kode_edm/budaya_mutu, F7 & §14) dengan bentuk
 * MasterKodeRepository. Seperti activities, tahun & kode memakai kunci alami (tahun_anggaran + kode unik per tahun;
 * FK ke tahun_anggaran(tahun) menyusul bersama tabelnya) karena kegiatan menyimpan teks kodenya dan master diganti
 * utuh tiap unggahan Excel. `urutan` = urutan baris di berkas (urutan tampil). IKSK wajib milik SK tahun yang sama:
 * FK (tahun_anggaran, kode_sk) → kode_sk ON DELETE NO ACTION DEFERRABLE (RESTRICT tidak pernah ditunda) sehingga master
 * bisa diganti dalam satu transaksi. Nama komponen EDM per huruf di komponen_edm; kode_edm.komponen = hurufnya (bawaan
 * huruf awal kode, "A1" → "A"). Uraian panjang (SK dari Perkin) memakai text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kode_sk', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('urutan');
            $table->string('kode', 20);
            $table->text('uraian');

            $table->unique(['tahun_anggaran', 'kode']);
        });

        Schema::create('kode_iksk', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('urutan');
            $table->string('kode', 20);
            $table->string('kode_sk', 20);
            $table->text('uraian');

            $table->unique(['tahun_anggaran', 'kode']);
            $table->index(['tahun_anggaran', 'kode_sk']);
        });
        DB::statement('ALTER TABLE kode_iksk ADD CONSTRAINT kode_iksk_kode_sk_foreign FOREIGN KEY (tahun_anggaran, kode_sk) REFERENCES kode_sk (tahun_anggaran, kode) ON UPDATE CASCADE ON DELETE NO ACTION DEFERRABLE INITIALLY DEFERRED');

        Schema::create('kode_rktm', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('urutan');
            $table->string('kode', 20);
            $table->text('snp');
            $table->text('kegiatan');
            $table->text('sub_kegiatan');

            $table->unique(['tahun_anggaran', 'kode']);
        });

        Schema::create('komponen_edm', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('huruf', 5);
            $table->string('nama');

            $table->unique(['tahun_anggaran', 'huruf']);
        });

        Schema::create('kode_edm', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('urutan');
            $table->string('kode', 10);
            $table->string('komponen', 5);
            $table->text('uraian');

            $table->unique(['tahun_anggaran', 'kode']);
        });

        Schema::create('budaya_mutu', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedSmallInteger('nomor');
            $table->string('nama');

            $table->unique(['tahun_anggaran', 'nomor']);
            $table->unique(['tahun_anggaran', 'nama']);
        });
        DB::statement('ALTER TABLE budaya_mutu ADD CONSTRAINT budaya_mutu_nomor_check CHECK (nomor >= 1)');
    }

    public function down(): void
    {
        foreach (['budaya_mutu', 'kode_edm', 'komponen_edm', 'kode_rktm', 'kode_iksk', 'kode_sk'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
