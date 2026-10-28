<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\KeamananAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\SaranJadwalAi;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Tests\TestCase;

class KeamananAiTest extends TestCase
{
    public function test_keluaran_ai_dibersihkan(): void
    {
        $this->assertSame(
            "Meningkatkan mutu\nJudul\n\nisi\n\nNIP [NIP disembunyikan] ok",
            KeamananAi::bersihkanKeluaran("<p>**Meningkatkan** mutu</p>\r\n## Judul\n```text\nisi\n```\nNIP 197001011995031001 \u{200B}ok"),
        );
        $this->assertSame('NIP [NIP disembunyikan].', KeamananAi::bersihkanKeluaran('NIP 19700101 199503 1 001.'));
        $this->assertSame('Tahun 2026, 40 peserta, kode 6.17.12 tetap.', KeamananAi::bersihkanKeluaran('Tahun 2026, 40 peserta, kode 6.17.12 tetap.'), 'Angka biasa tidak diubah.');
    }

    public function test_muatan_berisi_data_pribadi_tidak_terkirim(): void
    {
        $this->assertSame([], KeamananAi::piiMuatan('Peserta 40 guru; kontak [nomor telepon disembunyikan].'));
        $this->assertSame(['surel', 'telepon', 'nip'], KeamananAi::piiMuatan('Hubungi guru@example.com / 0812-3456-7890, NIP 197001011995031001.'));

        try {
            KeamananAi::pastikanMuatanAman('Bahan aman.', 'Narahubung: kepala@example.com');
            $this->fail('Muatan berisi surel harus dibatalkan.');
        } catch (GagalUsulanAi $e) {
            $this->assertStringStartsWith('Bahan AI masih memuat alamat surel — permintaan dibatalkan', $e->getMessage());
        }
    }

    public function test_saran_kalimat_dibersihkan_dan_diberi_peringatan_batas_aman(): void
    {
        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
            {
                return [
                    ['judul' => '**Ringkas**', 'ciri' => '<b>singkat</b>', 'teks' => '<p>Meningkatkan **mutu** pembelajaran bagi 999 guru dengan anggaran Rp88.888.888.</p>'],
                    ['judul' => 'Lengkap', 'ciri' => 'rinci', 'teks' => 'Meningkatkan mutu pembelajaran madrasah.'],
                ];
            }
        });
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $hasil = SaranKalimatAi::minta($kegiatan, 'tujuan', StubData::currentUser());

        $this->assertSame(['Ringkas', 'singkat', 'Meningkatkan mutu pembelajaran bagi 999 guru dengan anggaran Rp88.888.888.'], [$hasil['versi'][0]['judul'], $hasil['versi'][0]['ciri'], $hasil['versi'][0]['teks']]);
        $this->assertCount(2, $hasil['peringatan']);
        $this->assertNotEmpty($hasil['peringatan'][0], 'Angka yang tidak ada di data kegiatan diberi peringatan.');
        $this->assertSame([], $hasil['peringatan'][1]);
        $tersimpan = collect(app(RiwayatSaranAiRepository::class)->semua())->firstWhere('id', $hasil['permintaan']);
        $this->assertSame(array_column($hasil['versi'], 'teks'), array_column($tersimpan['versi'], 'teks'), 'Riwayat menyimpan teks yang sudah dibersihkan.');
    }

    public function test_usulan_jadwal_dibersihkan(): void
    {
        $versi = SaranJadwalAi::rapikan([['ciri' => '**cepat**', 'baris' => [['uraian' => '<i>Rapat</i> persiapan `panitia`', 'mulai' => 1, 'sampai' => 2]]]]);
        $this->assertSame(['cepat', 'Rapat persiapan panitia'], [$versi[0]['ciri'], $versi[0]['baris'][0]['uraian']]);
    }
}
