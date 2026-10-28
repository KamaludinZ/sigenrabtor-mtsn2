<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaAlurStatusTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    protected function setUp(): void
    {
        parent::setUp();
        RujukanDb::siapkan();
    }

    /**
     * Jalankan $aksi dalam savepoint lalu periksa semua constraint tertunda seketika (padanan commit; SET CONSTRAINTS
     * IMMEDIATE memeriksa peristiwa yang tertunda secara surut), kemudian kembali ke mode tertunda.
     */
    private function sebagaiCommit(callable $aksi): void
    {
        DB::transaction(function () use ($aksi) {
            $aksi();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        });
    }

    private function ditolak(callable $aksi, string $alasan): void
    {
        try {
            $this->sebagaiCommit($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("Seharusnya ditolak database: {$alasan}.");
    }

    private function kegiatan(string $status = 'draf'): int
    {
        return DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Uji Alur Status', 'kode_leading' => '1.6', 'status' => $status]);
    }

    /** @param  array<string, mixed>  $isi */
    private function riwayat(int $id, string $aksi, string $status, array $isi = []): void
    {
        DB::table('riwayat_status_kegiatan')->insert($isi + ['activity_id' => $id, 'aksi' => $aksi, 'status' => $status, 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin']);
    }

    public function test_rujukan_aksi_sama_dengan_enum(): void
    {
        $harapan = ['dibuat' => [StatusKegiatan::labelRiwayat('dibuat'), 'draf', ['leading', 'admin'], false, []]];
        foreach (StatusKegiatan::semuaAksi() as $aksi => $a) {
            $harapan[$aksi] = [$a['label'], $a['ke']->value, $a['peran'], isset(RiwayatStatus::CATATAN_WAJIB[$aksi]), array_map(fn (StatusKegiatan $s) => $s->value, $a['dari'])];
        }
        ksort($harapan);

        $db = [];
        foreach (DB::table('aksi_status')->orderBy('aksi')->get() as $r) {
            $dari = DB::table('aksi_status_dari')->where('aksi', $r->aksi)->pluck('dari')->all();
            $urutan = array_column(StatusKegiatan::cases(), 'value');
            usort($dari, fn ($a, $b) => array_search($a, $urutan, true) <=> array_search($b, $urutan, true));
            $db[$r->aksi] = [$r->label, $r->ke, json_decode($r->peran, true), (bool) $r->catatan_wajib, $dari];
        }
        $this->assertSame($harapan, $db, 'Aksi baru di StatusKegiatan wajib lewat migrasi.');
    }

    public function test_aturan_riwayat_dan_jejak_kunci(): void
    {
        $id = $this->kegiatan();
        $this->ditolak(fn () => $this->riwayat($id, 'setujui', 'draf'), 'aksi tidak berujung status itu');
        $this->ditolak(fn () => $this->riwayat($id, 'hapus', 'draf'), 'aksi tidak dikenal');
        $this->ditolak(fn () => $this->riwayat($id, 'dibuat', 'draf', ['peran' => 'sistem']), 'peran asing');
        $this->ditolak(fn () => $this->riwayat($id, 'dibuat', 'draf', ['oleh' => ' ']), 'pelaku kosong');
        $this->ditolak(fn () => $this->riwayat($id, 'dibuat', 'draf', ['catatan' => str_repeat('c', RiwayatStatus::MAKS_CATATAN + 1)]), 'catatan terlalu panjang');
        $this->riwayat($id, 'dibuat', 'draf', ['peran' => 'leading']);

        $lain = $this->kegiatan('diperiksa');
        $this->riwayat($lain, 'ajukan', 'diperiksa', ['peran' => 'leading']);
        $this->ditolak(function () use ($lain) {
            DB::table('activities')->where('id', $lain)->update(['status' => 'dikembalikan']);
            $this->riwayat($lain, 'kembalikan', 'dikembalikan', ['catatan' => '  ']);
        }, 'kembalikan tanpa catatan');

        $this->ditolak(fn () => DB::table('activities')->where('id', $id)->update(['dikunci_pada' => now()]), 'jejak kunci di luar Final');
        $this->ditolak(fn () => DB::table('activities')->where('id', $id)->update(['dikunci_oleh' => 2]), 'pengunci tanpa waktu kunci');
        $this->ditolak(fn () => DB::table('activities')->insert(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Final Asing', 'status' => 'final', 'dikunci_pada' => now(), 'dikunci_oleh' => 999]), 'pengunci bukan akun');
    }

    public function test_perpindahan_status_sah_dan_sesuai_riwayat(): void
    {
        $id = $this->kegiatan();
        $this->riwayat($id, 'dibuat', 'draf', ['peran' => 'leading']);

        $this->ditolak(function () use ($id) {
            DB::table('activities')->where('id', $id)->update(['status' => 'disetujui']);
            $this->riwayat($id, 'setujui', 'disetujui');
        }, 'draf langsung disetujui');
        $this->ditolak(fn () => DB::table('activities')->where('id', $id)->update(['status' => 'diperiksa']), 'status berubah tanpa riwayat');

        $this->sebagaiCommit(function () use ($id) {
            DB::table('activities')->where('id', $id)->update(['status' => 'diperiksa']);
            $this->riwayat($id, 'ajukan', 'diperiksa', ['peran' => 'leading']);
        });
        $this->ditolak(fn () => $this->riwayat($id, 'tarik', 'draf', ['peran' => 'leading']), 'riwayat tanpa perubahan status');
        $this->sebagaiCommit(function () use ($id) {
            DB::table('activities')->where('id', $id)->update(['status' => 'disetujui']);
            $this->riwayat($id, 'setujui', 'disetujui');
        });
        $this->assertSame('disetujui', DB::table('activities')->where('id', $id)->value('status'));
    }

    public function test_alur_lewat_repositori_dan_view_pemeriksaan(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Alur Repositori', 'leading' => '1.6', 'riwayat_status' => [RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, self::LEADING)]]);

        $riwayat = $repo->temukan($id)['riwayat_status'];
        $langkah = [
            ['ajukan', StatusKegiatan::Diperiksa, self::LEADING, null],
            ['kembalikan', StatusKegiatan::Dikembalikan, self::ADMIN, 'Lengkapi sasaran kegiatan.'],
            ['ajukan', StatusKegiatan::Diperiksa, self::LEADING, null],
            ['setujui', StatusKegiatan::Disetujui, self::ADMIN, null],
            ['finalkan', StatusKegiatan::Final, self::LEADING, null],
            ['buka_kunci', StatusKegiatan::Dikembalikan, self::ADMIN, 'Salah nominal termin.'],
        ];
        foreach ($langkah as [$aksi, $ke, $pengguna, $catatan]) {
            session(['stub_role' => $pengguna['role']]); // user_id riwayat = akun yang sedang masuk.
            $riwayat[] = RiwayatStatus::entri($aksi, $ke, $pengguna, $catatan);
            $this->sebagaiCommit(fn () => $repo->simpan(['status' => $ke->value, 'riwayat_status' => $riwayat], $id));
            $k = DB::table('activities')->find($id);
            $this->assertSame([$ke->value, $ke === StatusKegiatan::Final], [$k->status, $k->dikunci_pada !== null], "Sesudah {$aksi}.");
        }

        $this->assertSame([['dikembalikan', 'Lengkapi sasaran kegiatan.'], ['disetujui', null], ['dibuka', 'Salah nominal termin.']],
            DB::table('pemeriksaan_dokumen')->where('activity_id', $id)->orderBy('id')->get()->map(fn ($r) => [$r->keputusan, $r->catatan])->all());
        $this->assertSame([2], DB::table('pemeriksaan_dokumen')->where('activity_id', $id)->distinct()->pluck('pemeriksa_id')->all());
    }
}
