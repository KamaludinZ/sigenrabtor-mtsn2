<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Support\Kegiatan\AksesKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

class AksesKegiatanTest extends TestCase
{
    /** @return array{lain: int, buatanSendiri: int} */
    private function siapkan(): array
    {
        $repo = app(KegiatanRepository::class);

        return [
            'lain' => $repo->simpan(['nama_kegiatan' => 'Kegiatan Waka Kesiswaan', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]),
            'buatanSendiri' => $repo->simpan(['nama_kegiatan' => 'Dibuat Muslimin untuk Kesiswaan', 'leading' => '1.2', 'user_id' => 1]),
        ];
    }

    public function test_aturan_lihat_dan_saring(): void
    {
        ['lain' => $lain, 'buatanSendiri' => $sendiri] = $this->siapkan();
        $repo = app(KegiatanRepository::class);
        $leading = ['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']];

        $this->assertFalse(AksesKegiatan::bolehLihat($repo->temukan($lain), $leading));
        $this->assertTrue(AksesKegiatan::bolehLihat($repo->temukan($sendiri), $leading), 'Kegiatan buatannya sendiri.');
        $this->assertTrue(AksesKegiatan::bolehLihat($repo->temukan(1), $leading), 'Leading tertaut.');
        $this->assertTrue(AksesKegiatan::bolehLihat($repo->temukan($lain), ['id' => 2, 'role' => 'admin', 'leadings' => []]));

        $semua = $repo->semua();
        $this->assertSame(count($semua), count(AksesKegiatan::saring($semua, ['id' => 2, 'role' => 'admin'])));
        $this->assertNotContains($lain, array_column(AksesKegiatan::saring($semua, $leading), 'id'));
        $this->assertContains($sendiri, array_column(AksesKegiatan::saring($semua, $leading), 'id'));
    }

    public function test_halaman_ruang_kerja_kegiatan_leading_lain_ditolak(): void
    {
        ['lain' => $lain] = $this->siapkan();

        foreach (['kegiatan.edit', 'kegiatan.kode', 'kegiatan.rab', 'kegiatan.matrik', 'kegiatan.kalimat', 'kegiatan.pratinjau'] as $rute) {
            $this->get(route($rute, ['kegiatan' => $lain]))->assertForbidden();
            $this->get(route($rute, ['kegiatan' => 1]))->assertOk();
        }
        $this->get(route('kegiatan.arsip', ['kegiatan' => $lain, 'sumber' => 'Komite']))->assertForbidden();
        $this->get(route('kegiatan.edit', ['kegiatan' => 999]))->assertNotFound();

        $this->withSession(['stub_role' => 'admin']);
        foreach (['kegiatan.edit', 'kegiatan.rab', 'kegiatan.pratinjau'] as $rute) {
            $this->get(route($rute, ['kegiatan' => $lain]))->assertOk();
        }
    }

    public function test_tabel_data_kegiatan_per_peran(): void
    {
        ['lain' => $lain, 'buatanSendiri' => $sendiri] = $this->siapkan();

        $leading = array_column(Livewire::test(Daftar::class)->instance()->semuaBaris(), 'id');
        $this->assertNotContains($lain, $leading);
        $this->assertContains($sendiri, $leading);

        $this->withSession(['stub_role' => 'admin']);
        $admin = array_column(Livewire::test(Daftar::class)->instance()->semuaBaris(), 'id');
        $this->assertContains($lain, $admin);
        $this->assertSame(count(app(KegiatanRepository::class)->semua()), count($admin));
    }
}
