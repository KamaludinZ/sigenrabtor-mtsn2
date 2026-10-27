<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\JadwalKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

class KalimatAiTest extends TestCase
{
    public function test_halaman_enam_bagian_dengan_bahan_ai_dan_tab_ruang_kerja(): void
    {
        $this->get(route('kegiatan.edit', ['kegiatan' => 1]))->assertSeeHtml('href="'.route('kegiatan.kalimat', ['kegiatan' => 1]).'"');

        $this->get(route('kegiatan.kalimat', ['kegiatan' => 1]))
            ->assertOk()
            ->assertSeeLivewire(KalimatAi::class)
            ->assertSee('<title>Kalimat KAK · ', false);

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtmlInOrder(array_map(fn ($k) => 'data-bagian-ai="'.$k.'"', array_keys(BagianAi::BAGIAN)))
            ->assertSeeHtml('data-testid="buat-ai-tujuan"')
            ->assertSee('Tercetak di Bagian 3A · Tujuan.')
            ->assertSee('AI menyarankan 2–3 versi: 1 paragraf tujuan yang spesifik.')
            ->assertSeeHtml('data-testid="pratinjau-tujuan"')
            ->assertSeeHtml('data-testid="batas-aman-ai"')
            ->assertSee('Penyedia AI belum dipilih admin')
            ->assertSee('Ubah jadwal &amp; usulan AI di Matrik &amp; Jadwal', false)
            ->assertSee('Lihat tercetak di KAK/TOR');

        $this->assertSame(['Nama kegiatan', 'Kode RKTM & sub kegiatan', 'IKSK', 'Catatan konteks'], array_column(BagianAi::bahan('latar_belakang', ['nama_kegiatan' => 'Rapat']), 'label'));
        $bahan = BagianAi::bahan('pelaksanaan', ['peserta_jumlah' => 40, 'peserta_jenis' => 'guru', 'lama_hari' => 2, 'tempat' => '', 'konteks' => 'Narasumber: pengawas madrasah.']);
        $this->assertSame([['Peserta', '40 orang guru'], ['Lama', '2 hari'], ['Tempat', null], ['Catatan konteks', 'narasumber']], array_map(fn ($b) => [$b['label'], $b['nilai']], $bahan));
        $this->assertFalse(BagianAi::terisi('tujuan', ['tujuan' => '  ']));
        $this->assertTrue(BagianAi::terisi('jadwal', [], new JadwalKegiatan([['uraian' => 'Rapat', 'bulan' => [2]]])));
    }

    public function test_sunting_simpan_dan_nama_cover(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja', 'nama_kegiatan_cover' => 'RAPAT KERJA', 'leading' => '1.6', 'riwayat_kode' => [['oleh' => 'X']], 'tempat' => 'Aula']);

        Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->set('form.tujuan', 'menyusun   program kerja tahunan.')
            ->assertSet('adaPerubahan', true)
            ->assertSet('form.tujuan', 'menyusun program kerja tahunan.')
            ->set('form.nama_kegiatan_cover', 'rapat kerja tahunan')
            ->assertSet('form.nama_kegiatan_cover', 'RAPAT KERJA TAHUNAN')
            ->assertSet('form.cover_manual', true)
            ->assertSee('Samakan dengan nama kegiatan')
            ->call('simpan')
            ->assertDispatched('notify', message: 'Kalimat KAK tersimpan.', type: 'success')
            ->assertSet('adaPerubahan', false)
            ->call('resetNamaCover')
            ->assertSet('form.nama_kegiatan_cover', 'RAPAT KERJA')
            ->assertSet('adaPerubahan', true)
            ->call('batalkanPerubahan')
            ->assertSet('form.nama_kegiatan_cover', 'RAPAT KERJA TAHUNAN')
            ->call('simpan')
            ->assertDispatched('notify', message: 'Tidak ada perubahan kalimat untuk disimpan.', type: 'info');

        $tersimpan = $repo->temukan($id);
        $this->assertSame(['menyusun program kerja tahunan.', 'RAPAT KERJA TAHUNAN', true], [$tersimpan['tujuan'], $tersimpan['nama_kegiatan_cover'], $tersimpan['cover_manual']]);
        // Isian lain tidak tersentuh.
        $this->assertSame([[['oleh' => 'X']], 'Aula', 'Rapat Kerja'], [$tersimpan['riwayat_kode'], $tersimpan['tempat'], $tersimpan['nama_kegiatan']]);

        // Hanya kolom halaman ini yang boleh diubah; aturan kalimat utuh tetap berlaku.
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])->set('form.nama_kegiatan', 'Lain')->assertForbidden();
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->set('form.sasaran', 'dokumen yang dibutuhkan oleh')
            ->assertHasErrors('form.sasaran')
            ->call('simpan')
            ->assertDispatched('notify', type: 'error');
    }

    public function test_bentrok_dengan_perubahan_di_tempat_lain_dan_status_terkunci(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja', 'nama_kegiatan_cover' => 'RAPAT KERJA', 'leading' => '1.6', 'tujuan' => 'menyusun program.']);

        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => $id])->set('form.tujuan', 'menyusun program kerja.');
        $repo->simpan(['tujuan' => 'menyusun rencana.'] + $repo->temukan($id), $id);

        $halaman->call('simpan')
            ->assertSet('bentrok', ['tujuan' => ['menyusun program.', 'menyusun rencana.']])
            ->assertSeeHtml('data-testid="banner-bentrok"')
            ->call('simpan', true)
            ->assertSet('bentrok', null);
        $this->assertSame('menyusun program kerja.', $repo->temukan($id)['tujuan']);

        $repo->simpan(['status' => 'final'] + $repo->temukan($id), $id);
        Livewire::test(KalimatAi::class, ['kegiatan' => $id])
            ->assertDontSeeHtml('data-testid="simpan-kalimat"')
            ->set('form.tujuan', 'x')
            ->assertForbidden();
    }
}
