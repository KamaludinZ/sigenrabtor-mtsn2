<?php

namespace Tests\Feature\Pengaturan;

use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubDasarHukumRepository;
use App\Support\Stub\StubPejabatRepository;
use App\Support\Stub\StubPengaturanAiRepository;
use App\Support\Stub\StubProfilMadrasahRepository;
use App\Support\Stub\StubRiwayatPengaturanRepository;
use App\Support\Stub\StubTahunAnggaranRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaPengaturanTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = 'database/migrations/2026_10_28_000020_create_pengaturan_tables.php';

    /** Migrasi sesudahnya yang merujuk tahun_anggaran (jejak unggahan master): dibatalkan lebih dulu, dipasang lagi sesudahnya. */
    private const MIGRASI_MASTER = 'database/migrations/2026_10_28_000026_tighten_master_kode_and_unggahan.php';

    /** Trigger peran akun (000028) bergantung pada kolom users.role yang dibuat 000020. */
    private const MIGRASI_AKUN = 'database/migrations/2026_10_28_000028_tighten_users_and_riwayat_masuk.php';

    /** Dokumen & konfigurasi tahun aktif (000032) merujuk tahun_anggaran dan tabel template yang dibuat 000020. */
    private const MIGRASI_DOKUMEN = 'database/migrations/2026_10_28_000032_create_documents_tables.php';

    /** Buku dokumen (000034): view & tabel yang merujuk leadings dan tahun_anggaran. */
    private const MIGRASI_BUKU = 'database/migrations/2026_10_28_000034_create_buku_dokumen_tables.php';

    /** Impor & duplikat (000035): tabel yang merujuk tahun_anggaran. */
    private const MIGRASI_IMPOR = 'database/migrations/2026_10_28_000035_create_impor_dan_duplikat_tables.php';

    /** Aturan Pengaturan AI (000037): constraint & trigger pada tabel AI yang dibuat 000020. */
    private const MIGRASI_AI = 'database/migrations/2026_10_28_000037_tighten_pengaturan_ai_tables.php';

    private function ditolak(callable $aksi, string $catatan): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("Seharusnya ditolak database: {$catatan}");
    }

    public function test_contoh_pengaturan_tiruan_muat_di_tabel(): void
    {
        RujukanDb::siapkan();
        DB::table('tahun_anggaran')->delete();
        foreach (StubTahunAnggaranRepository::contoh() as $t) {
            DB::table('tahun_anggaran')->insert(Arr::except($t, ['id']));
        }
        DB::table('profil_madrasah')->insert(Arr::except(StubProfilMadrasahRepository::contoh(), ['diperbarui_pada']));
        foreach (StubPejabatRepository::contoh() as $tahun => $pejabat) {
            foreach (['kepala', 'komite'] as $peran) {
                DB::table('pejabat_penanda_tangan')->insert(['tahun_anggaran' => $tahun, 'peran' => $peran] + $pejabat[$peran]);
            }
            foreach ($pejabat['leading'] as $kode => $p) {
                DB::table('pejabat_penanda_tangan')->insert(['tahun_anggaran' => $tahun, 'peran' => 'leading', 'kode_leading' => $kode] + $p);
            }
        }
        foreach (StubDasarHukumRepository::contoh() as $tahun => $d) {
            foreach ($d['dasar_hukum'] as $i => $butir) {
                DB::table('dasar_hukum')->insert(['id' => (string) Str::uuid(), 'tahun_anggaran' => $tahun, 'urutan' => $i + 1, 'teks' => $butir['teks']]);
            }
            DB::table('latar_belakang_baku')->insert(['tahun_anggaran' => $tahun] + Arr::only($d, ['latar_belakang', 'kalimat_penutup']));
        }
        foreach (PromptAi::BAWAAN as $bagian => $instruksi) {
            DB::table('prompt_templates')->insert(['bagian' => $bagian, 'instruksi' => $instruksi]);
        }
        DB::table('prompt_templates')->insert(['bagian' => 'umum', 'gaya_bahasa' => Str::limit(PromptAi::GAYA_BAHASA, 300, '')]);
        $ai = StubPengaturanAiRepository::bawaan();
        DB::table('ai_settings')->insert(['parameter' => json_encode($ai['parameter']), 'batas_per_user_per_hari' => $ai['batas_per_user_per_hari']]);
        // Model kosong tiruan ('') tersimpan sebagai null di database.
        DB::table('ai_provider_settings')->insert(['penyedia' => 'ollama'] + array_map(fn ($v) => $v === '' ? null : $v, Arr::only($ai['penyedia']['ollama'], ['base_url', 'model', 'batas_waktu'])));
        foreach (StubRiwayatPengaturanRepository::contoh() as $r) {
            DB::table('activity_logs')->insert(['user_id' => $r['user_id'], 'pengguna' => $r['pengguna'], 'aksi' => $r['aksi'], 'keterangan' => $r['keterangan'], 'tahun_anggaran' => $r['tahun'], 'created_at' => $r['waktu']]);
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->assertSame(2026, DB::table('tahun_anggaran')->where('is_active', true)->value('tahun'));
        $this->assertSame(4, DB::table('pejabat_penanda_tangan')->where('tahun_anggaran', 2025)->count());
        $this->assertSame(7, DB::table('dasar_hukum')->count());
        $this->assertSame(['1.6'], DB::table('user_leading')->where('user_id', 1)->pluck('kode_leading')->all());
        $this->assertTrue(Schema::hasColumns('users', ['role', 'nip', 'jabatan', 'is_active', 'terakhir_masuk']));
    }

    public function test_aturan_tahun_leading_dan_akun(): void
    {
        RujukanDb::siapkan();

        $this->ditolak(fn () => DB::table('tahun_anggaran')->insert(['tahun' => 2027, 'is_active' => true]), 'dua tahun aktif');
        $this->ditolak(fn () => DB::table('tahun_anggaran')->where('tahun', 2026)->update(['no_sk_rktm' => '001/SK']), 'SK tanpa tanggal');
        $this->ditolak(fn () => DB::table('tahun_anggaran')->insert(['tahun' => 1999]), 'tahun di luar rentang');
        DB::table('tahun_anggaran')->where('tahun', 2026)->update(['no_sk_rktm' => '001/SK', 'tanggal_sk_rktm' => '2026-01-06']);

        $this->ditolak(fn () => DB::table('leadings')->insert(['kode' => '1.10.1', 'nama_unit' => 'Baru', 'jabatan_penanda_tangan' => 'Kepala']), 'kode leading tidak n.n');
        $this->ditolak(fn () => DB::table('leadings')->insert(['kode' => '1.10', 'nama_unit' => ' waka KURIKULUM ', 'jabatan_penanda_tangan' => 'Kepala']), 'nama unit ganda');
        $this->ditolak(fn () => DB::table('leadings')->insert(['kode' => '1.10', 'nama_unit' => 'Baru', 'jabatan_penanda_tangan' => 'Kepala', 'urutan' => 1000]), 'urutan > 999');
        DB::table('leadings')->insert(['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 10]);

        $akun = ['name' => 'Akun Uji', 'password' => '-'];
        $this->ditolak(fn () => DB::table('users')->insert($akun + ['email' => 'Uji@Example.com']), 'surel berhuruf besar');
        $this->ditolak(fn () => DB::table('users')->insert($akun + ['email' => 'uji@example.com', 'role' => 'operator']), 'peran asing');
        $this->ditolak(fn () => DB::table('users')->insert($akun + ['email' => 'uji@example.com', 'nip' => '19800110 200604 1 001']), 'NIP berpemisah');
        $id = DB::table('users')->insertGetId($akun + ['email' => 'uji@example.com', 'nip' => '198001102006041001']);
        $this->ditolak(fn () => DB::table('user_leading')->insert(['user_id' => $id, 'kode_leading' => '9.9']), 'leading tak dikenal');

        // Kode leading yang diubah ikut berubah di tautan akun dan kegiatan.
        DB::table('user_leading')->insert(['user_id' => $id, 'kode_leading' => '1.10']);
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Gerakan Literasi', 'kode_leading' => '1.10', 'user_id' => $id]);
        DB::table('leadings')->where('kode', '1.10')->update(['kode' => '1.11']);
        $this->assertSame(['1.11', '1.11'], [DB::table('user_leading')->where('user_id', $id)->value('kode_leading'), DB::table('activities')->where('id', $kegiatan)->value('kode_leading')]);

        DB::table('users')->where('id', $id)->delete();
        $this->assertNull(DB::table('activities')->where('id', $kegiatan)->value('user_id'), 'Pembuat yang dihapus dikosongkan.');
    }

    public function test_rujukan_kegiatan_master_dan_data_pagu_diperiksa_saat_commit(): void
    {
        RujukanDb::siapkan();

        // Tertunda: kegiatan boleh disimpan sebelum tahunnya dalam satu transaksi.
        DB::transaction(function () {
            DB::table('activities')->insert(['tahun_anggaran' => 2027, 'nama_kegiatan' => 'Kegiatan 2027']);
            DB::table('tahun_anggaran')->insert(['tahun' => 2027]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });

        foreach ([
            'kegiatan tahun tak ada' => fn () => DB::table('activities')->insert(['tahun_anggaran' => 2030, 'nama_kegiatan' => 'X']),
            'kegiatan leading tak ada' => fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'X', 'kode_leading' => '9.9']),
            'kegiatan pembuat tak ada' => fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'X', 'user_id' => 999]),
            'master tahun tak ada' => fn () => DB::table('kode_sk')->insert(['tahun_anggaran' => 2030, 'kode' => 'SK.1', 'uraian' => 'Uji', 'urutan' => 1]),
            'data pagu leading tak ada' => fn () => DB::table('data_pagu')->insert(['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'nama_kegiatan' => 'Uji', 'kode_leading' => '9.9', 'asal' => 'manual']),
        ] as $catatan => $aksi) {
            $this->ditolak(function () use ($aksi) {
                $aksi();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            }, $catatan);
        }
    }

    public function test_aturan_pejabat_dasar_hukum_dan_template(): void
    {
        RujukanDb::siapkan();
        $pejabat = ['tahun_anggaran' => 2026, 'jabatan' => 'Kepala MTsN 2 Kota Malang'];
        DB::table('pejabat_penanda_tangan')->insert($pejabat + ['peran' => 'kepala']);
        $this->ditolak(fn () => DB::table('pejabat_penanda_tangan')->insert($pejabat + ['peran' => 'kepala']), 'kepala ganda satu tahun');
        $this->ditolak(fn () => DB::table('pejabat_penanda_tangan')->insert($pejabat + ['peran' => 'leading']), 'pejabat leading tanpa kode');
        $this->ditolak(fn () => DB::table('pejabat_penanda_tangan')->insert($pejabat + ['peran' => 'komite', 'kode_leading' => '1.6']), 'komite berkode leading');
        $this->ditolak(fn () => DB::table('pejabat_penanda_tangan')->insert($pejabat + ['peran' => 'komite', 'nip' => '123']), 'NIP tidak 18 angka');
        DB::table('aturan_mengetahui')->insert(['tahun_anggaran' => 2026, 'sumber_dana' => 'DIPA', 'tampil' => false]);
        $this->ditolak(fn () => DB::table('aturan_mengetahui')->insert(['tahun_anggaran' => 2026, 'sumber_dana' => 'BOS']), 'sumber dana asing');

        $butir = fn (int $urutan, ?string $teks = null) => ['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'urutan' => $urutan, 'teks' => $teks ?? "Undang-Undang Nomor {$urutan} Tahun 2003"];
        DB::table('dasar_hukum')->insert([$butir(1), $butir(2)]);
        $this->ditolak(fn () => DB::table('dasar_hukum')->insert($butir(3, 'undang-undang nomor 1 tahun 2003')), 'butir dobel satu tahun');
        $this->ditolak(fn () => DB::table('dasar_hukum')->insert($butir(2)), 'urutan ganda');
        $this->ditolak(fn () => DB::table('dasar_hukum')->insert($butir(3, '  ')), 'butir kosong');
        $this->ditolak(fn () => DB::table('dasar_hukum')->insert($butir(31)), 'lebih dari 30 butir');
        // Urutan butir bisa ditukar dalam satu transaksi (unik tertunda).
        [$satu, $dua] = DB::table('dasar_hukum')->orderBy('urutan')->pluck('id')->all();
        DB::transaction(function () use ($satu, $dua) {
            DB::statement('SET CONSTRAINTS dasar_hukum_urutan_unik DEFERRED');
            DB::table('dasar_hukum')->where('id', $satu)->update(['urutan' => 2]);
            DB::table('dasar_hukum')->where('id', $dua)->update(['urutan' => 1]);
            DB::statement('SET CONSTRAINTS dasar_hukum_urutan_unik IMMEDIATE');
        });
        $this->assertSame([$dua, $satu], DB::table('dasar_hukum')->orderBy('urutan')->pluck('id')->all());

        $latar = ['tahun_anggaran' => 2026, 'latar_belakang' => 'Paragraf baku {tahun}.'];
        $this->ditolak(fn () => DB::table('latar_belakang_baku')->insert($latar + ['kalimat_penutup' => 'Tanpa nama kegiatan.']), 'penutup tanpa {nama_kegiatan}');
        $this->ditolak(fn () => DB::table('latar_belakang_baku')->insert($latar + ['kalimat_penutup' => '{nama_kegiatan} dan {nama_kegiatan}.']), '{nama_kegiatan} dua kali');
        DB::table('latar_belakang_baku')->insert($latar + ['kalimat_penutup' => 'Salah satunya kegiatan {nama_kegiatan}.']);

        $cover = fn (bool $default) => ['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'nama' => 'Cover', 'gambar_path' => 'cover.png', 'lebar' => 595, 'tinggi' => 842, 'is_default' => $default];
        DB::table('cover_templates')->insert([$cover(true), $cover(false)]);
        $this->ditolak(fn () => DB::table('cover_templates')->insert($cover(true)), 'dua cover default');
        DB::table('cover_templates')->insert(['kode_leading' => '1.6', 'sumber_dana' => 'Komite'] + $cover(true));

        $master = fn (string $jenis, bool $default) => ['id' => (string) Str::uuid(), 'tahun_anggaran' => 2026, 'nama' => 'Master', 'jenis' => $jenis, 'file_path' => 'master.docx', 'nama_berkas' => 'master.docx', 'is_default' => $default];
        DB::table('document_templates')->insert([$master('KAK+TOR', true), $master('RAB', true)]);
        $this->ditolak(fn () => DB::table('document_templates')->insert($master('RAB', true)), 'dua default satu jenis');
        $this->ditolak(fn () => DB::table('document_templates')->insert($master('SPJ', false)), 'jenis asing');

        DB::table('daftar_isi_templates')->insert([['tahun_anggaran' => 2026, 'kode_leading' => null, 'sumber_dana' => null, 'konfigurasi' => '{}'], ['tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'sumber_dana' => 'DIPA', 'konfigurasi' => '{}']]);
        $this->ditolak(fn () => DB::table('daftar_isi_templates')->insert(['tahun_anggaran' => 2026, 'konfigurasi' => '{}']), 'dua bawaan');
        $this->ditolak(fn () => DB::table('daftar_isi_templates')->insert(['tahun_anggaran' => 2026, 'kode_leading' => '1.6', 'konfigurasi' => '{}']), 'khusus tanpa sumber');
        $this->ditolak(fn () => DB::table('page_number_settings')->insert(['tahun_anggaran' => 2026, 'aturan' => '[]']), 'aturan bukan objek');
    }

    public function test_aturan_ai_dan_riwayat_hanya_tambah(): void
    {
        RujukanDb::siapkan();
        $this->ditolak(fn () => DB::table('prompt_templates')->insert(['bagian' => 'tujuan', 'instruksi' => 'Terlalu pendek']), 'instruksi < 20 karakter');
        $this->ditolak(fn () => DB::table('prompt_templates')->insert(['bagian' => 'umum', 'instruksi' => str_repeat('a', 30)]), 'baris umum berinstruksi');
        $this->ditolak(fn () => DB::table('prompt_templates')->insert(['bagian' => 'penutup', 'instruksi' => str_repeat('a', 30)]), 'bagian asing');

        DB::table('ai_settings')->insert(['provider_aktif' => 'ollama', 'provider_cadangan' => 'claude']);
        $this->ditolak(fn () => DB::table('ai_settings')->insert(['id' => 2]), 'baris kedua');
        $this->ditolak(fn () => DB::table('ai_settings')->update(['provider_cadangan' => 'ollama']), 'cadangan = aktif');
        $this->ditolak(fn () => DB::table('ai_settings')->update(['provider_aktif' => null]), 'cadangan tanpa aktif');
        $this->ditolak(fn () => DB::table('ai_settings')->update(['batas_per_user_per_hari' => 201]), 'batas > 200');
        $this->ditolak(fn () => DB::table('ai_provider_settings')->insert(['penyedia' => 'claude', 'kunci_terenkripsi' => 'xxx']), 'kunci tanpa 4 karakter akhir');
        $this->ditolak(fn () => DB::table('ai_provider_settings')->insert(['penyedia' => 'ollama', 'batas_waktu' => 5]), 'batas waktu < 10');

        $id = DB::table('activity_logs')->insertGetId(['user_id' => 3, 'pengguna' => 'Siti Rohmah, M.Pd.', 'aksi' => 'ubah_profil', 'keterangan' => 'Nama: A → B', 'tahun_anggaran' => 2026]);
        $this->ditolak(fn () => DB::table('activity_logs')->where('id', $id)->update(['keterangan' => 'diubah']), 'riwayat diubah');
        $this->ditolak(fn () => DB::table('activity_logs')->where('id', $id)->delete(), 'riwayat dihapus');
        DB::table('users')->where('id', 3)->delete();
        $this->assertSame([null, 'Nama: A → B'], [DB::table('activity_logs')->where('id', $id)->value('user_id'), DB::table('activity_logs')->where('id', $id)->value('keterangan')], 'Akun dihapus: rujukan dikosongkan, isi tetap.');
    }

    public function test_migrasi_bisa_dibatalkan(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_AI])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_IMPOR])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_BUKU])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_DOKUMEN])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_AKUN])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI_MASTER])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('tahun_anggaran'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        DB::table('activities')->insert(['tahun_anggaran' => 2030, 'nama_kegiatan' => 'Bebas']);
        DB::table('activities')->delete();
        // Peristiwa trigger tertunda (alur status) diperiksa dulu: tabel dengan peristiwa tertunda tidak bisa di-ALTER.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $this->artisan('migrate', ['--path' => self::MIGRASI])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_MASTER])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_AKUN])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_DOKUMEN])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_BUKU])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_IMPOR])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRASI_AI])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('activity_logs'));
        $this->assertTrue(Schema::hasTable('master_kode_unggahan'));
    }
}
