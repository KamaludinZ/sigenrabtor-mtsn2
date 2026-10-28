<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\PenyusunUsulanJadwal;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\PenyusunUsulanJadwalLlm;
use App\Support\Kegiatan\PermintaanUsulanJadwal;
use App\Support\Stub\StubMatrikJadwalRepository;
use App\Support\Stub\StubUsulanJadwal;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class IntegrasiMatrikJadwalTest extends TestCase
{
    public function test_binding_kontrak_memakai_tiruan(): void
    {
        $this->assertInstanceOf(StubMatrikJadwalRepository::class, app(MatrikJadwalRepository::class));
        $this->assertInstanceOf(PenyusunUsulanJadwalLlm::class, app(PenyusunUsulanJadwal::class), 'Tanpa penyedia aktif dijawab tiruan.');
    }

    public function test_contoh_dan_kegiatan_tanpa_entri(): void
    {
        $repo = app(MatrikJadwalRepository::class);

        $this->assertSame(StubMatrikJadwalRepository::contoh()[3]['jadwal'], $repo->jadwal(3)->baris);
        $this->assertSame([7], $repo->matrik(3)->butir['pelaksanaan'][0]['bulan']);
        $this->assertCount(JadwalKegiatan::JUMLAH_BAKU, $repo->jadwal(1)->baris);
        $this->assertSame(0, $repo->matrik(1)->jumlahTanda());
    }

    public function test_menyimpan_data_kegiatan_tidak_menghapus_matrik_dan_jadwal(): void
    {
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->call('isiDariPelaksanaan')->call('isiJadwalBaku')->call('simpan')->assertHasNoErrors();

        // Data Kegiatan disimpan ulang (hanya kolom form) — matrik & jadwal di penyimpanan terpisah tetap utuh.
        Livewire::test(Form::class, ['kegiatan' => 1])->call('saveDraft')->assertHasNoErrors();

        $repo = app(MatrikJadwalRepository::class);
        $this->assertSame(6, $repo->matrik(1)->jumlahTanda());
        $this->assertSame(6, $repo->jadwal(1)->jumlahTerisi());
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->assertSee('6 tanda bulan')->assertSee('6 dari 6 baris terisi');
    }

    public function test_data_lama_di_data_kegiatan_tetap_terbaca_lalu_pindah_saat_disimpan(): void
    {
        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Kegiatan format lama', 'bulan_pelaksanaan' => [5],
            'matrik' => ['pelaksanaan' => [['uraian' => 'Pelaksanaan', 'bulan' => [5]]]],
            'jadwal' => [['uraian' => 'Rapat lama', 'bulan' => [4]]],
        ]);

        Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->assertSet('jadwal', [['uraian' => 'Rapat lama', 'mulai' => 4, 'sampai' => null]])
            ->assertSet('matrik.pelaksanaan', [['uraian' => 'Pelaksanaan', 'bulan' => [5]]])
            ->set('jadwal.0.uraian', 'Rapat baru')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame([['uraian' => 'Rapat baru', 'bulan' => [4]]], app(MatrikJadwalRepository::class)->jadwal($id)->baris);
    }

    public function test_gagal_simpan_isian_di_layar_tetap_utuh(): void
    {
        $this->app->instance(MatrikJadwalRepository::class, new class(app(MatrikJadwalRepository::class)) implements MatrikJadwalRepository
        {
            public function __construct(private MatrikJadwalRepository $asli) {}

            public function matrik(int $kegiatanId): MatrikTahapan
            {
                return $this->asli->matrik($kegiatanId);
            }

            public function jadwal(int $kegiatanId): JadwalKegiatan
            {
                return $this->asli->jadwal($kegiatanId);
            }

            public function simpan(int $kegiatanId, MatrikTahapan $matrik, JadwalKegiatan $jadwal): void
            {
                throw new RuntimeException('Koneksi database terputus.');
            }
        });

        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])
            ->set('jadwal.0.uraian', 'Rapat panitia')
            ->call('simpan')
            ->assertDispatched('notify', message: 'Matrik & jadwal gagal disimpan. Perubahan masih ada di layar — coba simpan lagi.', type: 'error')
            ->assertNotDispatched('notify', message: 'Matrik & jadwal tersimpan.')
            ->assertSet('jadwal.0.uraian', 'Rapat panitia')
            ->assertSet('adaPerubahan', true);
    }

    public function test_permintaan_ke_penyusun_dan_hasilnya_dirapikan(): void
    {
        $penyusun = $this->penyusun(fn () => [
            ['judul' => 'Opsi A', 'ciri' => "  Lengkap\n", 'keterangan' => 'Disusun AI.', 'baris' => [
                ['uraian' => "Rapat\n  panitia ", 'mulai' => '7', 'sampai' => 6],
                ['uraian' => '   ', 'mulai' => 8],
                ['uraian' => str_repeat('a', 250), 'mulai' => 13, 'sampai' => 9],
                'bukan baris',
                ['uraian' => 'Pelaksanaan', 'mulai' => 8, 'sampai' => '9'],
            ]],
            ['judul' => 'Kosong', 'baris' => [['uraian' => '']]],
            ['ciri' => 'Panjang', 'baris' => array_fill(0, 20, ['uraian' => 'Langkah', 'mulai' => 8])],
            ['baris' => [['uraian' => 'Ketiga']]],
            ['baris' => [['uraian' => 'Keempat dibuang']]],
        ]);

        $id = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Lomba Kaligrafi', 'bulan_pelaksanaan' => [8], 'tanggal_laporan' => '2026-08-31',
            'konteks' => 'Narasumber pengawas, NIP 197210082007101002.',
        ]);

        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => $id])
            ->call('bukaUsulJadwal')
            ->set('usulInstruksi', "  lebih   ringkas \n")
            ->call('buatUsulJadwal')
            ->assertSet('usulGagal', null);

        $permintaan = $penyusun->permintaan;
        $this->assertSame([$id, 'lomba', 'lebih ringkas'], [$permintaan->kegiatanId, $permintaan->jenis, $permintaan->instruksi]);
        $this->assertSame(['persiapan' => 7, 'pelaksanaan' => [8], 'laporan' => 8], $permintaan->acuan);
        $this->assertStringContainsString('Nama kegiatan: Lomba Kaligrafi', $permintaan->bahan);
        $this->assertStringContainsString('[NIP disembunyikan]', $permintaan->bahan);
        $this->assertStringNotContainsString('197210082007101002', $permintaan->bahan);

        $usulan = $komponen->get('usulan');
        $this->assertSame(['Versi 1', 'Versi 2', 'Versi 3'], array_column($usulan, 'judul'), 'Maks. 3 versi berisi, dinomori ulang.');
        $this->assertSame(['Lengkap', 'Panjang', ''], array_column($usulan, 'ciri'));
        $this->assertSame([
            ['uraian' => 'Rapat panitia', 'mulai' => 7, 'sampai' => null],
            ['uraian' => str_repeat('a', 200), 'mulai' => null, 'sampai' => null],
            ['uraian' => 'Pelaksanaan', 'mulai' => 8, 'sampai' => 9],
        ], $usulan[0]['baris']);
        $this->assertCount(JadwalKegiatan::MAKS_BARIS, $usulan[1]['baris']);
        $komponen->assertSeeHtml('Versi 3</h3>');
    }

    public function test_penyusun_gagal_atau_kosong_tampil_di_panel(): void
    {
        $this->penyusun(fn () => throw new GagalUsulanAi('Server AI madrasah tidak menjawab.'));

        $komponen = Livewire::test(MatrikJadwal::class, ['kegiatan' => 2])
            ->call('bukaUsulJadwal')
            ->call('buatUsulJadwal')
            ->assertSet('usulGagal', 'Server AI madrasah tidak menjawab.')
            ->assertSet('usulan', [])
            ->assertSeeHtml('data-testid="usul-gagal"')
            ->assertSee('Usulan AI belum bisa dibuat')
            ->assertSee('Coba lagi')
            ->assertSet('jadwal.0.uraian', '')
            ->assertSet('adaPerubahan', false);

        $this->penyusun(fn () => [['baris' => [['uraian' => ' ']]]]);
        $komponen->call('buatUsulJadwal')->assertSet('usulGagal', 'AI tidak memberi usulan yang bisa dipakai. Coba lagi atau ubah instruksi.');

        // Penyusun pulih: coba lagi menampilkan versi dan pesan gagal hilang.
        $this->app->instance(PenyusunUsulanJadwal::class, new StubUsulanJadwal);
        $komponen->call('buatUsulJadwal')
            ->assertSet('usulGagal', null)
            ->assertCount('usulan', 3)
            ->assertDontSeeHtml('data-testid="usul-gagal"');
    }

    /**
     * Penyusun palsu yang mencatat permintaan terakhir.
     *
     * @param  callable(PermintaanUsulanJadwal): array<mixed>  $jawab
     */
    private function penyusun(callable $jawab): object
    {
        $palsu = new class($jawab) implements PenyusunUsulanJadwal
        {
            public ?PermintaanUsulanJadwal $permintaan = null;

            /** @var callable */
            private $jawab;

            public function __construct(callable $jawab)
            {
                $this->jawab = $jawab;
            }

            public function usulkan(PermintaanUsulanJadwal $permintaan, string $penyedia = 'tiruan'): array
            {
                $this->permintaan = $permintaan;

                return ($this->jawab)($permintaan);
            }
        };

        $this->app->instance(PenyusunUsulanJadwal::class, $palsu);

        return $palsu;
    }
}
