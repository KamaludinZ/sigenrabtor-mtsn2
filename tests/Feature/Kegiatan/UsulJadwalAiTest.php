<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Support\Kegiatan\KonteksAi;
use Livewire\Livewire;
use Tests\TestCase;

class UsulJadwalAiTest extends TestCase
{
    public function test_jenis_kegiatan_ditebak_dari_nama(): void
    {
        $this->assertSame(['workshop', 'supervisi', 'lomba', 'siswa', 'umum'], array_keys(KonteksAi::jenisKegiatan()));
        $this->assertSame('Kegiatan lainnya', KonteksAi::jenisKegiatan()['umum']);
        $this->assertSame(
            ['workshop', 'supervisi', 'lomba', 'umum', 'umum'],
            array_map(KonteksAi::tebakJenis(...), ['Workshop Penyusunan Kurikulum', 'Supervisi Akademik', 'Lomba Kaligrafi', 'Pengadaan meja', '']),
        );
    }

    public function test_panel_usul_jadwal_menebak_jenis_dan_menampilkan_acuan(): void
    {
        // Supervisi: Agustus–September, laporan 14 September.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->assertSee('Usulkan dengan AI')
            ->assertDontSeeHtml('data-testid="panel-usul-jadwal"')
            ->call('bukaUsulJadwal')
            ->assertSet('usulTerbuka', true)
            ->assertSet('usulJenis', 'supervisi')
            ->assertSeeHtml('data-testid="panel-usul-jadwal"')
            ->assertSeeHtml('data-testid="usul-permintaan"')
            ->assertSeeHtmlInOrder(['Usulkan jadwal dengan AI', 'Permintaan', 'Pilih versi', 'Acuan waktu', 'Agustus – September 2026', '14 September 2026'])
            ->assertSeeHtmlInOrder(['Supervisi / evaluasi / rapat kerja', 'Cocok dengan nama kegiatan', 'Kegiatan lainnya'])
            ->assertSeeHtml('value="supervisi" wire:model="usulJenis" checked')
            ->assertSeeHtmlInOrder(MatrikJadwal::SARAN_INSTRUKSI)
            ->assertSee('Nama dan NIP pejabat tidak pernah dikirim ke AI.')
            ->assertDontSeeHtml('data-testid="usul-tanpa-bulan"')
            ->call('tutupUsulJadwal')
            ->assertDontSeeHtml('data-testid="panel-usul-jadwal"');
    }

    public function test_buat_usulan_tiga_versi_dari_bulan_acuan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->call('bukaUsulJadwal')
            ->set('usulInstruksi', 'lebih ringkas')
            ->call('buatUsulJadwal')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-testid="usul-hasil"')
            ->assertSeeHtmlInOrder(['3 versi untuk', 'Supervisi / evaluasi / rapat kerja', 'dengan instruksi “lebih ringkas”'])
            ->assertSeeHtmlInOrder(['Versi 1 · Lengkap', '6 baris', 'Versi 2 · Persiapan lebih awal', '5 baris', 'Versi 3 · Ringkas', '4 baris'])
            ->assertSee('Lihat usulan AI');

