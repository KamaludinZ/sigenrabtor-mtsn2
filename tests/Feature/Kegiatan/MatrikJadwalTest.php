<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Kegiatan\Rab;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Livewire\Livewire;
use Tests\TestCase;

class MatrikJadwalTest extends TestCase
{
    public function test_matrik_baku_tiga_tahapan_dan_tanda_bulan_dirapikan(): void
    {
        $baku = MatrikTahapan::dari([]);
        $this->assertSame(['persiapan', 'pelaksanaan', 'evaluasi_pelaporan'], array_keys($baku->butir));
        $this->assertSame(['Rapat dan pembagian job deskripsi', 'Penyerahan anggaran per seksi'], array_column($baku->butir['persiapan'], 'uraian'));
        $this->assertSame([0, false], [$baku->jumlahTanda(), $baku->lengkap()]);

        $matrik = MatrikTahapan::dari(['matrik' => [
            'persiapan' => [['uraian' => ' Rapat panitia ', 'bulan' => [6, '6', 13, 0, 5]]],
            'pelaksanaan' => [['uraian' => 'Workshop', 'bulan' => [7]], ['uraian' => 'Pendampingan', 'bulan' => [7, 8]]],
        ]]);
        $this->assertSame([['uraian' => 'Rapat panitia', 'bulan' => [5, 6]]], $matrik->butir['persiapan']);
        $this->assertSame([7, 8], $matrik->bulanTahapan('pelaksanaan'));
        $this->assertSame(['Penyusunan laporan', 'Penyerahan laporan'], array_column($matrik->butir['evaluasi_pelaporan'], 'uraian'), 'Tahapan yang belum disimpan memakai butir baku.');
        $this->assertSame([5, false], [$matrik->jumlahTanda(), $matrik->lengkap()]);

        $this->assertTrue(MatrikTahapan::dari(['matrik' => [
            'persiapan' => [['uraian' => 'a', 'bulan' => [6]]],
            'pelaksanaan' => [['uraian' => 'b', 'bulan' => [7]]],
            'evaluasi_pelaporan' => [['uraian' => 'c', 'bulan' => []], ['uraian' => 'd', 'bulan' => [8]]],
        ]])->lengkap());
    }

    public function test_jadwal_enam_baris_baku_dan_waktu_mengikuti_tahun_aktif(): void
    {
        $baku = JadwalKegiatan::dari([]);
        $this->assertCount(JadwalKegiatan::JUMLAH_BAKU, $baku->baris);
        $this->assertSame([0, false, null], [$baku->jumlahTerisi(), $baku->lengkap(), $baku->waktu(0, 2026)]);

        $jadwal = JadwalKegiatan::dari(['jadwal' => [
            ['uraian' => 'Pembentukan panitia', 'bulan' => [6]],
            ['uraian' => 'Pelaksanaan workshop', 'bulan' => [8, 7]],
            ['uraian' => 'Penyusunan laporan', 'bulan' => []],
        ]]);
        $this->assertSame(['Juni 2026', 'Juli – Agustus 2026', null], [$jadwal->waktu(0, 2026), $jadwal->waktu(1, 2026), $jadwal->waktu(2, 2026)]);
        $this->assertSame([2, false], [$jadwal->jumlahTerisi(), $jadwal->lengkap()]);
        $this->assertTrue(JadwalKegiatan::dari(['jadwal' => [['uraian' => 'Rapat', 'bulan' => [1]]]])->lengkap());
    }

