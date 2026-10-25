<?php

namespace Tests\Feature\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\KalkulatorRab;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StrukturAnggaranSumberTest extends TestCase
{
    private function repo(): RabRepository
    {
        return app(RabRepository::class);
    }

    public function test_contoh_kegiatan_dua_sumber_punya_rab_terpisah(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $anggaran = AnggaranKegiatan::untuk($kegiatan);

        $this->assertSame(['DIPA', 'Komite'], array_map(fn (AnggaranSumber $a) => $a->sumber, $anggaran));

        [$dipa, $komite] = $anggaran;
        $this->assertSame([5_400_000, 6_000_000, 'sisa', 6], [$dipa->total(), $dipa->pagu(), $dipa->statusPagu(), $dipa->jumlahItem()]);
        $this->assertSame([2_500_000, 2_500_000, 'sesuai', 3], [$komite->total(), $komite->pagu(), $komite->statusPagu(), $komite->jumlahItem()]);
        $this->assertSame('Lima Juta Empat Ratus Ribu Rupiah', $dipa->terbilang());
        $this->assertSame(['total' => 7_900_000, 'pagu' => 8_500_000, 'jumlah_item' => 9], AnggaranKegiatan::gabungan($anggaran));
    }

    public function test_termin_contoh_tersimpan_per_sumber_dan_harus_pas_total(): void
    {
        $komite = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(1))['Komite'];

        $this->assertSame([['tanggal' => '2026-06-17', 'nominal' => 7_680_000], ['tanggal' => '2026-06-19', 'nominal' => 2_320_000]], $komite->termin);
        $this->assertTrue($komite->terminSesuai());

        $this->repo()->simpanTermin(1, 'Komite', [['tanggal' => '2026-06-17', 'nominal' => '9000000']]);
        $komite = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan(1))['Komite'];
        $this->assertSame(1_000_000, $komite->selisihTermin());
        $this->assertFalse($komite->terminSesuai());

        // Termin dan rincian satu sumber tidak saling menimpa.
        $this->assertCount(15, $this->repo()->rincian(1, 'Komite'));
    }

    public function test_tanpa_termin_dianggap_sesuai(): void
    {
        $anggaran = new AnggaranSumber('DIPA', KalkulatorRab::hitung([['volume' => 2, 'harga_satuan' => 50_000]], 100_000));

        $this->assertFalse($anggaran->adaTermin());
        $this->assertTrue($anggaran->terminSesuai());
        $this->assertSame('sesuai', $anggaran->statusPagu());
    }

    public function test_rab_sumber_yang_dilepas_tidak_hilang_diam_diam(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(2);
        $kegiatan['sumber_dana'] = [['sumber' => 'Komite', 'pagu' => 2_500_000]];
        $repo->simpan($kegiatan, 2);

        $this->assertSame(['Komite'], array_map(fn ($a) => $a->sumber, AnggaranKegiatan::untuk($repo->temukan(2))));

        $semua = AnggaranKegiatan::untuk($repo->temukan(2), sertakanTerlepas: true);
        $this->assertSame(['DIPA', 'Komite'], array_map(fn ($a) => $a->sumber, $semua));
        $this->assertFalse($semua[0]->dipilih);
        $this->assertNull($semua[0]->pagu());
        $this->assertSame(5_400_000, $semua[0]->total());
        $this->assertSame(['total' => 2_500_000, 'pagu' => 2_500_000, 'jumlah_item' => 3], AnggaranKegiatan::gabungan($semua));
    }

    public function test_sumber_tersimpan_dan_kegiatan_baru_tanpa_rab(): void
    {
        $this->assertSame(['DIPA', 'Komite'], $this->repo()->sumberTersimpan(2));

        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Baru', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => null]]]);
        $this->assertSame([], $this->repo()->sumberTersimpan($id));

        $baru = AnggaranKegiatan::untuk(app(KegiatanRepository::class)->temukan($id));
        $this->assertSame([0, null, 'tanpa_pagu', null], [$baru[0]->total(), $baru[0]->pagu(), $baru[0]->statusPagu(), $baru[0]->terbilang()]);
    }

    public function test_berkas_format_lama_tetap_terbaca_lalu_ditulis_format_baru(): void
    {
        $berkas = config('sigenrabtor.stub.rab_file');
        Storage::disk('local')->put($berkas, json_encode(['5' => ['DIPA' => [
            ['id' => 'a1', 'kode' => '', 'uraian' => 'ATK', 'kategori' => 'bahan', 'volume' => 2, 'satuan' => 'paket', 'harga_satuan' => 50_000],
        ]]]));

        $this->assertSame('ATK', $this->repo()->rincian(5, 'DIPA')[0]['uraian']);
        $this->assertSame([], $this->repo()->termin(5, 'DIPA'));

        $this->repo()->tambahItem(5, 'DIPA', ['kode' => '', 'uraian' => 'Konsumsi', 'kategori' => 'bahan', 'volume' => 1, 'satuan' => 'kotak', 'harga_satuan' => 25_000]);
        $isi = json_decode(Storage::disk('local')->get($berkas), true);

        $this->assertSame(['rincian', 'termin'], array_keys($isi['5']['DIPA']));
        $this->assertSame(['ATK', 'Konsumsi'], array_column($isi['5']['DIPA']['rincian'], 'uraian'));
    }
}
