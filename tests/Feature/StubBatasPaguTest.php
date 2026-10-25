<?php

namespace Tests\Feature;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Livewire\Kegiatan\Rab;
use App\Support\Rab\StatusPagu;
use App\Support\Stub\StubBatasPagu;
use Livewire\Livewire;
use Tests\TestCase;

class StubBatasPaguTest extends TestCase
{
    /** @return array<string, array<string, mixed>> Kondisi berkunci "kegiatan:sumber". */
    private function kondisi(): array
    {
        $kondisi = [];
        foreach (StubBatasPagu::kondisiKegiatan() as $k) {
            $kondisi["{$k['kegiatan_id']}:{$k['sumber']}"] = $k;
        }
        ksort($kondisi);

        return $kondisi;
    }

    public function test_contoh_kegiatan_mencakup_semua_kondisi_pagu(): void
    {
        $kondisi = $this->kondisi();

        $this->assertSame(
            ['1:Komite' => 'sesuai', '2:DIPA' => 'sisa', '2:Komite' => 'sesuai', '3:DIPA' => 'tanpa_pagu', '3:Komite' => 'melebihi'],
            array_map(fn ($k) => $k['status'], array_intersect_key($kondisi, array_flip(['1:Komite', '2:DIPA', '2:Komite', '3:DIPA', '3:Komite']))),
        );

        $melebihi = $kondisi['3:Komite'];
        $this->assertSame(
            ['kegiatan' => 'Review RKJM dan Penyusunan RKTM', 'pagu' => 7_500_000, 'total' => 7_950_000, 'selisih' => -450_000, 'jumlah_item' => 7, 'teks' => 'Melebihi pagu Rp450.000'],
            array_intersect_key($melebihi, array_flip(['kegiatan', 'pagu', 'total', 'selisih', 'jumlah_item', 'teks'])),
        );
        $this->assertSame([null, 900_000, null, 'Pagu belum diisi'], [$kondisi['3:DIPA']['pagu'], $kondisi['3:DIPA']['total'], $kondisi['3:DIPA']['selisih'], $kondisi['3:DIPA']['teks']]);
        $this->assertSame(600_000, $kondisi['2:DIPA']['selisih']);
    }

    public function test_kondisi_mengikuti_rab_dan_kegiatan_terbaru(): void
    {
        $rab = app(RabRepository::class);
        $rab->tambahItem(2, 'DIPA', ['kode' => '521211', 'uraian' => 'Spanduk', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'lembar', 'harga_satuan' => 600_000]);
        $this->assertSame(['sesuai', 0], [$this->kondisi()['2:DIPA']['status'], $this->kondisi()['2:DIPA']['selisih']]);

        $rab->tambahItem(2, 'DIPA', ['kode' => '521211', 'uraian' => 'Map', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => 1]);
        $this->assertSame(['melebihi', 'Melebihi pagu Rp1'], [$this->kondisi()['2:DIPA']['status'], $this->kondisi()['2:DIPA']['teks']]);

        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Belum ber-RAB', 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $this->assertSame(['sisa', 0, 0, 1_000_000], [$this->kondisi()["{$id}:Komite"]['status'], $this->kondisi()["{$id}:Komite"]['total'], $this->kondisi()["{$id}:Komite"]['jumlah_item'], $this->kondisi()["{$id}:Komite"]['selisih']]);
    }

    public function test_kasus_uji_sesuai_status_pagu_dan_fixture_kembaran_js(): void
    {
        $fixture = array_map(fn ($k) => [$k['total'], $k['pagu'], $k['status'], $k['teks']], json_decode(file_get_contents(base_path('tests/fixtures/status-pagu.json')), true));
        $statusTercakup = [];

        foreach (StubBatasPagu::kasusUji() as $k) {
            $hasil = StatusPagu::hitung($k['total'], $k['pagu']);
            $this->assertSame([$k['status'], $k['teks']], [$hasil->status, $hasil->teks()], $k['catatan']);
            $this->assertContains([$k['total'], $k['pagu'], $k['status'], $k['teks']], $fixture, "Kasus \"{$k['catatan']}\" juga diuji untuk \$statusPagu (JS).");
            $statusTercakup[$k['status']] = true;
        }

        $this->assertEqualsCanonicalizing(['tanpa_pagu', 'sesuai', 'sisa', 'melebihi'], array_keys($statusTercakup));
    }

    public function test_contoh_melebihi_pagu_tampil_di_rab_dan_data_kegiatan(): void
    {
        Livewire::test(Rab::class, ['kegiatan' => 3])
            ->assertSet('sumber', 'DIPA')
            ->assertSee('Pagu belum diisi')
            ->call('pilihSumber', 'Komite')
            ->assertSee('Melebihi pagu Rp450.000');

        $daftar = Livewire::test(Daftar::class)->set('statusPagu', 'melebihi');
        $this->assertSame(['Review RKJM dan Penyusunan RKTM'], array_column($daftar->instance()->halamanIni, 'nama'));
    }
}
