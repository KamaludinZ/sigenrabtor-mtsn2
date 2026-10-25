<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Livewire\Kegiatan\Form;
use Livewire\Livewire;
use Tests\TestCase;

class ValidasiPelaksanaanTest extends TestCase
{
    private function kegiatanTanpaPelaksanaan(): int
    {
        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan Tanpa Pelaksanaan',
            'nama_kegiatan_cover' => 'KEGIATAN TANPA PELAKSANAAN',
            'leading' => '1.6',
            'kode_sk' => 'SK.12',
            'kode_iksk' => 'IKSK 12.1',
        ]);
    }

    public function test_draf_boleh_kosong_tetapi_pengajuan_mewajibkan_isian_pelaksanaan(): void
    {
        Livewire::test(Form::class, ['kegiatan' => $this->kegiatanTanpaPelaksanaan()])
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->call('ubahStatus', 'ajukan')
            ->assertHasErrors(['form.tanggal_mulai', 'form.lama_hari', 'form.bulan_pelaksanaan', 'form.tempat', 'form.peserta_jumlah', 'form.peserta_jenis'])
            ->assertHasNoErrors('form.kode_sk')
            ->assertSee('Tempat pelaksanaan wajib diisi sebelum kegiatan diajukan — tercetak di bagian 5 KAK.')
            ->assertDispatched('notify', message: 'Lengkapi 6 isian yang ditandai merah sebelum mengajukan.', type: 'error')
            ->assertSet('status', 'draf')
            // Konfirmasi ditutup saat dikirim agar tombol "Ajukan pemeriksaan" kembali setelah ditolak.
            ->assertSeeHtml("wire:click=\"ubahStatus('ajukan')\" x-on:click=\"yakin = false\"");
    }

    public function test_pengajuan_berhasil_setelah_pelaksanaan_lengkap(): void
    {
        Livewire::test(Form::class, ['kegiatan' => $this->kegiatanTanpaPelaksanaan()])
            ->set('form.tanggal_mulai', '2026-07-14')
            ->set('form.lama_hari', '2')
            ->set('form.tempat', 'Aula Madrasah')
            ->set('form.peserta_jumlah', '40')
            ->set('form.peserta_jenis', 'Guru')
            ->call('ubahStatus', 'ajukan')
            ->assertHasNoErrors()
            ->assertSet('status', 'diperiksa');
    }

    public function test_angka_bulat_dengan_pesan_yang_jelas(): void
    {
        Livewire::test(Form::class)
            ->set('form.lama_hari', 'abc')
            ->assertHasErrors(['form.lama_hari' => 'integer'])
            ->assertSee('Lama kegiatan harus angka bulat (jumlah hari), mis. 3.')
            ->set('form.lama_hari', '0')
            ->assertSee('Lama kegiatan minimal 1 hari.')
            ->set('form.lama_hari', '400')
            ->assertSee('Lama kegiatan maksimal 366 hari.')
            ->set('form.peserta_jumlah', '12.5')
            ->assertSee('Jumlah peserta harus angka bulat tanpa titik atau koma, mis. 56.')
            ->set('form.peserta_jumlah', '-3')
            ->assertSee('Jumlah peserta minimal 1 orang.')
            ->set('form.peserta_jumlah', '100001')
            ->assertSee('Jumlah peserta maksimal 100.000 orang.')
            ->set('form.peserta_jumlah', '56')
            ->assertHasNoErrors('form.peserta_jumlah');
    }

    public function test_bulan_pelaksanaan_di_luar_rentang_ditolak(): void
    {
        // Tombol bulan hanya mengirim 1–12; nilai lain diabaikan, dan data yang diakali tertolak saat disimpan.
        Livewire::test(Form::class)
            ->call('toggleBulan', 13)
            ->assertSet('form.bulan_pelaksanaan', [])
            ->set('form.nama_kegiatan', 'Kegiatan Bulan')
            ->set('form.bulan_pelaksanaan', [7, 13])
            ->call('saveDraft')
            ->assertHasErrors('form.bulan_pelaksanaan.1')
            ->assertSee('Bulan pelaksanaan harus antara Januari dan Desember.');
    }

    public function test_angka_tak_wajar_diberi_peringatan_tanpa_menghalangi(): void
    {
        Livewire::test(Form::class)
            ->set('form.lama_hari', (string) (KegiatanForm::BATAS_WAJAR['lama_hari'] + 14))
            ->set('form.peserta_jumlah', '2500')
            ->assertHasNoErrors(['form.lama_hari', 'form.peserta_jumlah'])
            ->assertSee('Lama kegiatan 45 hari (lebih dari sebulan) — pastikan bukan salah ketik.')
            ->assertSee('Jumlah peserta 2.500 orang — pastikan bukan salah ketik.')
            ->set('form.lama_hari', '3')
            ->assertDontSee('lebih dari sebulan');
    }

    public function test_penanda_wajib_dan_filter_digit_tampil(): void
    {
        Livewire::test(Form::class)
            ->assertSee('wajib sebelum kegiatan diajukan; draf boleh disimpan dulu.')
            ->assertSeeHtml('id="lama_hari" wire:model.live.debounce.400ms="form.lama_hari" min="1" max="366" step="1"')
            ->assertSeeHtml('inputmode="numeric" x-angka-bulat placeholder="1"')
            ->assertSeeHtml('inputmode="numeric" x-angka-bulat placeholder="0"');
    }
}
