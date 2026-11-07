<?php

namespace Tests\Feature\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Livewire\Master\Daftar;
use App\Support\Master\JenisKode;
use App\Support\Master\KelolaRencanaAksi;
use App\Support\Master\RencanaAksi;
use App\Support\Master\SalinMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RencanaAksiMasterTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @var array<string, mixed> */
    private array $kode;

    protected function setUp(): void
    {
        parent::setUp();
        $iksk = MasterKode::kodeIksk(tahun: 2026)[0];
        $rktm = MasterKode::kodeRktm(2026);
        $edm = MasterKode::kodeEdm(2026);
        $this->kode = ['sk' => $iksk['kode_sk'], 'iksk' => $iksk['kode'], 'rktm' => $rktm[0]['kode'], 'rktm2' => $rktm[1]['kode'], 'edm' => $edm[0]['kode'], 'edm2' => $edm[1]['kode']];
        $kegiatan = app(KegiatanRepository::class)->temukan(1);

        $ra = fn (string $kode, string $uraian, array $isi = []) => $isi + [
            'kode' => $kode, 'uraian' => $uraian, 'kode_sk' => $this->kode['sk'], 'kode_iksk' => $this->kode['iksk'], 'kode_rktm' => $this->kode['rktm'],
            'unit' => 'Tim Penjamin Mutu', 'kode_leading' => '1.6', 'pagu_dipa' => 1000000, 'pagu_komite' => 0, 'bulan' => [2, 3], 'kode_edm' => null, 'budaya_mutu' => [],
        ];
        app(MasterKodeRepository::class)->ganti(2026, [
            'rencana_aksi' => [
                $ra('RA.1', $kegiatan['nama_kegiatan']),
                $ra('RA.2', 'Rencana aksi tanpa leading', ['kode_leading' => null, 'kode_rktm' => null, 'unit' => 'Tim Lain']),
                $ra('RA.3', 'Rencana aksi kode lama', ['kode_sk' => 'SK.404', 'kode_iksk' => null]),
            ],
            'relasi_edm_rktm' => [['kode_edm' => $this->kode['edm'], 'kode_rktm' => $this->kode['rktm']], ['kode_edm' => $this->kode['edm2'], 'kode_rktm' => $this->kode['rktm2']]],
        ]);
        session(['stub_role' => 'admin']);
    }

    public function test_tag_efektif_edm_otomatis_kode_asing_dan_pemakaian(): void
    {
        $baris = array_column(RencanaAksi::baris(2026), null, 'kode');

        $this->assertSame([$this->kode['edm']], $baris['RA.1']['tag']['kode_edm']);
        $this->assertTrue($baris['RA.1']['tag']['edm_otomatis']);
        $this->assertSame([], $baris['RA.2']['tag']['kode_edm'], 'Tanpa RKTM tidak ada EDM otomatis.');
        $this->assertSame(['SK.404'], $baris['RA.3']['asing']);
        $this->assertSame(['RA.1' => 1], JenisKode::pemakaian('rencana_aksi', app(KegiatanRepository::class)->semua(), 2026), 'Kegiatan bernama sama.');
        $this->assertSame(3, JenisKode::jumlah(2026)['rencana_aksi']);
        $this->assertSame('1.6', RencanaAksi::cocokkanLeading('tim penjamin  mutu'));
        $this->assertSame('1.5', RencanaAksi::cocokkanLeading('Kepala Tata Usaha'));
        $this->assertNull(RencanaAksi::cocokkanLeading('Tim Manajemen Mutu'));
    }

    public function test_ubah_tag_memeriksa_master_dan_mencatat_riwayat(): void
    {
        $hasil = KelolaRencanaAksi::ubahTag(2026, 'RA.2', [
            'kode_sk' => $this->kode['sk'], 'kode_iksk' => $this->kode['iksk'], 'kode_rktm' => $this->kode['rktm2'], 'kode_leading' => '1.1',
            'kode_edm' => [$this->kode['edm'], $this->kode['edm']], 'budaya_mutu' => ['3', '1'],
        ], self::ADMIN, KelolaRencanaAksi::versi(2026));

        $this->assertTrue($hasil['berubah']);
        $ra = collect(MasterKode::rencanaAksi(2026))->firstWhere('kode', 'RA.2');
        $this->assertSame([$this->kode['rktm2'], '1.1', [$this->kode['edm']], [1, 3]], [$ra['kode_rktm'], $ra['kode_leading'], $ra['kode_edm'], $ra['budaya_mutu']]);
        $riwayat = app(RiwayatPengaturanRepository::class)->semua()[0];
        $this->assertSame('ubah_tag_rencana_aksi', $riwayat['aksi']);
        $this->assertStringContainsString('RA.2 Rencana aksi tanpa leading (TA 2026) — RKTM: — → '.$this->kode['rktm2'], $riwayat['keterangan']);
        $this->assertStringContainsString('EDM: otomatis → '.$this->kode['edm'], $riwayat['keterangan']);

        $this->assertFalse(KelolaRencanaAksi::ubahTag(2026, 'RA.2', ['kode_sk' => $this->kode['sk'], 'kode_iksk' => $this->kode['iksk'], 'kode_rktm' => $this->kode['rktm2'], 'kode_leading' => '1.1', 'kode_edm' => [$this->kode['edm']], 'budaya_mutu' => [1, 3]], self::ADMIN)['berubah']);

        $galat = function (array $isian): array {
            try {
                KelolaRencanaAksi::ubahTag(2026, 'RA.1', $isian, self::ADMIN);
            } catch (ValidationException $e) {
                return array_map(fn (array $p) => $p[0], $e->errors());
            }
            $this->fail('Seharusnya ditolak.');
        };
        $this->assertSame(['kode_sk' => 'SK SK.404 tidak ada di master TA 2026.'], $galat(['kode_sk' => 'SK.404']));
        $this->assertSame(['kode_iksk' => 'Pilih SK terlebih dahulu.'], $galat(['kode_iksk' => $this->kode['iksk']]));
        $this->assertSame(['kode_rktm' => 'RKTM 99.9.9 tidak ada di master TA 2026.'], $galat(['kode_rktm' => '99.9.9']));
        $this->assertSame(['kode_leading' => 'Pilih leading yang aktif.'], $galat(['kode_leading' => '1.9']));
        $this->assertSame(['kode_edm' => 'EDM Z9 tidak ada di master TA 2026.'], $galat(['kode_edm' => ['Z9']]));
        $this->assertSame(['budaya_mutu' => '5 Budaya Mutu nomor 9 tidak ada di master TA 2026.'], $galat(['budaya_mutu' => [9]]));
    }

    public function test_ubah_tag_ditolak_bila_versi_usang_bukan_admin_atau_tahun_terkunci(): void
    {
        $versi = KelolaRencanaAksi::versi(2026);
        KelolaRencanaAksi::ubahTag(2026, 'RA.1', ['kode_leading' => '1.1'], self::ADMIN);
        try {
            KelolaRencanaAksi::ubahTag(2026, 'RA.1', ['kode_leading' => '1.6'], self::ADMIN, $versi);
            $this->fail('Versi usang harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertSame('1.1', MasterKode::rencanaAksi(2026)[0]['kode_leading']);
        }

        $this->assertThrows(fn () => KelolaRencanaAksi::ubahTag(2026, 'RA.1', [], ['role' => 'leading']), AuthorizationException::class);
        $this->assertThrows(fn () => KelolaRencanaAksi::ubahTag(2025, 'RA.1', [], self::ADMIN), AuthorizationException::class);
        $this->assertThrows(fn () => KelolaRencanaAksi::ubahTag(2026, 'RA.99', [], self::ADMIN), \RuntimeException::class);
    }

    public function test_endpoint_tag_dan_daftar_master_kode(): void
    {
        $this->putJson(route('master.rencana_aksi.tag', ['tahun' => 2026, 'kode' => 'RA.2']), ['kode_leading' => '1.3', 'kode_edm' => null])
            ->assertOk()->assertJsonPath('berubah', true)->assertJsonPath('rencana_aksi.kode_leading', '1.3')->assertJsonPath('rencana_aksi.tag.edm_otomatis', true);
        $this->putJson(route('master.rencana_aksi.tag', ['tahun' => 2026, 'kode' => 'RA.2']), ['kode_rktm' => '99.9.9'])
            ->assertUnprocessable()->assertJsonPath('galat.kode_rktm.0', 'RKTM 99.9.9 tidak ada di master TA 2026.');
        $this->putJson(route('master.rencana_aksi.tag', ['tahun' => 2026, 'kode' => 'RA.2']), ['versi' => 'usang'])->assertStatus(409);
        $this->putJson(route('master.rencana_aksi.tag', ['tahun' => 2026, 'kode' => 'RA.77']), [])->assertNotFound();
        $this->withSession(['stub_role' => 'leading'])->putJson(route('master.rencana_aksi.tag', ['tahun' => 2026, 'kode' => 'RA.2']), [])->assertForbidden();

        $this->getJson(route('master.kode', ['jenis' => 'rencana_aksi', 'cari' => 'kode lama']))
            ->assertOk()->assertJsonPath('jumlah', 1)->assertJsonPath('baris.0.kode', 'RA.3')->assertJsonPath('baris.0.asing.0', 'SK.404');
    }

    public function test_tab_rencana_aksi_cari_saring_dan_sunting_tag(): void
    {
        Livewire::test(Daftar::class)
            ->set('jenis', 'rencana_aksi')
            ->assertSee('Rencana aksi tanpa leading')
            ->assertSeeHtml('data-testid="saring-tag"')
            ->set('saringTag', 'tanpa_leading')->assertSee('Rencana aksi tanpa leading')->assertDontSee('Rencana aksi kode lama')
            ->set('saringTag', 'asing')->assertSee('Rencana aksi kode lama')->assertDontSee('Rencana aksi tanpa leading')
            ->set('saringTag', 'semua')->set('cari', 'TANPA leading')->assertSee('Rencana aksi tanpa leading')->assertDontSee('Rencana aksi kode lama')
            ->set('cari', '')
            ->call('ubahTag', 'RA.2')->assertSeeHtml('data-testid="form-tag-ra"')->assertSet('isianTag.edm_otomatis', true)
            ->set('isianTag.kode_sk', $this->kode['sk'])->assertSet('isianTag.kode_iksk', count(MasterKode::kodeIksk($this->kode['sk'], 2026)) === 1 ? $this->kode['iksk'] : '')
            ->set('isianTag.kode_rktm', $this->kode['rktm2'])->assertSet('isianTag.kode_edm', [$this->kode['edm2']])
            ->set('isianTag.kode_leading', '1.9')->call('simpanTag')->assertHasErrors(['isianTag.kode_leading'])
            ->set('isianTag.kode_leading', '1.2')->call('simpanTag')->assertHasNoErrors()->assertSet('tagTerbuka', null)
            ->assertDispatched('notify', message: 'Tag RA.2 tersimpan.');

        $ra = collect(MasterKode::rencanaAksi(2026))->firstWhere('kode', 'RA.2');
        $this->assertSame(['1.2', $this->kode['rktm2'], null], [$ra['kode_leading'], $ra['kode_rktm'], $ra['kode_edm']], 'EDM tetap otomatis.');
    }

    public function test_kode_klasifikasi_mengisi_tag_rencana_aksi_bernama_sama(): void
    {
        session(['stub_role' => 'admin']);
        $komponen = Livewire::test(Klasifikasi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-testid="saran-rencana-aksi"')
            ->assertSeeHtml('data-kode-ra="RA.1"')
            ->call('pakaiTagRencanaAksi', 'RA.1')
            ->assertSet('form.kode_sk', $this->kode['sk'])
            ->assertSet('form.kode_iksk', $this->kode['iksk'])
            ->assertSet('form.kode_rktm', $this->kode['rktm'])
            ->assertSet('form.kode_edm', $this->kode['edm'])
            ->assertSet('form.leading', '1.6');
        $this->assertTrue((bool) $komponen->get('adaPerubahan') || app(KegiatanRepository::class)->temukan(1)['kode_rktm'] === $this->kode['rktm']);
    }

    public function test_salin_master_ikut_menyalin_rencana_aksi_dan_relasi(): void
    {
        $repo = app(MasterKodeRepository::class);
        app(TahunAnggaranRepository::class)->tambah(2027);

        $hasil = SalinMasterKode::salin(2026, 2027, self::ADMIN);

        $this->assertSame(3, $hasil['disalin']['rencana_aksi']);
        $this->assertSame($repo->rencanaAksi(2026), $repo->rencanaAksi(2027));
        $this->assertSame($repo->relasiEdmRktm(2026), $repo->relasiEdmRktm(2027));
    }
}
