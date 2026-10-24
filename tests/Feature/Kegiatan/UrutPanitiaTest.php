<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class UrutPanitiaTest extends TestCase
{
    /**
     * @param  list<array{0: string, 1: string}>  $anggota  [nama, peran]
     */
    private function denganAnggota(array $anggota): Testable
    {
        $component = Livewire::test(Form::class);

        foreach ($anggota as $i => [$nama, $peran]) {
            $component->call('tambahPanitia')
                ->set("form.panitia.$i.nama", $nama)
                ->set("form.panitia.$i.peran", $peran);
        }

        return $component;
    }

    /**
     * @return list<string>
     */
    private function nama(Testable $component): array
    {
        return array_column($component->get('form.panitia'), 'nama');
    }

    public function test_seret_lepas_memindahkan_anggota_ke_posisi_baru(): void
    {
        $component = $this->denganAnggota([['Ahmad', 'Anggota'], ['Budi', 'Anggota'], ['Citra', 'Anggota'], ['Dewi', 'Anggota']]);
        $keyCitra = $component->get('form.panitia.2.key');

        $component->call('pindahkanPanitia', $keyCitra, 0);
        $this->assertSame(['Citra', 'Ahmad', 'Budi', 'Dewi'], $this->nama($component));

        $component->call('pindahkanPanitia', $keyCitra, 3);
        $this->assertSame(['Ahmad', 'Budi', 'Dewi', 'Citra'], $this->nama($component));
    }

    public function test_seret_dengan_key_tidak_dikenal_diabaikan(): void
    {
        $component = $this->denganAnggota([['Ahmad', 'Ketua'], ['Budi', 'Anggota']])
            ->call('pindahkanPanitia', 'tidak-ada', 0);

        $this->assertSame(['Ahmad', 'Budi'], $this->nama($component));
    }

    public function test_hapus_bisa_diurungkan_ke_posisi_semula(): void
    {
        $component = $this->denganAnggota([['Ahmad', 'Ketua'], ['Budi', 'Sekretaris'], ['Citra', 'Anggota']])
            ->call('hapusPanitia', 1)
            ->assertSee('“Budi” dihapus dari susunan tim.')
            ->assertSee('Urungkan');

        $this->assertSame(['Ahmad', 'Citra'], $this->nama($component));

        $component->call('urungkanHapusPanitia')->assertDontSee('dihapus dari susunan tim');
        $this->assertSame(['Ahmad', 'Budi', 'Citra'], $this->nama($component));
    }

    public function test_aksi_lain_menutup_kesempatan_urungkan(): void
    {
        $this->denganAnggota([['Ahmad', 'Ketua'], ['Budi', 'Anggota']])
            ->call('hapusPanitia', 0)
            ->call('tambahPanitia')
            ->assertSet('panitiaTerhapus', null)
            ->assertDontSee('dihapus dari susunan tim');
    }

    public function test_data_urungkan_tidak_bisa_diubah_dari_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Form::class)->set('panitiaTerhapus', ['index' => 0, 'anggota' => ['key' => 'x', 'nama' => 'Palsu', 'jabatan' => '', 'peran' => 'Ketua']]);
    }

    public function test_urutkan_sesuai_hierarki_peran_dan_stabil(): void
    {
        $component = $this->denganAnggota([
            ['Dewi', 'Anggota'],
            ['Eko', 'Dokumentasi'],
            ['Budi', 'Sekretaris'],
            ['Fajar', 'Anggota'],
            ['Ahmad', 'ketua'],
            ['Gita', 'Pengarah'],
        ])->call('urutkanPanitiaSesuaiPeran')
            ->assertDispatched('notify');

        $this->assertSame(['Gita', 'Ahmad', 'Budi', 'Eko', 'Dewi', 'Fajar'], $this->nama($component));
    }

    public function test_peringatan_nama_ganda(): void
    {
        $this->denganAnggota([['Ahmad Fauzi', 'Ketua'], ['ahmad  fauzi ', 'Anggota'], ['Budi', 'Anggota']])
            ->assertSee('Nama ini tercantum lebih dari sekali.');
    }
}
