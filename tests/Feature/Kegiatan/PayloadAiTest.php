<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\PayloadAi;
use Livewire\Livewire;
use Tests\TestCase;

/** Muatan AI tidak pernah memuat NIP, nama pejabat penanda tangan, maupun pagu (janji di Pengaturan AI). */
class PayloadAiTest extends TestCase
{
    private const KEPALA = 'Dr. H. Ahmad Fauzan, M.Pd.';

    protected function setUp(): void
    {
        parent::setUp();
        app(PejabatRepository::class)->simpan(2026, 'kepala', null, ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => self::KEPALA, 'nip' => '197001011995031001']);

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['konteks' => 'Dibuka '.self::KEPALA.' (NIP 197001011995031001); peserta 54 guru.'] + $repo->temukan(2), 2);
    }

    private function assertBersih(string $muatan): void
    {
        foreach ([self::KEPALA, '197001011995031001', '6.000.000', '2.500.000', 'Rp'] as $terlarang) {
            $this->assertStringNotContainsStringIgnoringCase($terlarang, $muatan);
        }
    }

    public function test_bahan_umum_tanpa_pagu_nip_dan_nama_pejabat(): void
    {
        $bahan = PayloadAi::bahan(app(KegiatanRepository::class)->temukan(2));

        $this->assertStringContainsString('Sumber dana: DIPA · Komite', $bahan);
        $this->assertStringContainsString('Dibuka [nama pejabat disembunyikan] ([NIP disembunyikan]); peserta 54 guru.', $bahan);
        $this->assertBersih($bahan);
        $this->assertSame($bahan, PayloadAi::bersihkan($bahan), 'Pembersihan idempoten.');
        $this->assertSame('Sambutan [nama pejabat disembunyikan].', PayloadAi::bersihkan('Sambutan '.mb_strtoupper(self::KEPALA).'.'));
    }

    public function test_permintaan_saran_dan_usulan_jadwal_dibersihkan(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);

        $saran = PayloadAi::saranKalimat(2, 'tujuan', $kegiatan, 'Disahkan '.self::KEPALA.'.', 'Sebut NIP 197001011995031001.');
        $this->assertSame(['Disahkan [nama pejabat disembunyikan].', 'Sebut [NIP disembunyikan].'], [$saran->teks, $saran->instruksi]);
        $this->assertBersih($saran->bahan.implode(' ', $saran->bahanBagian));

        $usulan = PayloadAi::usulanJadwal(2, $kegiatan, 'umum', 'Libatkan '.self::KEPALA, null);
        $this->assertSame('Libatkan [nama pejabat disembunyikan]', $usulan->instruksi);
        $this->assertBersih($usulan->bahan);
    }

    public function test_riwayat_saran_mencatat_muatan_yang_bersih(): void
    {
        Livewire::test(KalimatAi::class, ['kegiatan' => 2])->call('mintaSaran', 'tujuan')->assertSet('saranTerbuka', 'tujuan');

        $this->assertBersih(app(RiwayatSaranAiRepository::class)->semua()[0]['prompt']);
    }
}
