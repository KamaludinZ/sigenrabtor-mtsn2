<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\KelengkapanDokumen;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Dokumen\PenandaKosong;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\ValidasiKelengkapan;
use InvalidArgumentException;
use Tests\TestCase;

class ValidasiKelengkapanTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    public function test_kelengkapan_per_bagian_set_lengkap(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $hasil = ValidasiKelengkapan::periksa($kegiatan, null, self::LEADING);
        $kak = SusunanKak::dari($kegiatan);
        $rab = SusunanRab::dari($kegiatan);
        $kelengkapan = KelengkapanDokumen::dari($kak, $rab, $kegiatan, self::LEADING);

        $this->assertSame(['cover', ...array_map(fn (int $n) => "kak-{$n}", range(1, 9)), 'rab'], array_column($hasil['bagian'], 'kunci'));
        $this->assertSame(array_column($kelengkapan->bagian, 'status'), array_column($hasil['bagian'], 'status'));
        $this->assertSame([$kelengkapan->persen(), $kelengkapan->jumlah('lengkap'), 11], [$hasil['persen'], $hasil['jumlah']['lengkap'], $hasil['jumlah']['bagian']]);
        $this->assertSame(count(PenandaKosong::daftar($kak, $rab)), array_sum(array_map(fn (array $b) => count($b['penanda']), $hasil['bagian'])), 'Setiap penanda kosong masuk ke bagiannya.');
        $this->assertSame([null, 5], [$hasil['bagian'][0]['nomor'], $hasil['bagian'][5]['nomor']]);
        $this->assertSame(['tempat'], array_column($hasil['bagian'][5]['sunting'], 'kunci'));
        $this->assertSame('Data Kegiatan › Pelaksanaan', $hasil['bagian'][5]['asal'][0]['label']);

        $cetak = PemeriksaanCetak::periksa($kegiatan, 'lengkap', 'Komite');
        $this->assertSame([$cetak['siap'], $cetak['draf'], $cetak['nama_berkas']], [$hasil['cetak']['siap'], $hasil['cetak']['draf'], $hasil['cetak']['nama_berkas']]);
        $this->assertSame(['berlaku' => true, 'siap' => true, 'halangan' => []], $hasil['ajukan'], 'Contoh 1 siap diajukan.');
    }

    public function test_penanda_wajib_jenis_dan_syarat_ajukan(): void
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Koordinasi Guru', 'leading' => '1.6', 'user_id' => 1, 'status' => 'draf']);
        $hasil = ValidasiKelengkapan::periksa($repo->temukan($id), null, self::LEADING, 'KAK+TOR');

        $this->assertSame(JenisDokumen::ISI['KAK+TOR'], array_values(array_unique(array_column($hasil['bagian'], 'dokumen'))));
        $tempat = collect($hasil['bagian'])->firstWhere('kunci', 'kak-5');
        $this->assertSame('kurang', $tempat['status']);
        $wajib = collect($tempat['penanda'])->firstWhere('tingkat', 'wajib');
        $this->assertSame(['Wajib sebelum diajukan', 'Data Kegiatan › Pelaksanaan', ['kunci' => 'tempat', 'label' => 'Tempat pelaksanaan']], [$wajib['label_tingkat'], $wajib['tempat']['label'], $wajib['sunting']]);
        $this->assertStringContainsString("/kegiatan/{$id}/edit#pelaksanaan", $wajib['tempat']['tautan']);
        $this->assertGreaterThan(0, $hasil['jumlah']['wajib']);
        $this->assertFalse($hasil['lengkap']);
        $this->assertFalse($hasil['ajukan']['siap']);
        $this->assertContains(KegiatanForm::PELAKSANAAN_WAJIB['tempat'], $hasil['ajukan']['halangan']);

        $tanpaNama = $repo->simpan(['nama_kegiatan' => '', 'leading' => '1.6', 'user_id' => 1, 'status' => 'draf']);
        $cover = ValidasiKelengkapan::bagian($repo->temukan($tanpaNama), null, 'cover', self::LEADING);
        $this->assertSame(['kurang', 1, 'wajib'], [$cover['status'], $cover['wajib'], $cover['penanda'][0]['tingkat']]);

        $this->assertSame(['rab'], array_column(ValidasiKelengkapan::periksa($repo->temukan(2), 'DIPA', self::LEADING, 'RAB')['bagian'], 'kunci'));
        $this->assertStringContainsString('melebihi pagu', implode(' ', ValidasiKelengkapan::periksa($repo->temukan(3), null, self::LEADING)['ajukan']['halangan']));

        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);
        $this->assertSame(['berlaku' => false, 'siap' => false, 'halangan' => []], ValidasiKelengkapan::periksa($repo->temukan(1), null, self::LEADING)['ajukan']);

        foreach ([
            fn () => ValidasiKelengkapan::periksa($repo->temukan(1), null, self::LEADING, 'Surat'),
            fn () => ValidasiKelengkapan::periksa($repo->temukan(1), 'DIPA', self::LEADING),
            fn () => ValidasiKelengkapan::bagian($repo->temukan(1), null, 'kak-10', self::LEADING),
        ] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
            }
        }
    }

    public function test_endpoint_kelengkapan(): void
    {
        $this->getJson(route('kegiatan.kelengkapan', 1))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('jenis', 'lengkap')->assertJsonCount(11, 'bagian')
            ->assertJsonStructure(['persen', 'jumlah' => ['lengkap', 'kurang', 'periksa', 'bagian', 'wajib'], 'ajukan', 'cetak']);
        $this->getJson(route('kegiatan.kelengkapan', ['kegiatan' => 1, 'bagian' => 'kak-5']))->assertOk()->assertJsonPath('kunci', 'kak-5');
        $this->getJson(route('kegiatan.kelengkapan', ['kegiatan' => 2, 'jenis' => 'RAB', 'sumber' => 'DIPA']))->assertOk()->assertJsonPath('sumber', 'DIPA')->assertJsonCount(1, 'bagian');
        $this->getJson(route('kegiatan.kelengkapan', ['kegiatan' => 1, 'bagian' => 'lampiran']))->assertNotFound();
        $this->getJson(route('kegiatan.kelengkapan', ['kegiatan' => 1, 'jenis' => 'Surat']))->assertNotFound();
        $this->getJson(route('kegiatan.kelengkapan', ['kegiatan' => 1, 'sumber' => 'DIPA']))->assertNotFound();
        $this->getJson(route('kegiatan.kelengkapan', 999))->assertNotFound();
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->getJson(route('kegiatan.kelengkapan', $lain))->assertForbidden();
    }
}