        $usulan = $komponen->get('usulan');
        $this->assertSame([6, 5, 4], array_map(fn ($v) => count($v['baris']), $usulan));
        $this->assertSame([
            ['uraian' => 'Penyusunan jadwal dan instrumen supervisi', 'mulai' => 7, 'sampai' => null],
            ['uraian' => 'Sosialisasi jadwal supervisi kepada guru', 'mulai' => 7, 'sampai' => null],
            ['uraian' => 'Rapat koordinasi tim supervisor', 'mulai' => 7, 'sampai' => 8],
            ['uraian' => 'Pelaksanaan supervisi dan observasi kelas', 'mulai' => 8, 'sampai' => 9],
            ['uraian' => 'Refleksi dan umpan balik hasil supervisi', 'mulai' => 9, 'sampai' => null],
            ['uraian' => 'Penyusunan laporan dan rencana tindak lanjut', 'mulai' => 9, 'sampai' => null],
        ], $usulan[0]['baris']);
        // Persiapan dimajukan sebulan; versi ringkas membuang langkah pendukung lebih dulu.
        $this->assertSame([6, 6, 6, 8, 9], array_column($usulan[1]['baris'], 'mulai'));
        $this->assertSame(
            ['Penyusunan jadwal dan instrumen supervisi', 'Sosialisasi jadwal supervisi kepada guru', 'Pelaksanaan supervisi dan observasi kelas', 'Penyusunan laporan dan rencana tindak lanjut'],
            array_column($usulan[2]['baris'], 'uraian'),
        );
        $komponen->assertSeeHtmlInOrder(['Rapat koordinasi tim supervisor', 'Juni – Agustus 2026']);
    }

    public function test_pakai_usulan_mengganti_jadwal_dan_bisa_diurungkan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->set('jadwal.0.uraian', 'Rapat lama')
            ->set('jadwal.0.mulai', '8')
            ->call('bukaUsulJadwal')
            ->call('buatUsulJadwal')
            ->assertSeeHtml('data-testid="usul-ganti-jadwal"')
            ->assertSee('Memakai satu versi mengganti 1 baris jadwal yang sudah terisi.')
            ->call('pakaiUsulan', 2)
            ->assertSet('usulTerbuka', false)
            ->assertSet('usulan', [])
            ->assertSet('adaPerubahan', true)
            ->assertSet('jadwal.0', ['uraian' => 'Penyusunan jadwal dan instrumen supervisi', 'mulai' => 7, 'sampai' => null])
            ->assertSee('Jadwal diganti dengan usulan AI (Versi 3) — periksa lalu simpan.')
            ->assertSee('4 dari 4 baris terisi')
            ->assertSee('Usulkan dengan AI')
            ->call('urungkanJadwal')
            ->assertSet('jadwal.0', ['uraian' => 'Rapat lama', 'mulai' => 8, 'sampai' => null])
            ->assertCount('jadwal', 6);

        $komponen->call('bukaUsulJadwal')->call('buatUsulJadwal')->call('pakaiUsulan', 0)->call('simpan')->assertHasNoErrors();

        $tersimpan = app(MatrikJadwalRepository::class)->jadwal(2)->baris;
        $this->assertCount(6, $tersimpan);
        $this->assertSame(['uraian' => 'Pelaksanaan supervisi dan observasi kelas', 'bulan' => [8, 9]], $tersimpan[3]);

        // Indeks versi yang tidak ada diabaikan.
        $komponen->call('pakaiUsulan', 5)->assertSet('jadwal.3.uraian', 'Pelaksanaan supervisi dan observasi kelas');
    }

    public function test_tolak_usulan_dan_validasi_permintaan(): void
    {
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->call('bukaUsulJadwal')
            ->assertSet('usulJenis', 'workshop')
            ->set('usulJenis', 'tidak-ada')
            ->call('buatUsulJadwal')
            ->assertHasErrors(['usulJenis' => 'in'])
            ->assertSee('Pilih jenis kegiatan dari daftar.')
            ->set('usulJenis', 'umum')
            ->set('usulInstruksi', str_repeat('a', 301))
            ->call('buatUsulJadwal')
            ->assertHasErrors(['usulInstruksi' => 'max'])
            ->set('usulInstruksi', '')
            ->call('buatUsulJadwal')
            ->assertHasNoErrors()
            ->assertSee('Pembentukan kepanitiaan')
            ->call('tolakUsulan')
            ->assertSet('usulan', [])
            ->assertSet('usulJenis', 'umum')
            ->assertSeeHtml('data-testid="usul-permintaan"')
            ->assertSet('jadwal.0', ['uraian' => '', 'mulai' => null, 'sampai' => null])
            ->assertSet('adaPerubahan', false);
    }

    public function test_usulan_tanpa_bulan_pelaksanaan_hanya_uraian(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kaligrafi Antarkelas']);

        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->call('bukaUsulJadwal')
            ->assertSet('usulJenis', 'lomba')
            ->assertSeeHtml('data-testid="usul-tanpa-bulan"')
            ->call('buatUsulJadwal')
            ->assertSee('bulan dipilih sendiri');

        foreach ($komponen->get('usulan') as $versi) {
            $this->assertSame([null], array_values(array_unique(array_column($versi['baris'], 'mulai'))));
        }
    }

    public function test_usul_jadwal_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Sudah final', 'status' => 'final', 'bulan_pelaksanaan' => [5]]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertDontSee('Usulkan dengan AI')
            ->call('bukaUsulJadwal')
            ->assertForbidden();
    }
}
