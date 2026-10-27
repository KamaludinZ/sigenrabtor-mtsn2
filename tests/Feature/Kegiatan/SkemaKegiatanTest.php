<?php

namespace Tests\Feature\Kegiatan;

use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Livewire\Forms\KegiatanForm;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Tests\TestCase;

class SkemaKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $nama = 'Operator'): int
    {
        return DB::table('users')->insertGetId(['name' => $nama, 'email' => mb_strtolower($nama).'@example.com', 'password' => 'x']);
    }

    /** @param  array<string, mixed>  $data */
    private function kegiatan(array $data = []): int
    {
        return DB::table('activities')->insertGetId($data + ['tahun_anggaran_id' => 1, 'nama_kegiatan' => 'Workshop Kurikulum']);
    }

    private function ditolak(callable $aksi): void
    {
        try {
            // Savepoint: transaksi tes tetap bisa dipakai setelah pelanggaran batasan.
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    public function test_tabel_menampung_semua_isian_data_kegiatan(): void
    {
        $kolom = array_diff(array_keys((new KegiatanForm(new class extends Component {}, 'form'))->dataUntukDisimpan()), ['leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu', 'sumber_dana', 'panitia']);

        $this->assertTrue(Schema::hasColumns('activities', [...$kolom, 'tahun_anggaran_id', 'leading_id', 'user_id', 'kode_rktm_id', 'kode_sk_id', 'kode_iksk_id', 'kode_edm_id', 'budaya_mutu_id', 'kode_hilang', 'asal', 'duplikat_dari_id', 'status', 'dikunci_pada', 'dikunci_oleh', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('anggaran_kegiatan', ['activity_id', 'sumber_dana', 'pagu', 'total_rab', 'terbilang']));
        $this->assertTrue(Schema::hasColumns('committees', ['activity_id', 'urutan', 'nama', 'jabatan', 'peran']));
        $this->assertTrue(Schema::hasColumns('riwayat_status_kegiatan', ['activity_id', 'aksi', 'status', 'user_id', 'oleh', 'peran', 'catatan', 'sidik', 'created_at']));
    }

    public function test_nilai_bawaan_kegiatan_baru(): void
    {
        $k = DB::table('activities')->find($this->kegiatan());

        $this->assertSame(['draf', 'manual', false, true, false, []], [$k->status, $k->asal, $k->cover_manual, $k->laporan_otomatis, $k->hari_kerja, json_decode($k->bulan_pelaksanaan)]);
    }

    public function test_batasan_status_asal_dan_angka(): void
    {
        foreach (StatusKegiatan::cases() as $status) {
            $this->kegiatan(['status' => $status->value]);
        }
        $this->ditolak(fn () => $this->kegiatan(['status' => 'dihapus']));
        $this->ditolak(fn () => $this->kegiatan(['asal' => 'tebakan']));
        $this->ditolak(fn () => $this->kegiatan(['lama_hari' => 0]));
        $this->ditolak(fn () => $this->kegiatan(['lama_hari' => 367]));
        $this->ditolak(fn () => $this->kegiatan(['peserta_jumlah' => 0]));
        $this->ditolak(fn () => $this->kegiatan(['bulan_pelaksanaan' => '{"7": true}']));
        $this->ditolak(fn () => $this->kegiatan(['nama_kegiatan' => null]));
        $this->ditolak(fn () => $this->kegiatan(['tahun_anggaran_id' => null]));
    }

    public function test_anggaran_satu_baris_per_sumber_dana(): void
    {
        $id = $this->kegiatan();
        foreach (SumberDana::cases() as $sumber) {
            DB::table('anggaran_kegiatan')->insert(['activity_id' => $id, 'sumber_dana' => $sumber->value, 'pagu' => 100_000_000_000]);
        }

        $baru = DB::table('anggaran_kegiatan')->find(DB::table('anggaran_kegiatan')->insertGetId(['activity_id' => $this->kegiatan(), 'sumber_dana' => 'DIPA']));
        $this->assertSame([null, 0, null], [$baru->pagu, $baru->total_rab, $baru->terbilang]);
        $this->ditolak(fn () => DB::table('anggaran_kegiatan')->insert(['activity_id' => $id, 'sumber_dana' => 'DIPA']));
        $this->ditolak(fn () => DB::table('anggaran_kegiatan')->insert(['activity_id' => $this->kegiatan(), 'sumber_dana' => 'BOS']));
        $this->ditolak(fn () => DB::table('anggaran_kegiatan')->insert(['activity_id' => $this->kegiatan(), 'sumber_dana' => 'DIPA', 'pagu' => -1]));
        $this->ditolak(fn () => DB::table('anggaran_kegiatan')->insert(['activity_id' => 999, 'sumber_dana' => 'DIPA']));
    }

    public function test_hapus_kegiatan_dan_akun(): void
    {
        $pembuat = $this->pengguna();
        $admin = $this->pengguna('Admin');
        $sumber = $this->kegiatan(['user_id' => $pembuat]);
        $salinan = $this->kegiatan(['duplikat_dari_id' => $sumber, 'user_id' => $pembuat, 'status' => 'final', 'dikunci_pada' => now(), 'dikunci_oleh' => $admin]);
        DB::table('anggaran_kegiatan')->insert(['activity_id' => $sumber, 'sumber_dana' => 'Komite', 'pagu' => 10_000_000]);
        DB::table('committees')->insert(['activity_id' => $sumber, 'urutan' => 1, 'nama' => 'Ahmad Fauzi, S.Pd.', 'peran' => 'Ketua']);
        DB::table('riwayat_status_kegiatan')->insert(['activity_id' => $sumber, 'aksi' => 'dibuat', 'status' => 'draf', 'user_id' => $pembuat, 'oleh' => 'Operator', 'peran' => 'leading']);
        $this->ditolak(fn () => DB::table('committees')->insert(['activity_id' => $sumber, 'urutan' => 1, 'nama' => 'Siti', 'peran' => 'Anggota']));

        // Akun dihapus: kegiatan & riwayat tetap ada tanpa tautan akun, nama pelaku di riwayat tetap terbaca.
        DB::table('users')->whereIn('id', [$pembuat, $admin])->delete();
        $this->assertSame([null, null], [DB::table('activities')->find($salinan)->user_id, DB::table('activities')->find($salinan)->dikunci_oleh]);
        $this->assertSame([null, 'Operator'], [DB::table('riwayat_status_kegiatan')->value('user_id'), DB::table('riwayat_status_kegiatan')->value('oleh')]);

        // Kegiatan dihapus: anggaran, panitia, dan riwayatnya ikut terhapus; salinannya tetap ada tanpa tautan asal.
        DB::table('activities')->delete($sumber);
        $this->assertSame([0, 0, 0], [DB::table('anggaran_kegiatan')->count(), DB::table('committees')->count(), DB::table('riwayat_status_kegiatan')->count()]);
        $this->assertNull(DB::table('activities')->find($salinan)->duplikat_dari_id);
    }

    public function test_waktu_bermikrodetik_untuk_versi(): void
    {
        $id = $this->kegiatan(['updated_at' => '2026-01-03 08:00:00.123456+07']);

        $this->assertStringContainsString('.123456', (string) DB::table('activities')->find($id)->updated_at);
    }
}
