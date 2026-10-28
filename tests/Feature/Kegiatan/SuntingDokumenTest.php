<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\TeksKhusus;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SuntingDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function kegiatanBaru(array $data = []): int
    {
        return app(KegiatanRepository::class)->simpan($data + ['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026, 'tujuan' => 'menyusun rencana kerja tahunan.', 'tempat' => 'Aula Madrasah']);
    }

    /** @return list<string> Kunci sunting blok (dan butir) satu kegiatan, urut dokumen. */
    private function kunciBlok(array $kegiatan): array
    {
        return collect(SusunanKak::dari($kegiatan)->bagian)->flatMap(fn (array $b) => collect($b['isi'])->flatMap(fn (array $blok) => [...(isset($blok['sunting']) ? [$blok['sunting']] : []), ...($blok['sunting_butir'] ?? [])]))->all();
    }

    public function test_bidang_blok_bersunting_dan_kiriman(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanBaru());

        // Latar belakang khusus kosong → tidak ada bloknya, ditawarkan "Tambah"; sasaran kosong tetap bisa disunting dari penandanya.
        $this->assertSame(['tujuan', 'sasaran', 'persiapan', 'analisis_hasil', 'tempat', 'pengarah'], $this->kunciBlok($kegiatan));
        $kak = SusunanKak::dari($kegiatan);
        $this->assertSame(['latar_belakang'], SuntingDokumen::tambahan($kak->bagian[0]));
        $this->assertSame(['uraian_pelaksanaan'], SuntingDokumen::tambahan($kak->bagian[3]), 'Tanpa jadwal, kalimat 4b belum tersusun.');
        $this->assertSame([], SuntingDokumen::tambahan($kak->bagian[2]));
        $this->assertSame('kosong', collect($kak->bagian[2]['isi'])->firstWhere('sunting', 'sasaran')['jenis']);
        $this->assertSame(['persiapan', 'uraian_pelaksanaan', 'analisis_hasil'], SuntingDokumen::untukBagian(4));

        $this->assertSame(['Tujuan', 'uraian', 2000], [SuntingDokumen::label('tujuan'), SuntingDokumen::jenis('tujuan'), SuntingDokumen::maks('tujuan')]);
        $this->assertSame(['Tempat pelaksanaan', 'teks'], [SuntingDokumen::label('tempat'), SuntingDokumen::jenis('tempat')]);
        $this->assertSame(['Tahapan persiapan (4a)', 'daftar', 500], [SuntingDokumen::label('persiapan'), SuntingDokumen::jenis('persiapan'), SuntingDokumen::maks('persiapan')]);
        $this->assertSame('uraian', SuntingDokumen::jenis('pengarah'));

        $this->assertSame('menyusun rencana kerja tahunan.', SuntingDokumen::teks($kegiatan, 'tujuan'));
        $this->assertSame('', SuntingDokumen::teks($kegiatan, 'sasaran'));
        $this->assertSame(implode("\n", TeksKhusus::bakuMentah('persiapan')), SuntingDokumen::teks($kegiatan, 'persiapan'));
        $this->assertSame(['tujuan' => 'baru.'], SuntingDokumen::kiriman($kegiatan, 'tujuan', '  baru. '));
        $this->assertSame(['tujuan' => null], SuntingDokumen::kiriman($kegiatan, 'tujuan', ' '));
        $teks = ['pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.'];
        $this->assertSame(['teks_khusus' => $teks + ['analisis_hasil' => 'Analisis singkat hasil kegiatan.']], SuntingDokumen::kiriman(['teks_khusus' => $teks] + $kegiatan, 'analisis_hasil', 'Analisis singkat hasil kegiatan.'));
        $this->assertSame(['teks_khusus' => []], SuntingDokumen::kiriman(['teks_khusus' => $teks] + $kegiatan, 'pengarah', ''), 'Kosong = kembali ke teks baku.');
    }

    public function test_simpan_ke_data_kegiatan(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $hasil = SuntingDokumen::simpan($id, $repo->temukan($id), 'sasaran', 'rencana kerja yang disepakati warga madrasah.', self::LEADING, null);
        $this->assertSame(['sasaran'], $hasil['diubah']);
        $this->assertSame('Sasaran diperbarui — tersimpan di data kegiatan.', $hasil['pesan']);
        $this->assertSame('rencana kerja yang disepakati warga madrasah.', $repo->temukan($id)['sasaran']);

        $hasil = SuntingDokumen::simpan($id, $repo->temukan($id), 'pengarah', 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah bersama Komite.', self::LEADING, null);
        $this->assertSame('Kalimat pengarah (6b) diperbarui — tersimpan di data kegiatan.', $hasil['pesan']);
        $this->assertStringContainsString('Pengarah kegiatan Rapat Kerja Madrasah adalah Kepala Madrasah bersama Komite.', json_encode(SusunanKak::dari($repo->temukan($id))->bagian[5], JSON_UNESCAPED_UNICODE));
        $this->assertSame('Kalimat pengarah (6b) kembali memakai teks baku.', SuntingDokumen::simpan($id, $repo->temukan($id), 'pengarah', '', self::LEADING, null)['pesan']);
        $this->assertSame('Sasaran tidak berubah.', SuntingDokumen::simpan($id, $repo->temukan($id), 'sasaran', 'rencana kerja yang disepakati warga madrasah.', self::LEADING, null)['pesan']);

        foreach ([['tujuan', 'menyusun rencana kerja yang'], ['kode_sk', 'SK.1']] as [$kunci, $teks]) {
            try {
                SuntingDokumen::simpan($id, $repo->temukan($id), $kunci, $teks, self::LEADING, null);
                $this->fail("{$kunci} harus ditolak.");
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_mode_sunting_di_pratinjau(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $this->kegiatanBaru();

        $halaman = Livewire::test(PratinjauDokumen::class, ['kegiatan' => $id])
            ->assertSet('mode', 'baca')
            ->assertDontSeeHtml('data-sunting=')
            ->assertDontSeeHtml('data-testid="petunjuk-sunting"');
        $halaman->set('mode', 'sunting')
            ->assertSeeHtml('data-testid="petunjuk-sunting"')
            ->assertSeeHtml('data-sunting="tujuan"')
            ->assertSeeHtml('data-sunting="analisis_hasil"')
            ->assertSeeHtml('data-testid="tambah-latar_belakang"')
            ->call('bukaSunting', 'tujuan')
            ->assertSet('suntingTerbuka', 'tujuan')
            ->assertSet('teksSunting', 'menyusun rencana kerja tahunan.')
            ->assertSeeHtml('data-testid="penyunting-tujuan"')
            // Kalimat terpotong ditolak dengan aturan Data Kegiatan; penyunting tetap terbuka.
            ->set('teksSunting', 'menyusun rencana kerja yang')
            ->call('simpanSunting')
            ->assertHasErrors('teksSunting')
            ->assertSet('suntingTerbuka', 'tujuan')
            ->set('teksSunting', 'menyusun rencana kerja tahunan yang terukur.')
            ->call('simpanSunting')
            ->assertHasNoErrors()
            ->assertSet('suntingTerbuka', null)
            ->assertDispatched('notify', message: 'Tujuan diperbarui — tersimpan di data kegiatan.', type: 'success')
            ->assertSee(KalimatKak::tujuan('Rapat Kerja Madrasah', 'menyusun rencana kerja tahunan yang terukur.'));
        $this->assertSame('menyusun rencana kerja tahunan yang terukur.', $repo->temukan($id)['tujuan']);

        // Tambah latar belakang khusus dari tombol "Tambah".
        $halaman->call('bukaSunting', 'latar_belakang')->assertSet('teksSunting', '')
            ->set('teksSunting', 'Rapat kerja menjadi tindak lanjut evaluasi diri madrasah.')
            ->call('simpanSunting')
            ->assertSeeHtml('data-sunting="latar_belakang"')
            ->assertDontSeeHtml('data-testid="tambah-latar_belakang"');

        // Butir analisis hasil (teks khusus) tersimpan sebagai teks yang disesuaikan.
        $halaman->call('bukaSunting', 'analisis_hasil')
            ->set('teksSunting', 'Menganalisis capaian kegiatan {nama_kegiatan} bersama tim penjamin mutu.')
            ->call('simpanSunting')
            ->assertSee('Menganalisis capaian kegiatan Rapat Kerja Madrasah bersama tim penjamin mutu.');
        $this->assertTrue(TeksKhusus::disesuaikan('analisis_hasil', $repo->temukan($id)));

        // Berubah di tempat lain sejak dibuka: tidak menimpa, teks tetap di penyunting.
        $halaman->call('bukaSunting', 'tempat');
        $repo->simpan(['peserta_jenis' => 'guru'] + $repo->temukan($id), $id);
        $halaman->set('teksSunting', 'Ruang Multimedia')->call('simpanSunting')
            ->assertDispatched('notify', type: 'error')
            ->assertSet('suntingTerbuka', 'tempat')
            ->assertSet('teksSunting', 'Ruang Multimedia');
        $this->assertSame('Aula Madrasah', $repo->temukan($id)['tempat']);
        $halaman->call('simpanSunting')->assertSet('suntingTerbuka', null);
        $this->assertSame(['Ruang Multimedia', 'guru'], [$repo->temukan($id)['tempat'], $repo->temukan($id)['peserta_jenis']]);

        // Kembali ke mode baca menutup penyunting.
        $halaman->call('bukaSunting', 'sasaran')->set('mode', 'baca')->assertSet('suntingTerbuka', null)->assertDontSeeHtml('data-sunting=');
        $halaman->call('bukaSunting', 'tujuan')->assertForbidden();
    }

    public function test_kegiatan_terkunci_hanya_baca(): void
    {
        $id = $this->kegiatanBaru(['status' => 'diperiksa']);

        Livewire::withQueryParams(['mode' => 'sunting'])->test(PratinjauDokumen::class, ['kegiatan' => $id])
            ->assertSet('mode', 'baca')
            ->assertDispatched('notify', type: 'error')
            ->assertSeeHtml('data-pilihan-mode="sunting"')
            ->assertDontSeeHtml('data-sunting=')
            ->set('mode', 'sunting')
            ->assertSet('mode', 'baca')
            ->call('bukaSunting', 'tujuan')
            ->assertForbidden();
    }
}
