<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Kegiatan\Form;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class StatusKegiatanTest extends TestCase
{
    private function statusTersimpan(int $id = 1): string
    {
        return app(KegiatanRepository::class)->temukan($id)['status'];
    }

    private function sebagaiAdmin(): void
    {
        session(['stub_role' => 'admin']);
    }

    public function test_enum_menentukan_status_yang_boleh_diedit(): void
    {
        $this->assertTrue(StatusKegiatan::Draf->dapatDiedit());
        $this->assertTrue(StatusKegiatan::Dikembalikan->dapatDiedit());
        $this->assertFalse(StatusKegiatan::Diperiksa->dapatDiedit());
        $this->assertFalse(StatusKegiatan::Disetujui->dapatDiedit());
        $this->assertFalse(StatusKegiatan::Final->dapatDiedit());
    }

    public function test_aksi_dibatasi_per_status_dan_peran(): void
    {
        $this->assertSame(['ajukan'], array_keys(StatusKegiatan::Draf->aksiUntuk('leading')));
        $this->assertSame(['tarik'], array_keys(StatusKegiatan::Diperiksa->aksiUntuk('leading')));
        $this->assertSame(['setujui', 'kembalikan'], array_keys(StatusKegiatan::Diperiksa->aksiUntuk('admin')));
        $this->assertSame([], array_keys(StatusKegiatan::Final->aksiUntuk('leading')));
        $this->assertSame(['buka_kunci'], array_keys(StatusKegiatan::Final->aksiUntuk('admin')));
    }

    public function test_kegiatan_baru_belum_bisa_diajukan_dan_mencatat_riwayat_saat_disimpan(): void
    {
        Livewire::test(Form::class)
            ->assertSee('Simpan draf terlebih dahulu untuk mengajukan pemeriksaan.')
            ->assertDontSee('Ajukan pemeriksaan')
            ->set('form.nama_kegiatan', 'Kegiatan MGMP Matematika')
            ->call('saveDraft');

        $riwayat = app(KegiatanRepository::class)->temukan($this->idKegiatanBaru())['riwayat_status'];
        $this->assertSame('dibuat', $riwayat[0]['aksi']);
        $this->assertSame('Muslimin, M.Pd.', $riwayat[0]['oleh']);
    }

    public function test_alur_lengkap_ajukan_setujui_final_lalu_buka_kunci(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('Ajukan pemeriksaan')
            ->call('ubahStatus', 'ajukan')
            ->assertSet('status', 'diperiksa')
            ->assertSee('Isian terkunci — status Diperiksa')
            ->assertSee('Tarik pengajuan')
            ->assertDontSee('Simpan Draf');
        $this->assertSame('diperiksa', $this->statusTersimpan());

        $this->sebagaiAdmin();
        $form = Livewire::test(Form::class, ['kegiatan' => 1])
            ->assertSee('Setujui')
            ->call('ubahStatus', 'setujui')
            ->assertSet('status', 'disetujui')
            // Tandai final menunggu arsip final semua sumber dana (contoh 1: Komite).
            ->call('ubahStatus', 'finalkan')
            ->assertSet('status', 'disetujui')
            ->assertDispatched('notify', message: 'Unggah arsip final bertanda tangan untuk RAB Komite sebelum menandai final.', type: 'error');
        app(ArsipFinalRepository::class)->simpan(1, 'Komite', UploadedFile::fake()->createWithContent('arsip.pdf', '%PDF-1.4'), ['id' => 2, 'name' => 'Koordinator PUSKOM']);
        $form->call('arsipFinalBerubah')
            ->call('ubahStatus', 'finalkan')
            ->assertSet('status', 'final')
            ->assertSee('Buka kunci')
            // Buka kunci wajib beralasan; alasannya tercatat di riwayat.
            ->call('ubahStatus', 'buka_kunci')
            ->assertHasErrors(['catatanStatus' => 'required'])
            ->assertSet('status', 'final')
            ->set('catatanStatus', '  Nama narasumber salah ketik.  ')
            ->call('ubahStatus', 'buka_kunci')
            ->assertSet('status', 'dikembalikan')
            ->assertSet('catatanStatus', '')
            ->assertDontSee('Isian terkunci');

        $riwayat = array_column(app(KegiatanRepository::class)->temukan(1)['riwayat_status'], 'aksi');
        $this->assertSame(['ajukan', 'setujui', 'finalkan', 'buka_kunci'], $riwayat);
        $this->assertSame('Nama narasumber salah ketik.', end(app(KegiatanRepository::class)->temukan(1)['riwayat_status'])['catatan']);
    }

    public function test_leading_tidak_bisa_menyetujui_sendiri(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('ubahStatus', 'ajukan')
            ->call('ubahStatus', 'setujui')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('status', 'diperiksa');
    }

    public function test_pengajuan_mewajibkan_isian_valid(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Abc', 'status' => 'draf']);

        Livewire::test(Form::class, ['kegiatan' => $id])
            ->call('ubahStatus', 'ajukan')
            ->assertHasErrors('form.nama_kegiatan')
            ->assertSet('status', 'draf');
    }

    public function test_isian_terkunci_ditolak_di_server_walau_html_diakali(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('ubahStatus', 'ajukan')
            ->set('form.nama_kegiatan', 'Diubah diam-diam')
            ->assertForbidden();

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('tambahPanitia')
            ->assertForbidden();

        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('saveDraft')
            ->assertForbidden();

        $tersimpan = app(KegiatanRepository::class)->temukan(1);
        $this->assertSame('Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran', $tersimpan['nama_kegiatan']);
        $this->assertCount(3, $tersimpan['panitia']);
    }

    public function test_status_tidak_bisa_diubah_langsung_dari_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Form::class, ['kegiatan' => 1])->set('status', 'final');
    }

    public function test_tarik_pengajuan_membuka_kembali_isian(): void
    {
        Livewire::test(Form::class, ['kegiatan' => 1])
            ->call('ubahStatus', 'ajukan')
            ->call('ubahStatus', 'tarik')
            ->assertSet('status', 'draf')
            ->set('form.sasaran', 'dokumen kurikulum yang diperbarui.')
            ->assertHasNoErrors();
    }

    public function test_status_tak_dikenal_di_data_dianggap_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Data Rusak', 'status' => 'entah']);

        Livewire::test(Form::class, ['kegiatan' => $id])->assertSet('status', 'draf');
    }
}
