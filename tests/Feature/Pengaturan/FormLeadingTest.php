<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Livewire\Pengaturan\LeadingUser;
use App\Support\MasterKode;
use App\Support\Pengaturan\AturanLeading;
use App\Support\Stub\StubLeadingRepository;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class FormLeadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function riwayatTerakhir(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
    }

    public function test_aturan_isian_leading(): void
    {
        $leading = app(LeadingRepository::class)->semua();
        $isian = fn (array $ubah) => $ubah + ['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => ''];

        $this->assertSame('1.10', AturanLeading::normalkanKode(' 01 . 10 '));
        $this->assertNull(AturanLeading::normalkanKode('1.1.1'));
        $this->assertNull(AturanLeading::normalkanKode('A.1'));
        $this->assertSame([], AturanLeading::galat($isian([]), $leading));
        $this->assertSame(['kode' => 'Kode leading wajib diisi.', 'nama_unit' => 'Nama unit wajib diisi.', 'jabatan_penanda_tangan' => 'Jabatan penanda tangan wajib diisi — tercetak di kolom "Pembuat".'],
            AturanLeading::galat(['kode' => '', 'nama_unit' => '', 'jabatan_penanda_tangan' => '', 'urutan' => ''], $leading));
        $this->assertSame(['kode' => 'Kode berformat angka.angka, mis. 1.1 atau 1.10.'], AturanLeading::galat($isian(['kode' => '1-10']), $leading));
        $this->assertSame(['kode' => 'Kode 1.6 sudah dipakai leading lain.'], AturanLeading::galat($isian(['kode' => '1.06']), $leading));
        $this->assertSame(['nama_unit' => 'Nama unit sudah dipakai leading 1.7.'], AturanLeading::galat($isian(['nama_unit' => 'mahad']), $leading));
        $this->assertSame(['urutan' => 'Urutan berupa bilangan bulat 0–999.'], AturanLeading::galat($isian(['urutan' => '-1']), $leading));
        $this->assertSame([], AturanLeading::galat($isian(['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu']), $leading, '1.6'), 'Leading yang diubah tidak dibandingkan dengan dirinya.');
    }

    public function test_tambah_leading_baru(): void
    {
        Livewire::test(LeadingUser::class)
            ->call('tambahLeading')
            ->assertSet('suntingLeading', 'baru')
            ->assertSet('isianLeading.urutan', '10')
            ->assertSeeHtml('data-testid="formulir-leading"')
            ->set('isianLeading.kode', '1.6')
            ->call('simpanLeading')
            ->assertHasErrors(['isianLeading.kode', 'isianLeading.nama_unit', 'isianLeading.jabatan_penanda_tangan'])
            ->assertSee('Kode 1.6 sudah dipakai leading lain.')
            ->set('isianLeading', ['kode' => ' 1 . 10 ', 'nama_unit' => '  Literasi   Madrasah ', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => '10'])
            ->call('simpanLeading')
            ->assertHasNoErrors()
            ->assertSet('suntingLeading', null)
            ->assertDispatched('notify', message: 'Leading 1.10 Literasi Madrasah ditambahkan.', type: 'success')
            ->assertSeeHtml('data-leading="1.10"')
            // Kode dibandingkan ketat: "1.10" bukan "1.1".
            ->call('ubahLeading', '1.10')
            ->assertSet('isianLeading.nama_unit', 'Literasi Madrasah');

        $this->assertSame(['kode' => '1.10', 'nama_unit' => 'Literasi Madrasah', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 10, 'is_active' => true], collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.10'));
        $this->assertContains('1.10', array_column(MasterKode::leadings(), 'kode'), 'Langsung bisa dipilih di Data Kegiatan.');
        $this->assertSame(['tambah_leading', 'Leading 1.10 Literasi Madrasah — jabatan Koordinator Literasi'], [$this->riwayatTerakhir()['aksi'], $this->riwayatTerakhir()['keterangan']]);
    }

    public function test_ubah_leading_dan_kode_terpakai_terkunci(): void
    {
        $komponen = Livewire::test(LeadingUser::class)
            ->call('ubahLeading', '1.6')
            ->assertSet('isianLeading', ['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'jabatan_penanda_tangan' => 'Ketua Tim Penjamin Mutu', 'urutan' => '6'])
            ->assertSeeHtml('readonly')
            ->assertSee('Terkunci: dipakai 4 kegiatan/akun.')
            ->set('isianLeading.kode', '1.60')
            ->set('isianLeading.jabatan_penanda_tangan', 'Ketua Tim Penjamin Mutu Madrasah')
            ->call('simpanLeading')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Leading 1.6 Tim Penjamin Mutu diperbarui.', type: 'success');

        $this->assertSame('Ketua Tim Penjamin Mutu Madrasah', collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.6')['jabatan_penanda_tangan']);
        $this->assertNull(collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', '1.60'), 'Kode terpakai tidak berganti.');
        $this->assertSame('Leading 1.6 — Jabatan: Ketua Tim Penjamin Mutu → Ketua Tim Penjamin Mutu Madrasah', $this->riwayatTerakhir()['keterangan']);

        // Kode yang belum dipakai bisa diganti.
        $komponen->call('ubahLeading', '1.8')
            ->assertSet('suntingLeading', '1.8')
            ->set('isianLeading.kode', '1.11')
            ->call('simpanLeading')
            ->assertHasNoErrors();
        $this->assertSame(['1.11'], array_values(array_intersect(['1.8', '1.11'], array_column(app(LeadingRepository::class)->semua(), 'kode'))));

        $jumlah = count(app(RiwayatPengaturanRepository::class)->semua());
        $komponen->call('ubahLeading', '1.7')->call('simpanLeading')
            ->assertDispatched('notify', message: 'Tidak ada yang berubah.', type: 'info')
            ->assertSet('suntingLeading', null);
        $this->assertCount($jumlah, app(RiwayatPengaturanRepository::class)->semua());

        $komponen->call('ubahLeading', 'tidak-ada')->assertSet('suntingLeading', null);
        $komponen->call('ubahLeading', '1.7')->call('batalLeading')->assertSet('suntingLeading', null)->assertSet('isianLeading.kode', '');
        $komponen->call('ubahLeading', '1.7')->set('tab', 'akun')->assertSet('suntingLeading', null);
    }

    public function test_gagal_simpan_tidak_membuang_isian(): void
    {
        $this->app->instance(LeadingRepository::class, new class extends StubLeadingRepository
        {
            public function simpan(array $data, ?string $kodeLama = null): void
            {
                throw new RuntimeException('Disk penuh.');
            }
        });
        $jumlah = count(app(RiwayatPengaturanRepository::class)->semua());

        Livewire::test(LeadingUser::class)
            ->call('tambahLeading')
            ->set('isianLeading', ['kode' => '1.10', 'nama_unit' => 'Literasi', 'jabatan_penanda_tangan' => 'Koordinator', 'urutan' => ''])
            ->call('simpanLeading')
            ->assertDispatched('notify', message: 'Leading gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error')
            ->assertSet('suntingLeading', 'baru')
            ->assertSet('isianLeading.nama_unit', 'Literasi');
        $this->assertCount($jumlah, app(RiwayatPengaturanRepository::class)->semua());
    }
}
