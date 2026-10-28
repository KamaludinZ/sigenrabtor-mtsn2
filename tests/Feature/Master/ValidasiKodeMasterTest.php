<?php

namespace Tests\Feature\Master;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Kegiatan\ValidasiKegiatan;
use App\Support\Master\ValidasiKodeMaster;
use App\Support\Pengaturan\KontrolTahunAktif;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ValidasiKodeMasterTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    public function test_kode_dicocokkan_dengan_master_tahun_kegiatannya(): void
    {
        $kode = ['kode_rktm' => '6.19.1', 'kode_sk' => 'SK.19', 'kode_iksk' => 'IKSK 19.2', 'kode_edm' => 'A3', 'budaya_mutu' => 2];

        $this->assertSame([], ValidasiKodeMaster::galat($kode + ['tahun_anggaran' => 2026]));
        $this->assertSame([], ValidasiKodeMaster::galat($kode), 'Tanpa tahun = tahun aktif (2026).');
        $this->assertSame(2026, ValidasiKodeMaster::tahun([]));

        $lalu = ValidasiKodeMaster::galat($kode + ['tahun_anggaran' => 2025]);
        $this->assertSame('Kode RKTM 6.19.1 tidak ada di master TA 2025.', $lalu['kode_rktm']);
        $this->assertSame('Kode SK SK.19 tidak ada di master TA 2025.', $lalu['kode_sk']);
        $this->assertSame('IKSK IKSK 19.2 tidak ada di master TA 2025.', $lalu['kode_iksk']);
        $this->assertSame($lalu, ValidasiKodeMaster::galat($kode, 2025), 'Tahun bisa diberikan langsung.');

        $this->assertSame(['kode_iksk' => 'IKSK 19.2 bukan indikator dari SK.10.'], ValidasiKodeMaster::galat(['kode_sk' => 'SK.10', 'kode_iksk' => 'IKSK 19.2']));
        $this->assertSame(['kode_iksk' => 'Pilih kode SK terlebih dahulu.'], ValidasiKodeMaster::galat(['kode_iksk' => 'IKSK 19.2']));
        $this->assertSame(['budaya_mutu' => '5 Budaya Mutu 9 tidak ada di master TA 2026.'], ValidasiKodeMaster::galat(['budaya_mutu' => '9']));
        $this->assertSame([], ValidasiKodeMaster::galat(['kode_rktm' => null, 'kode_sk' => '', 'budaya_mutu' => null]), 'Kosong tidak ditolak di sini.');

        try {
            ValidasiKodeMaster::periksa(['kode_edm' => 'Z9'], 2026);
            $this->fail('Kode asing harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['kode_edm' => ['Kode EDM Z9 tidak ada di master TA 2026.']], $e->errors());
        }

        // ValidasiKegiatan memakai aturan yang sama untuk bentuk data repositori.
        $this->assertSame(['Kode SK SK.19 tidak ada di master TA 2025.'], ValidasiKegiatan::galat(['nama_kegiatan' => 'Supervisi Akademik', 'nama_kegiatan_cover' => 'SUPERVISI AKADEMIK', 'kode_sk' => 'SK.19', 'tahun_anggaran' => 2025]));
    }

    public function test_kegiatan_tahun_lain_yang_terbuka_memakai_masternya_sendiri(): void
    {
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Supervisi Akademik', 'nama_kegiatan_cover' => 'SUPERVISI AKADEMIK', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        KontrolTahunAktif::aktifkan(2025, ['name' => 'Koordinator PUSKOM']);

        // Tahun aktif 2025, kegiatan tetap TA 2026 (belum dikunci): kode 2026 sah, bukan master 2025.
        $this->assertSame(['kode_sk', 'kode_iksk'], UbahDataKegiatan::ubah($id, ['kode_sk' => 'SK.19'], self::LEADING)['diubah']);
        $this->assertSame('IKSK 19.2', app(KegiatanRepository::class)->temukan($id)['kode_iksk'], 'Satu-satunya IKSK SK.19 di master 2026.');

        Livewire::test(Klasifikasi::class, ['kegiatan' => $id])
            ->assertSet('tahunMaster', 2026)
            ->set('form.kode_rktm', '6.19.1')
            ->call('simpan')
            ->assertHasNoErrors();
        $this->assertSame('6.19.1', app(KegiatanRepository::class)->temukan($id)['kode_rktm']);

        try {
            UbahDataKegiatan::ubah($id, ['kode_edm' => 'Z9'], self::LEADING);
            $this->fail('Kode asing harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Kode EDM Z9 tidak ada di master TA 2026.', $e->errors()['kode_edm'][0]);
        }
    }
}
