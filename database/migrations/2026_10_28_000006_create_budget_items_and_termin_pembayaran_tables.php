<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RAB per kegiatan × sumber dana (PRD §6 budget_items & termin_pembayaran, F11–F12): rincian belanja dan termin
 * pembayaran milik baris anggaran_kegiatan. RAB tetap tersimpan walau sumber dananya tidak dipilih lagi
 * (RabRepository::sumberTersimpan), jadi pilihan sumber dana kini berupa anggaran_kegiatan.dipilih, bukan ada/tidaknya baris.
 *
 * Item memakai uuid sebagai id (id string RabRepository; "urungkan hapus" menyisipkan kembali id yang sama). Uang
 * rupiah utuh (bigint), volume 2 desimal; jumlah = round(volume × harga satuan) dihitung database (kolom generated,
 * pembulatan setengah menjauhi nol sama dengan KalkulatorRab::jumlah) sehingga tidak bisa berbeda dari rinciannya.
 * Urutan unik per anggaran tetapi DEFERRABLE agar penyisipan/penggeseran urutan dalam satu transaksi tidak bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->boolean('dipilih')->default(true)->after('sumber_dana');
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('anggaran_kegiatan_id')->constrained('anggaran_kegiatan')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('kode', 20)->default('');
            $table->string('uraian');
            $table->enum('kategori', ['bahan', 'jasa', 'lain']);
            $table->decimal('volume', 10, 2);
            $table->string('satuan', 30);
            $table->unsignedBigInteger('harga_satuan');
            $table->bigInteger('jumlah')->storedAs('round(volume * harga_satuan)');
            $table->timestampsTz(6);
        });

        DB::statement('ALTER TABLE budget_items ADD CONSTRAINT budget_items_urutan_unique UNIQUE (anggaran_kegiatan_id, urutan) DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE budget_items ADD CONSTRAINT budget_items_volume_check CHECK (volume > 0)');
        DB::statement('ALTER TABLE budget_items ADD CONSTRAINT budget_items_harga_satuan_check CHECK (harga_satuan > 0)');

        Schema::create('termin_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggaran_kegiatan_id')->constrained('anggaran_kegiatan')->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_termin');
            $table->date('tanggal')->nullable();
            $table->unsignedBigInteger('nominal');

            $table->unique(['anggaran_kegiatan_id', 'nomor_termin']);
        });

        DB::statement('ALTER TABLE termin_pembayaran ADD CONSTRAINT termin_pembayaran_nomor_termin_check CHECK (nomor_termin >= 1)');
        DB::statement('ALTER TABLE termin_pembayaran ADD CONSTRAINT termin_pembayaran_nominal_check CHECK (nominal > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('termin_pembayaran');
        Schema::dropIfExists('budget_items');
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->dropColumn('dipilih');
        });
    }
};
