<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\SimpanKonteks;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SimpanKonteksTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_konteks_yang_disimpan_dan_dirapikan(): void
    {
        $repo = app(KegiatanRepository::class);
        $sebelum = $repo->temukan(1);

        $hasil = SimpanKonteks::simpan(1, "  Peserta   54 guru.\n\n\n\nOutput: dokumen KTSP.  ");

        $this->assertSame(['berubah' => true, 'konteks' => "Peserta 54 guru.\n\nOutput: dokumen KTSP.", 'diperbarui_pada' => null], $hasil);
        $sesudah = $repo->temukan(1);
        $this->assertSame($hasil['konteks'], $sesudah['konteks']);
        $this->assertSame(Arr::except($sebelum, ['konteks', 'diperbarui_pada']), Arr::except($sesudah, ['konteks', 'diperbarui_pada']));

        // Teks sama (setelah dirapikan) tidak ditulis ulang.
        $versi = $sesudah['diperbarui_pada'];
        $this->assertFalse(SimpanKonteks::simpan(1, 'Peserta 54 guru.  '."\n\nOutput: dokumen KTSP.")['berubah']);
        $this->assertSame($versi, $repo->temukan(1)['diperbarui_pada']);
    }

    public function test_konteks_terlalu_panjang_terkunci_atau_kegiatan_tidak_ada_ditolak(): void
    {
        try {
            SimpanKonteks::simpan(1, str_repeat('a', 3001));
            $this->fail('Konteks lebih dari 3.000 karakter seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('konteks', $e->errors());
        }

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(2), 2);
        try {
            SimpanKonteks::simpan(2, 'Catatan baru.');
            $this->fail('Kegiatan terkunci seharusnya ditolak.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Kegiatan berstatus Diperiksa sehingga isiannya terkunci.', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        SimpanKonteks::simpan(999, 'Catatan.');
    }

    public function test_simpan_otomatis_data_kegiatan_lewat_database(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));

        Livewire::test(Form::class, ['kegiatan' => $id])
            ->set('form.tempat', 'Belum disimpan')
            ->set('form.konteks', 'Narasumber pengawas madrasah;  output dokumen KTSP.')
            ->assertDispatched('konteks-tersimpan');

        $k = app(KegiatanRepository::class)->temukan($id);
        $this->assertSame(['Narasumber pengawas madrasah; output dokumen KTSP.', 'Aula Madrasah'], [$k['konteks'], $k['tempat']]);
        $this->assertNotNull($k['konteks_diperbarui_pada']);
    }
}
