<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Pemeriksaan\Periksa;
use App\Support\Kegiatan\PerbaikanKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

class PerbaikanDataTest extends TestCase
{
    /** Salinan contoh kegiatan 2 yang diajukan lalu dikembalikan admin lewat halaman Pemeriksaan. */
    private function dikembalikan(string $catatan = 'Tempat pelaksanaan belum sesuai.'): int
    {
        $data = app(KegiatanRepository::class)->temukan(2);
        unset($data['id']);
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Supervisi Perbaikan', 'status' => 'diperiksa', 'riwayat_status' => [
            ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-03-01T09:00:00+07:00'],
        ]] + $data);

        session(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => $id])->set('catatan', $catatan)->call('minta', 'kembalikan')->call('putuskan', 'kembalikan');
        session(['stub_role' => 'leading']);

        return $id;
    }

    public function test_leading_melihat_catatan_dan_bagian_yang_sudah_diperbaiki(): void
    {
        $id = $this->dikembalikan();
        $this->assertIsArray(end(app(KegiatanRepository::class)->temukan($id)['riwayat_status'])['sidik']);

        // Simpan tanpa perubahan tidak menandai bagian mana pun.
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="banner-perbaikan"')
            ->assertSee('Dikembalikan untuk perbaikan')
            ->assertSee('Tempat pelaksanaan belum sesuai.')
            ->assertSee('Belum ada perubahan tersimpan sejak dikembalikan.')
            ->assertSee('Ajukan ulang')
            ->call('saveDraft')
            ->assertSee('Belum ada perubahan tersimpan sejak dikembalikan.')
            ->set('form.tempat', 'Laboratorium Komputer')
            ->call('saveDraft')
            ->assertDontSee('Belum ada perubahan tersimpan sejak dikembalikan.')
            ->assertSeeInOrder(['Sudah diubah sejak dikembalikan:', 'Pelaksanaan']);

        app(RabRepository::class)->tambahItem($id, 'DIPA', ['uraian' => 'Konsumsi rapat', 'volume' => 10, 'satuan' => 'kotak', 'harga_satuan' => 25_000]);
        $this->assertSame(['Pelaksanaan', 'RAB DIPA'], PerbaikanKegiatan::dari(app(KegiatanRepository::class)->temukan($id))->diubah);

        // Ajukan ulang: banner hilang, pemeriksa melihat pengajuan ulang beserta bagian yang diubah.
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->call('ubahStatus', 'ajukan')
            ->assertSet('status', 'diperiksa')
            ->assertDontSeeHtml('data-testid="banner-perbaikan"');

        session(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="pengajuan-ulang"')
            ->assertSee('Tempat pelaksanaan belum sesuai.')
            ->assertSeeInOrder(['Diubah:', 'Pelaksanaan', 'RAB DIPA']);
    }

    public function test_pengajuan_ulang_tanpa_perubahan_ditandai_untuk_pemeriksa(): void
    {
        $id = $this->dikembalikan();
        Livewire::test(Form::class, ['kegiatan' => $id])->call('ubahStatus', 'ajukan')->assertSet('status', 'diperiksa');

        session(['stub_role' => 'admin']);
        Livewire::test(Periksa::class, ['kegiatan' => $id])
            ->assertSee('Pengajuan ulang')
            ->assertSee('Tidak ada data yang diubah sejak dikembalikan.');

        // Pengajuan pertama tidak menampilkan kotak pengajuan ulang.
        Livewire::test(Periksa::class, ['kegiatan' => 1])->assertDontSeeHtml('data-testid="pengajuan-ulang"');
    }

    public function test_buka_kunci_juga_membuka_perbaikan(): void
    {
        $data = app(KegiatanRepository::class)->temukan(1);
        unset($data['id']);
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Workshop Final', 'status' => 'final', 'riwayat_status' => []] + $data);

        session(['stub_role' => 'admin']);
        Livewire::test(Form::class, ['kegiatan' => $id])->set('catatanStatus', 'Tanggal dokumen salah.')->call('ubahStatus', 'buka_kunci')->assertSet('status', 'dikembalikan');

        session(['stub_role' => 'leading']);
        Livewire::test(Form::class, ['kegiatan' => $id])
            ->assertSee('Kunci dibuka untuk perbaikan')
            ->assertSee('Tanggal dokumen salah.')
            ->assertSee('Belum ada perubahan tersimpan sejak dikembalikan.');
    }

    public function test_aturan_perbaikan(): void
    {
        $this->assertNull(PerbaikanKegiatan::dari(['riwayat_status' => [['aksi' => 'ajukan', 'oleh' => 'A', 'pada' => '2026-01-01']]]));

        // Entri lama tanpa sidik: perubahan tidak diketahui.
        $lama = PerbaikanKegiatan::dari(['riwayat_status' => [['aksi' => 'kembalikan', 'oleh' => 'Admin', 'pada' => '2026-01-02', 'catatan' => 'Perbaiki.']]]);
        $this->assertSame([null, 'Perbaiki.', false], [$lama->diubah, $lama->catatan, $lama->diajukanUlang]);

        $sidik = PerbaikanKegiatan::sidik(['tempat' => 'Aula', 'peserta_jumlah' => 40, 'tujuan' => '']);
        $this->assertSame($sidik, PerbaikanKegiatan::sidik(['tempat' => ' Aula ', 'peserta_jumlah' => '40', 'tujuan' => null]), 'Beda tipe/spasi tersimpan bukan perubahan.');
        $this->assertSame(['Kode & klasifikasi', 'RAB DIPA', 'RAB Komite', 'Matrik & jadwal'], PerbaikanKegiatan::bandingkan(
            ['matrik' => 'a', 'rab:Komite' => 'b', 'kode' => 'c', 'identitas' => 'd'],
            ['matrik' => 'x', 'rab:DIPA' => 'y', 'kode' => 'z', 'identitas' => 'd'],
        ));
    }
}
