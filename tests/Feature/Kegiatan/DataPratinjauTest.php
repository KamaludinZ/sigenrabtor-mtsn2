<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\PembuatDokumen;
use App\Support\Dokumen\PenampilDokumen;
use App\Support\Dokumen\PenandaKosong;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
use App\Support\Kegiatan\UbahDataKegiatan;
use InvalidArgumentException;
use Tests\TestCase;

class DataPratinjauTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    public function test_data_pratinjau_dari_jalur_yang_sama_dengan_tab_pratinjau(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $data = DataPratinjau::untuk($kegiatan, null, self::LEADING);
        $kak = SusunanKak::dari($kegiatan);
        $rab = SusunanRab::dari($kegiatan);
        $cetak = StatusCetak::dari($kegiatan, null, $kak, $rab);

        $this->assertSame([1, 'Komite', ['Komite'], ['cover', 'kak', 'rab'], true, null], [$data['kegiatan_id'], $data['sumber'], $data['daftar_sumber'], $data['lembar'], $data['dapat_diubah'], $data['alasan_terkunci']]);
        $this->assertSame(UbahDataKegiatan::versi($kegiatan), $data['versi']);
        $this->assertSame([CetakDokumen::namaBerkas($cetak, ['cover', 'kak', 'rab']), $cetak->set->draf, $cetak->keadaan()], [$data['cetak']['nama_berkas'], $data['cetak']['draf'], $data['cetak']['keadaan']]);
        $this->assertSame(PenampilDokumen::isi($kak, $rab), $data['daftar_isi']);

        $bagian = $data['dokumen']['kak']['bagian'];
        $this->assertSame(range(1, 9), array_column($bagian, 'nomor'));
        foreach ($bagian as $b) {
            $this->assertSame(SuntingDokumen::untukBagian($b['nomor']), array_column($b['sunting'], 'kunci'));
        }
        $tempat = collect($bagian[4]['sunting'])->firstWhere('kunci', 'tempat');
        $this->assertSame(['Tempat pelaksanaan', 'teks', 255, $kegiatan['tempat'], true], [$tempat['label'], $tempat['jenis'], $tempat['maks'], $tempat['teks'], $tempat['tampil']]);
        $this->assertSame(array_values($kak->bagian[0]['isi']), $bagian[0]['isi'], 'Blok isi SusunanKak apa adanya (penunjuk sunting ikut).');
        $this->assertSame([$cetak->kak->draf, array_column($cetak->kak->alasan, 'teks')], [$data['dokumen']['kak']['draf'], $data['dokumen']['kak']['alasan']]);
        $this->assertSame(PembuatDokumen::rab($rab) + ['draf' => $cetak->rab->draf, 'alasan' => array_column($cetak->rab->alasan, 'teks')], $data['dokumen']['rab']);
        $this->assertSame([TeksCover::nilai($kegiatan, 'Komite'), $cetak->set->draf], [$data['dokumen']['cover']['nilai'], $data['dokumen']['cover']['draf']], 'Cover mengikuti cap set.');

        $penanda = PenandaKosong::daftar($kak, $rab);
        $this->assertSame(['ringkasan' => PenandaKosong::ringkasan($penanda), 'daftar' => $penanda], $data['penanda_kosong']);
        $this->assertSame(count($data['kelengkapan']['bagian']), array_sum($data['kelengkapan']['jumlah']));
        $this->assertSame(11, count($data['kelengkapan']['bagian']), 'Cover, sembilan bagian, RAB.');
        $this->assertSame(KaidahDokumen::ringkasan($data['kaidah']['aturan']), $data['kaidah']['ringkasan']);
        $this->assertSame([], $data['riwayat_sunting']);
    }

    public function test_lembar_sumber_status_dan_jejak_sunting(): void
    {
        $repo = app(KegiatanRepository::class);
        $hanyaKak = DataPratinjau::untuk($repo->temukan(2), 'DIPA', self::LEADING, 'kak');
        $this->assertSame(['DIPA', ['kak'], null, null], [$hanyaKak['sumber'], $hanyaKak['lembar'], $hanyaKak['dokumen']['cover'], $hanyaKak['dokumen']['rab']]);
        $this->assertSame(['kak'], array_values(array_unique(array_column($hanyaKak['daftar_isi'], 'lembar'))));
        $this->assertStringStartsWith('KAK-TOR_', $hanyaKak['cetak']['nama_berkas']);

        try {
            DataPratinjau::untuk($repo->temukan(1), 'DIPA', self::LEADING);
            $this->fail('Sumber yang tidak dipilih harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Sumber dana DIPA tidak dipilih untuk kegiatan ini.', $e->getMessage());
        }

        SuntingDokumen::simpan(1, $repo->temukan(1), 'tempat', 'Aula Utama MTsN 2 Kota Malang', self::LEADING, null);
        $data = DataPratinjau::untuk($repo->temukan(1), null, self::LEADING);
        $this->assertSame(['tempat', 'diubah', 'Aula Utama MTsN 2 Kota Malang'], [$data['riwayat_sunting'][0]['bidang'], $data['riwayat_sunting'][0]['keterangan'], $data['riwayat_sunting'][0]['teks_baru']]);
        $this->assertSame('Aula Utama MTsN 2 Kota Malang', collect($data['dokumen']['kak']['bagian'][4]['sunting'])->firstWhere('kunci', 'tempat')['teks']);

        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);
        $terkunci = DataPratinjau::untuk($repo->temukan(1), null, self::LEADING);
        $this->assertSame([false, 'diperiksa'], [$terkunci['dapat_diubah'], $terkunci['status']]);
        $this->assertNotNull($terkunci['alasan_terkunci']);
    }

    public function test_endpoint_data_pratinjau(): void
    {
        $this->getJson(route('kegiatan.pratinjau.data', 1))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sumber', 'Komite')
            ->assertJsonCount(9, 'dokumen.kak.bagian')
            ->assertJsonStructure(['cetak' => ['nama_berkas', 'tautan', 'alasan_draf'], 'daftar_isi', 'penanda_kosong' => ['ringkasan', 'daftar'], 'kelengkapan' => ['persen', 'jumlah', 'bagian'], 'kaidah', 'riwayat_sunting', 'versi']);
        $this->getJson(route('kegiatan.pratinjau.data', ['kegiatan' => 2, 'sumber' => 'DIPA', 'dokumen' => 'rab']))
            ->assertOk()->assertJsonPath('sumber', 'DIPA')->assertJsonPath('lembar', ['rab'])->assertJsonPath('dokumen.kak', null);
        $this->getJson(route('kegiatan.pratinjau.data', ['kegiatan' => 1, 'sumber' => 'DIPA']))->assertNotFound();
        $this->getJson(route('kegiatan.pratinjau.data', 999))->assertNotFound();

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 3, 'status' => 'draf']);
        $this->getJson(route('kegiatan.pratinjau.data', $lain))->assertForbidden();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('kegiatan.pratinjau.data', $lain))->assertOk();
    }
}