    public function test_halaman_matrik_jadwal_dengan_acuan_bulan_pelaksanaan(): void
    {
        $this->get(route('kegiatan.matrik', ['kegiatan' => 1]))->assertOk()->assertSeeLivewire(MatrikJadwal::class);

        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSee('Matrik &amp; Jadwal Kegiatan', false)
            ->assertSeeHtml('aria-current="page"')
            ->assertSeeInOrder(['Bulan pelaksanaan', 'Juli 2026', 'Tanggal', '14 Juli 2026', 'Batas laporan', '30 Juli 2026'])
            ->assertSeeInOrder(['Matrik tahapan', '1. Persiapan', 'Rapat dan pembagian job deskripsi', '2. Pelaksanaan', '3. Evaluasi dan Pelaporan', 'Penyerahan laporan'])
            ->assertSeeInOrder(['Jadwal kegiatan', '0 dari 6 baris terisi', 'Waktu belum dipilih'])
            ->assertSeeHtml('data-testid="baris-jadwal-6"')
            ->assertSeeHtml('data-testid="kelengkapan-matrik" data-lengkap="tidak"')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 1]).'#pelaksanaan"');

        // Kolom bulan pelaksanaan (Juli) ditandai sebagai acuan.
        $this->assertMatchesRegularExpression('/data-bulan="7"\s+data-pelaksanaan\s+class="[^"]*bg-brand-50/', $komponen->html());
        $this->assertDoesNotMatchRegularExpression('/data-bulan="6"[^>]*data-pelaksanaan/', $komponen->html());

        $this->get(route('kegiatan.matrik', ['kegiatan' => 999]))->assertNotFound();
    }

    public function test_sel_grid_state_lokal_terkirim_bersama_aksi_dan_dirapikan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSeeHtml('x-data="gridMatrik({ dapatDiedit: true })"')
            ->assertSeeHtml('aria-label="Persiapan: Rapat dan pembagian job deskripsi — Juli"')
            // Tanda sel adalah state lokal: tidak ada request per sel; ringkasan, kelengkapan & "Belum disimpan" dihitung di klien.
            ->assertDontSeeHtml('aturBulan')
            ->assertSeeHtml('x-text="$ringkasanMatrik($wire.matrik).jumlahTanda + \' tanda bulan\'"')
            ->assertSeeHtml('x-bind:data-lengkap="lengkap ? \'ya\' : \'tidak\'"')
            ->assertSeeHtml('wire:target="isiDariPelaksanaan, kosongkanMatrik, urungkanMatrik, batalkanPerubahan, simpan"')
            ->assertSeeHtml('x-on:livewire:navigate.document=');
        $this->assertMatchesRegularExpression('/x-show="dirty"\s+style="display: none"[^>]*data-testid="belum-disimpan"/', $komponen->html());

        // Nilai yang dikirim klien dirapikan lewat updatedMatrik; tahapan tak dikenal dibuang.
        $komponen->set('matrik.persiapan.0.bulan', [7, '6', 6, 13])
            ->assertSet('matrik.persiapan.0.bulan', [6, 7])
            ->assertSet('adaPerubahan', true)
            ->assertSee('2 tanda bulan')
            ->set('matrik.tahapan-lain', [['uraian' => 'Lain', 'bulan' => [1]]])
            ->set('matrik.pelaksanaan.1.bulan', [9, 3, 3])
            ->assertSet('matrik.pelaksanaan.1.bulan', [3, 9]);

        $this->assertSame(['persiapan', 'pelaksanaan', 'evaluasi_pelaporan'], array_keys($komponen->get('matrik')));
        $this->assertMatchesRegularExpression('/data-bulan="7" data-ditandai="ya"/', $komponen->html());
        $this->assertDoesNotMatchRegularExpression('/style="display: none"[^>]*data-testid="belum-disimpan"/', $komponen->html());
        $this->assertSame(4, $komponen->instance()->susunanMatrik->jumlahTanda());
    }

    public function test_ringkasan_matrik_server_sama_dengan_kembaran_klien(): void
    {
        // Berkas yang sama dipakai tests/e2e/format.spec.js untuk ringkasanMatrik() di resources/js/matrik.js.
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/ringkasan-matrik.json')), true);
        $this->assertNotEmpty($kasus);

        foreach ($kasus as $k) {
            $matrik = MatrikTahapan::dari(['matrik' => $k['matrik']]);
            $this->assertSame([$k['jumlahTanda'], $k['lengkap']], [$matrik->jumlahTanda(), $matrik->lengkap()], $k['keterangan']);
        }
    }

    public function test_isi_dari_jadwal_pelaksanaan_kosongkan_dan_urungkan(): void
    {
        // Workshop: Juli 2026, laporan 30 Juli → Persiapan Juni, Pelaksanaan Juli, Evaluasi & Pelaporan Juli.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->call('isiDariPelaksanaan')
            ->assertSet('matrik.persiapan.0.bulan', [6])
            ->assertSet('matrik.persiapan.1.bulan', [6])
            ->assertSet('matrik.pelaksanaan.0.bulan', [7])
            ->assertSet('matrik.evaluasi_pelaporan.1.bulan', [7])
            ->assertSee('Matrik diisi dari jadwal pelaksanaan.')
            ->assertSeeHtml('data-testid="kelengkapan-matrik" data-lengkap="ya"')
            ->call('kosongkanMatrik')
            ->assertSet('matrik.pelaksanaan.0.bulan', [])
            ->assertSee('Semua tanda bulan dikosongkan.')
            ->call('urungkanMatrik')
            ->assertSet('matrik.pelaksanaan.0.bulan', [7])
            ->assertSet('matrikSebelum', null);

        // Supervisi: Agustus–September, laporan 14 September.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->call('isiDariPelaksanaan')
            ->assertSet('matrik.persiapan.0.bulan', [7])
            ->assertSet('matrik.pelaksanaan.1.bulan', [8, 9])
            ->assertSet('matrik.evaluasi_pelaporan.0.bulan', [9])
            ->assertSeeHtml('x-on:matrik-diubah-lokal.window="tampil = false"')
            // Sel yang diubah tangan membatalkan "Urungkan" isi otomatis.
            ->set('matrik.persiapan.0.bulan', [6, 7])
            ->assertSet('matrikSebelum', null)
            ->assertDontSeeHtml('data-testid="matrik-berubah"');

        $tanpaBulan = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa jadwal']);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => $tanpaBulan])
            ->assertSeeHtml('wire:click="isiDariPelaksanaan" disabled')
            ->call('isiDariPelaksanaan')
            ->assertDispatched('notify', message: 'Isi bulan pelaksanaan di Data Kegiatan terlebih dahulu.')
            ->assertSet('adaPerubahan', false);
    }

    public function test_simpan_dan_batalkan_matrik(): void
    {
        $repo = app(KegiatanRepository::class);
        $namaSemula = $repo->temukan(1)['nama_kegiatan'];

        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('matrik.pelaksanaan.0.bulan', [7])
            ->call('batalkanPerubahan')
            ->assertSet('matrik.pelaksanaan.0.bulan', [])
            ->assertSet('adaPerubahan', false)
            ->call('isiDariPelaksanaan')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Matrik & jadwal tersimpan.')
            ->assertSet('adaPerubahan', false)
            ->assertDontSeeHtml('data-testid="matrik-berubah"');

        $tersimpan = $repo->temukan(1);
        $this->assertSame([6], $tersimpan['matrik']['persiapan'][0]['bulan']);
        $this->assertSame($namaSemula, $tersimpan['nama_kegiatan'], 'Isian kegiatan lain tidak tersentuh.');
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->assertSet('matrik.evaluasi_pelaporan.1.bulan', [7])->assertSee('6 tanda bulan');

        // Uraian butir wajib diisi.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('matrik.persiapan.0.uraian', '')
            ->call('simpan')
            ->assertHasErrors(['matrik.persiapan.0.uraian' => 'required']);
    }

    public function test_matrik_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Sudah final', 'status' => 'final', 'bulan_pelaksanaan' => [5]]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertSeeHtml('data-testid="banner-terkunci"')
            ->assertSeeHtml('x-data="gridMatrik({ dapatDiedit: false })"')
            ->assertDontSee('Isi dari jadwal pelaksanaan')
            ->assertDontSeeHtml('wire:click="simpan"')
            ->set('matrik.persiapan.0.bulan', [5])
            ->assertForbidden();
    }

    public function test_baris_jadwal_disunting_dan_waktu_dirapikan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSeeHtml('wire:model.live.blur="jadwal.0.uraian"')
            ->assertSeeHtml('placeholder="mis. Pembentukan kepanitiaan"')
            // Uraian tidak dirapikan di tempat (ketikan tidak tertimpa); dirapikan saat disimpan.
            ->set('jadwal.0.uraian', ' Pembentukan  panitia ')
            ->assertSet('jadwal.0.uraian', ' Pembentukan  panitia ')
            ->assertSet('adaPerubahan', true)
            ->set('jadwal.0.mulai', '7')
            ->assertSet('jadwal.0.mulai', 7)
            ->assertSeeHtml('data-testid="waktu-jadwal-1">Juli 2026')
            ->set('jadwal.0.sampai', '8')
            ->assertSet('jadwal.0.sampai', 8)
            ->assertSeeHtml('data-testid="waktu-jadwal-1">Juli – Agustus 2026')
            ->assertSee('1 dari 6 baris terisi')
            // Bulan mulai dipindah melewati "s.d." → "s.d." dilepas; bulan mulai dikosongkan → keduanya kosong.
            ->set('jadwal.0.mulai', '9')
            ->assertSet('jadwal.0.sampai', null)
            ->set('jadwal.0.sampai', '12')
            ->set('jadwal.0.mulai', '')
            ->assertSet('jadwal.0.mulai', null)
            ->assertSet('jadwal.0.sampai', null)
            ->set('jadwal.1.sampai', '5')
            ->assertSet('jadwal.1.sampai', null);

        // Opsi "s.d." hanya bulan setelah bulan mulai.
        $komponen->set('jadwal.2.mulai', '10');
        $this->assertSame(1, preg_match('/id="jadwal-2-sampai".*?<\/select>/s', $komponen->html(), $select));
        preg_match_all('/<option value="(\d*)">/', $select[0], $opsi);
        $this->assertSame(['', '11', '12'], $opsi[1]);
    }

    public function test_isi_jadwal_baku_dari_jadwal_pelaksanaan_dan_urungkan(): void
    {
        // Supervisi: Agustus–September, laporan 14 September.
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->call('isiJadwalBaku')
            ->assertSet('jadwal', [
                ['uraian' => 'Pembentukan kepanitiaan', 'mulai' => 7, 'sampai' => null],
                ['uraian' => 'Rapat koordinasi panitia', 'mulai' => 7, 'sampai' => null],
                ['uraian' => 'Persiapan sarana dan bahan kegiatan', 'mulai' => 7, 'sampai' => 8],
                ['uraian' => 'Pelaksanaan kegiatan', 'mulai' => 8, 'sampai' => 9],
                ['uraian' => 'Evaluasi kegiatan', 'mulai' => 9, 'sampai' => null],
                ['uraian' => 'Penyusunan dan penyerahan laporan', 'mulai' => 9, 'sampai' => null],
            ])
            ->assertSee('Jadwal baku diisi dari jadwal pelaksanaan.')
            ->assertSee('6 dari 6 baris terisi')
            ->assertSeeHtml('data-testid="kelengkapan-jadwal" data-lengkap="ya"')
            ->call('urungkanJadwal')
            ->assertSet('jadwal.3', ['uraian' => '', 'mulai' => null, 'sampai' => null])
            ->assertSet('jadwalSebelum', null);

        $tanpaBulan = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Tanpa jadwal']);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => $tanpaBulan])
            ->call('isiJadwalBaku')
            ->assertSet('jadwal.0', ['uraian' => 'Pembentukan kepanitiaan', 'mulai' => null, 'sampai' => null])
            ->assertSee('Uraian jadwal baku diisi; pilih bulannya karena bulan pelaksanaan belum diisi.');
    }

    public function test_simpan_jadwal_dan_validasi(): void
    {
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('jadwal.1.mulai', '5')
            ->call('simpan')
            ->assertHasErrors(['jadwal.1.uraian'])
            ->assertSee('Isi uraian kegiatan untuk bulan yang sudah dipilih.')
            // Galat baris terikat indeks: dibuang begitu baris dihapus.
            ->call('hapusBarisJadwal', 1)
            ->assertHasNoErrors()
            ->set('jadwal.0.uraian', ' Rapat   panitia ')
            ->set('jadwal.0.mulai', '7')
            ->set('jadwal.0.sampai', '8')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Matrik & jadwal tersimpan.')
            ->assertSet('jadwal.0.uraian', 'Rapat panitia')
            ->assertSet('adaPerubahan', false);

        $tersimpan = app(KegiatanRepository::class)->temukan(1)['jadwal'];
        $this->assertSame(['uraian' => 'Rapat panitia', 'bulan' => [7, 8]], $tersimpan[0]);
        $this->assertCount(5, $tersimpan);
        $this->assertSame(['uraian' => '', 'bulan' => []], $tersimpan[4]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSet('jadwal.0', ['uraian' => 'Rapat panitia', 'mulai' => 7, 'sampai' => 8])
            ->assertSee('1 dari 5 baris terisi');

        $this->assertSame([['uraian' => 'Laporan', 'bulan' => [12]]], MatrikJadwal::jadwalUntukDisimpan([['uraian' => "Laporan\n", 'mulai' => '12', 'sampai' => '3']]));
    }

    public function test_tambah_geser_hapus_baris_jadwal_dan_urungkan(): void
    {
        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSee('6 dari maks. 12 baris')
            ->assertSeeHtml('wire:click="geserBarisJadwal(0, -1)" disabled')
            ->assertSeeHtml('wire:click="geserBarisJadwal(5, 1)" disabled')
            ->call('tambahBarisJadwal')
            ->assertDispatched('jadwal-ditambah')
            ->assertCount('jadwal', 7)
            ->assertSet('jadwal.6', ['uraian' => '', 'mulai' => null, 'sampai' => null])
            ->assertSet('adaPerubahan', true)
            ->assertSee('0 dari 7 baris terisi')
            ->assertSeeHtml('data-testid="baris-jadwal-7"')
            ->set('jadwal.6.uraian', 'Rapat evaluasi bersama komite')
            ->set('jadwal.6.mulai', '8')
            // Naikkan/turunkan menukar baris; baris pertama tidak bisa dinaikkan.
            ->call('geserBarisJadwal', 6, -1)
            ->assertSet('jadwal.5', ['uraian' => 'Rapat evaluasi bersama komite', 'mulai' => 8, 'sampai' => null])
            ->assertSet('jadwal.6.uraian', '')
            ->call('geserBarisJadwal', 0, -1)
            ->call('geserBarisJadwal', 6, 1)
            ->assertSet('jadwal.5.uraian', 'Rapat evaluasi bersama komite')
            ->call('hapusBarisJadwal', 5)
            ->assertCount('jadwal', 6)
            ->assertSee('Baris 6 “Rapat evaluasi bersama komite” dihapus.')
            ->call('urungkanJadwal')
            ->assertCount('jadwal', 7)
            ->assertSet('jadwal.5.uraian', 'Rapat evaluasi bersama komite')
            ->call('hapusBarisJadwal', 6)
            ->assertSee('Baris 7 dihapus.')
            ->call('hapusBarisJadwal', 99)
            ->assertCount('jadwal', 6);

        // Batas 12 baris.
        foreach (range(1, 6) as $_) {
            $komponen->call('tambahBarisJadwal');
        }
        $komponen->assertCount('jadwal', 12)
            ->assertSeeHtml('wire:click="tambahBarisJadwal" disabled')
            ->call('tambahBarisJadwal')
            ->assertDispatched('notify', message: 'Jadwal sudah mencapai batas 12 baris.')
            ->assertCount('jadwal', 12);

        // Semua baris boleh dihapus; jadwal kosong tersimpan apa adanya (tidak kembali ke 6 baris baku).
        foreach (range(1, 12) as $_) {
            $komponen->call('hapusBarisJadwal', 0);
        }
        $komponen->assertSet('jadwal', [])
            ->assertSeeHtml('data-testid="jadwal-kosong"')
            ->assertSee('Belum ada baris jadwal.')
            ->assertSeeHtml('data-testid="kelengkapan-jadwal" data-lengkap="tidak"')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame([], app(KegiatanRepository::class)->temukan(1)['jadwal']);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->assertSet('jadwal', [])
            ->assertSeeHtml('data-testid="ringkasan-jadwal">Belum ada baris');
    }

    public function test_jadwal_terkunci_di_luar_draf(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Sudah final', 'status' => 'final', 'bulan_pelaksanaan' => [5],
            'jadwal' => [['uraian' => 'Rapat akhir', 'bulan' => [5]]],
        ]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertSee('Rapat akhir')
            ->assertSeeHtml('data-testid="waktu-jadwal-1">Mei 2026')
            ->assertDontSeeHtml('wire:model.live.blur="jadwal.0.uraian"')
            ->assertDontSee('Isi jadwal baku')
            ->assertDontSee('Tambah baris')
            ->assertDontSeeHtml('wire:click="hapusBarisJadwal(0)"')
            ->set('jadwal.0.uraian', 'Diubah')
            ->assertForbidden();

        foreach (['tambahBarisJadwal' => [], 'hapusBarisJadwal' => [0], 'geserBarisJadwal' => [0, 1]] as $aksi => $argumen) {
            Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])->call($aksi, ...$argumen)->assertForbidden();
        }
    }

    public function test_tab_matrik_jadwal_aktif_di_ruang_kerja_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 1])
            ->assertSeeHtml('href="'.route('kegiatan.matrik', ['kegiatan' => 1]).'"');
    }
}
