<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Forms\KegiatanForm;
use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class PanitiaTest extends TestCase
{
    public function test_daftar_kosong_menampilkan_ajakan_susunan_baku(): void
    {
        Livewire::test(Form::class)
            ->assertSee('Belum ada anggota tim')
            ->assertSee('Pakai susunan baku')
            ->assertSee('Kepala MTsN 2 Kota Malang');
    }

    public function test_susunan_baku_mengisi_empat_peran(): void
    {
        $component = Livewire::test(Form::class)
            ->call('isiSusunanBaku')
            ->assertDispatched('panitia-ditambah');

        $this->assertSame(
            ['Ketua', 'Sekretaris', 'Bendahara', 'Anggota'],
            array_column($component->get('form.panitia'), 'peran'),
        );
    }

    public function test_tambah_hapus_dan_geser_anggota(): void
    {
        $component = Livewire::test(Form::class)
            ->call('tambahPanitia')
            ->call('tambahPanitia')
            ->call('tambahPanitia')
            ->set('form.panitia.0.nama', 'Ahmad')
            ->set('form.panitia.1.nama', 'Budi')
            ->set('form.panitia.2.nama', 'Citra')
            ->call('geserPanitia', 2, -1)
            ->call('hapusPanitia', 0);

        $this->assertSame(['Citra', 'Budi'], array_column($component->get('form.panitia'), 'nama'));
    }

    public function test_geser_di_ujung_daftar_diabaikan(): void
    {
        $component = Livewire::test(Form::class)
            ->call('tambahPanitia')
            ->set('form.panitia.0.nama', 'Ahmad')
            ->call('geserPanitia', 0, -1)
            ->call('geserPanitia', 0, 1);

        $this->assertSame(['Ahmad'], array_column($component->get('form.panitia'), 'nama'));
    }

    public function test_nama_anggota_wajib_saat_simpan_draf(): void
    {
        Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum Madrasah')
            ->call('tambahPanitia')
            ->call('saveDraft')
            ->assertHasErrors(['form.panitia.0.nama' => 'required']);
    }

    public function test_error_baris_dibuang_setelah_urutan_berubah(): void
    {
        Livewire::test(Form::class)
            ->call('tambahPanitia')
            ->call('tambahPanitia')
            ->set('form.panitia.0.nama', '')
            ->assertHasErrors('form.panitia.0.nama')
            ->call('hapusPanitia', 0)
            ->assertHasNoErrors('form.panitia.0.nama');
    }

    public function test_jumlah_anggota_dibatasi(): void
    {
        $component = Livewire::test(Form::class);

        for ($i = 0; $i < KegiatanForm::MAKS_PANITIA; $i++) {
            $component->call('tambahPanitia');
        }

        $component->call('tambahPanitia')
            ->assertDispatched('notify', type: 'error');

        $this->assertCount(KegiatanForm::MAKS_PANITIA, $component->get('form.panitia'));
    }
}
