<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\LeadingUser;
use Livewire\Livewire;
use Tests\TestCase;

class TautanLeadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function leadingAkun(int $id): array
    {
        return app(PenggunaRepository::class)->temukan($id)['leadings'];
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    public function test_panel_dan_calon_tautan(): void
    {
        $komponen = Livewire::test(LeadingUser::class)
            ->call('bukaTautan', '1.3')
            ->assertSet('tautanLeading', '1.3')
            ->assertSeeHtml('data-testid="panel-tautan-leading"')
            ->assertSee('Belum ada akun — kegiatan Waka Sarpras hanya bisa diisi admin.');
        // Calon: akun berperan leading yang belum tertaut, aktif lebih dulu; admin tidak pernah.
        $this->assertSame([4, 1, 3, 6], array_column($komponen->instance()->calonTautan('1.3'), 'id'));
        $this->assertSame([4, 3, 6], array_column($komponen->instance()->calonTautan('1.6'), 'id'));

        $komponen->call('bukaTautan', '1.3')->assertSet('tautanLeading', null, 'Klik lagi menutup panel.');
        $komponen->call('bukaTautan', 'tidak-ada')->assertSet('tautanLeading', null);
        $komponen->call('bukaTautan', '1.6')
            ->assertSeeHtmlInOrder(['data-testid="panel-tautan-leading"', 'data-tautan-akun="1"', 'Muslimin, M.Pd.', 'hanya leading ini'])
            ->call('tutupTautan')
            ->assertSet('tautanLeading', null);
    }

    public function test_tautkan_dan_lepas_akun(): void
    {
        $komponen = Livewire::test(LeadingUser::class)
            ->call('bukaTautan', '1.3')
            ->call('tautkanAkun')
            ->assertHasErrors(['akunDitautkan'])
            ->set('akunDitautkan', '4')
            ->call('tautkanAkun')
            ->assertHasNoErrors()
            ->assertSet('akunDitautkan', '')
            ->assertDispatched('notify', message: 'Akun Ahmad Fauzan, S.Pd. ditautkan ke 1.3 Waka Sarpras.', type: 'success')
            ->assertSeeHtmlInOrder(['data-testid="panel-tautan-leading"', 'data-tautan-akun="4"', 'juga 1.2, 1.4']);
        $this->assertSame(['1.2', '1.3', '1.4'], $this->leadingAkun(4), 'Tautan tersusun seperti urutan leading.');
        $this->assertSame(['ubah_tautan_leading', 'Akun Ahmad Fauzan, S.Pd. ditautkan ke 1.3 Waka Sarpras'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);

        // Akun admin tidak bisa ditautkan walau idnya dikirim.
        $komponen->set('akunDitautkan', '2')->call('tautkanAkun')->assertHasErrors(['akunDitautkan']);
        $this->assertSame([], $this->leadingAkun(2));

        $komponen->call('lepasTautan', 4)
            ->assertDispatched('notify', message: 'Akun Ahmad Fauzan, S.Pd. dilepas dari 1.3 Waka Sarpras.', type: 'success');
        $this->assertSame(['1.2', '1.4'], $this->leadingAkun(4));

        // Melepas tautan satu-satunya: diberi tahu akun belum tertaut.
        $komponen->call('bukaTautan', '1.1')->call('lepasTautan', 3)
            ->assertDispatched('notify', message: 'Siti Rohmah, M.Pd. kini belum tertaut ke leading mana pun — akun ini belum bisa membuat kegiatan.', type: 'info');
        $this->assertSame([], $this->leadingAkun(3));
        $komponen->call('lepasTautan', 99);
    }

    public function test_leading_nonaktif_hanya_bisa_dilepas(): void
    {
        $repo = app(LeadingRepository::class);
        $repo->simpan(['is_active' => false] + collect($repo->semua())->firstWhere('kode', '===', '1.7'), '1.7');

        Livewire::test(LeadingUser::class)
            ->call('bukaTautan', '1.7')
            ->assertSee('Leading nonaktif tidak bisa diberi tautan baru; tautan lama bisa dilepas.')
            ->set('akunDitautkan', '4')
            ->call('tautkanAkun')
            ->assertHasErrors(['akunDitautkan' => 'Leading nonaktif tidak bisa diberi tautan baru.']);
        $this->assertNotContains('1.7', $this->leadingAkun(4));
    }
}
