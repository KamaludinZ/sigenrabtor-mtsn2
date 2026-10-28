<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Basisdata\PindahStrukturAnggaran;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubRab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PindahStrukturAnggaranTest extends TestCase
{
    use RefreshDatabase;

    /** Angka anggaran tiap kegiatan × sumber dana dari repositori yang sedang terpasang. */
    private function anggaran(): array
    {
        $hasil = [];
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            foreach (AnggaranKegiatan::untuk($k, sertakanTerlepas: true) as $a) {
                $hasil[$k['id']][$a->sumber] = [$a->dipilih, $a->pagu(), $a->total(), $a->jumlahItem(), $a->totalTermin(), $a->keteranganPagu()->status];
            }
        }
        ksort($hasil);

        return $hasil;
    }

    private function pakaiDatabase(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    public function test_struktur_anggaran_dipindah_utuh(): void
    {
        $tiruan = $this->anggaran();
        $monitoringTiruan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026])->perSumber();
        $matrikTiruan = app(MatrikJadwalRepository::class)->matrik(3)->butir;
        $item = array_sum(array_map(fn (int $id) => array_sum(array_map(fn (string $s) => count(StubRab::rincian($id, $s)), StubRab::DAFTAR_CONTOH[$id])), array_keys(StubRab::DAFTAR_CONTOH)));

        $hasil = PindahStrukturAnggaran::jalankan();

        $this->assertSame(['tahun' => 2, 'leading' => 9, 'akun' => 6, 'data_pagu' => 6, 'kegiatan' => 3, 'rab' => 5, 'item' => $item], array_intersect_key($hasil, array_flip(['tahun', 'leading', 'akun', 'data_pagu', 'kegiatan', 'rab', 'item'])));
        $this->assertSame(1, $hasil['matrik_jadwal'], 'Hanya contoh 3 yang pernah menyimpan matrik & jadwal.');
        $this->assertSame([2026, true], [DB::table('tahun_anggaran')->where('is_active', true)->value('tahun'), (bool) DB::table('tahun_anggaran')->where('tahun', 2025)->value('is_locked')]);
        $this->assertSame([1, 2, 3], DB::table('activities')->orderBy('id')->pluck('id')->all(), 'Id kegiatan tetap.');
        $this->assertSame(['1.6'], DB::table('user_leading')->where('user_id', 1)->pluck('kode_leading')->all());

        $this->pakaiDatabase();
        $this->assertSame($tiruan, $this->anggaran(), 'Pagu, total RAB, item, termin, dan status pagu sama.');
        $this->assertSame($monitoringTiruan, RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => 2026])->perSumber());
        $this->assertSame($matrikTiruan, app(MatrikJadwalRepository::class)->matrik(3)->butir);
        $this->assertSame('Review RKJM dan Penyusunan RKTM', app(KegiatanRepository::class)->temukan(3)['nama_kegiatan']);

        // Kegiatan baru melanjutkan id; database tidak bisa dipindah dua kali.
        $this->assertSame(4, app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Baru Sesudah Pindah', 'leading' => '1.6']));
        $this->expectException(RuntimeException::class);
        PindahStrukturAnggaran::jalankan();
    }

    public function test_mode_uji_tidak_menyimpan_apa_pun(): void
    {
        $hasil = PindahStrukturAnggaran::jalankan(uji: true);

        $this->assertSame(3, $hasil['kegiatan']);
        foreach (['tahun_anggaran', 'leadings', 'users', 'data_pagu', 'activities', 'budget_items'] as $tabel) {
            $this->assertSame(0, DB::table($tabel)->count(), $tabel);
        }
    }

    public function test_perintah_artisan(): void
    {
        $this->artisan('sigenrabtor:pindah-anggaran', ['--uji' => true])
            ->expectsOutputToContain('Uji pemindahan lolos verifikasi')
            ->assertSuccessful();
        $this->artisan('sigenrabtor:pindah-anggaran')
            ->expectsOutputToContain('Struktur anggaran awal dipindahkan')
            ->assertSuccessful();
        $this->artisan('sigenrabtor:pindah-anggaran')
            ->expectsOutputToContain('pemindahan hanya untuk database kosong')
            ->assertFailed();
    }
}
