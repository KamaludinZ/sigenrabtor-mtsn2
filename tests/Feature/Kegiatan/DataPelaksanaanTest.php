<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\SaranPelaksanaan;
use App\Support\Stub\StubData;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class DataPelaksanaanTest extends TestCase
{
    public function test_isian_dinormalkan_dan_nilai_tidak_sah_dianggap_kosong(): void
    {
        $data = DataPelaksanaan::dari([
            'tanggal_mulai' => '2026-07-14',
            'lama_hari' => '3',
            'bulan_pelaksanaan' => [9, '7', 7, 13, 0],
            'tempat' => '  Aula Madrasah ',
            'peserta_jumlah' => '56',
            'peserta_jenis' => ' Guru ',
            'uraian_pelaksanaan' => ' workshop. ',
        ]);

        $this->assertSame(['2026-07-14', 3, [7, 9], 'Aula Madrasah', 56, 'Guru', 'workshop.'],
            [$data->mulai->toDateString(), $data->lamaHari, $data->bulan, $data->tempat, $data->pesertaJumlah, $data->pesertaJenis, $data->uraian]);

        $rusak = DataPelaksanaan::dari(['tanggal_mulai' => 'bukan-tanggal', 'lama_hari' => '0', 'peserta_jumlah' => '-5', 'bulan_pelaksanaan' => 'x']);
        $this->assertSame([null, null, null, []], [$rusak->mulai, $rusak->lamaHari, $rusak->pesertaJumlah, $rusak->bulan]);
        $this->assertNull(DataPelaksanaan::dari(['tanggal_mulai' => '2026-07-14', 'lama_hari' => 367])->selesai(), 'Lama di atas 366 hari tidak dihitung.');
        $this->assertEquals(new DataPelaksanaan, DataPelaksanaan::dari([]));
    }

    public function test_turunan_jadwal_dengan_hari_kerja(): void
    {
        // Jumat 17 Juli, 3 hari kerja → Selasa 21 Juli; batas laporan +10 hari.
        $data = DataPelaksanaan::dari(['tanggal_mulai' => '2026-07-17', 'lama_hari' => 3, 'hari_kerja' => true, 'bulan_pelaksanaan' => [7]]);

        $this->assertSame('2026-07-21', $data->selesai()->toDateString());
        $this->assertSame([7], $data->bulanDariTanggal());
        $this->assertSame('2026-07-31', $data->batasLaporanBaku()->toDateString());
        $this->assertSame('Juli 2026', $data->teksBulan(2026));

        $belum = DataPelaksanaan::dari(['tanggal_mulai' => '2026-07-17']);
        $this->assertSame([null, null, null, null], [$belum->selesai(), $belum->bulanDariTanggal(), $belum->batasLaporanBaku(), $belum->teksBulan(2026)]);
    }

    public function test_kelengkapan_per_kelompok(): void
    {
        $this->assertSame(['waktu' => 'kosong', 'tempat' => 'kosong', 'bentuk' => 'opsional'], (new DataPelaksanaan)->kelengkapan());

        $sebagian = DataPelaksanaan::dari(['tanggal_mulai' => '2026-07-14', 'lama_hari' => '0', 'tempat' => 'Aula', 'peserta_jumlah' => 10, 'peserta_jenis' => 'Guru']);
        $this->assertSame(['waktu' => 'sebagian', 'tempat' => 'lengkap', 'bentuk' => 'opsional'], $sebagian->kelengkapan(), 'Lama 0 hari belum dihitung terisi.');
        $this->assertSame(DataPelaksanaan::WAJIB, array_keys($sebagian->terisi()));
    }

    public function test_form_pratinjau_dan_kak_memakai_state_yang_sama(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $data = DataPelaksanaan::dari($kegiatan);
        $nama = $kegiatan['nama_kegiatan'];

        $form = Livewire::test(Form::class, ['kegiatan' => 2])->instance()->form;
        $this->assertEquals($data, $form->pelaksanaan());
        $this->assertSame('lengkap', $form->kelengkapan()['pelaksanaan']);

        // Bagian 4b & 5 KAK/TOR = kalimat dari DataPelaksanaan yang sama dengan pratinjau di form.
        $teks = collect(SusunanKak::dari($kegiatan)->bagian)->whereIn('nomor', [4, 5])->flatMap(fn ($b) => $b['isi'])->pluck('teks')->filter()->all();
        $this->assertContains($data->kalimatPelaksanaan($nama), $teks);
        $this->assertContains($data->kalimatTempat($nama), $teks);
        $this->assertStringContainsString('diikuti oleh 54 orang peserta (Guru)', $data->kalimatPelaksanaan($nama));

        Livewire::test(Form::class, ['kegiatan' => 2])
            ->assertSee($data->kalimatPelaksanaan($nama))
            ->assertSee($data->kalimatTempat($nama));
    }

    public function test_riwayat_isian_terbaru_lebih_dulu_tanpa_duplikat(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['nama_kegiatan' => 'Lomba', 'tempat' => 'Lapangan Madrasah', 'peserta_jenis' => 'Siswa']);
        $this->travel(1)->minutes();
        $repo->simpan(['nama_kegiatan' => 'Rapat', 'tempat' => ' aula madrasah ', 'peserta_jenis' => '']);

        $tempat = $repo->riwayatIsian('tempat');
        $this->assertSame(['aula madrasah', 'Lapangan Madrasah'], array_slice($tempat, 0, 2));
        $this->assertCount(count(array_unique(array_map('mb_strtolower', $tempat))), $tempat);
        $this->assertNotContains('', $repo->riwayatIsian('peserta_jenis'));
        $this->assertCount(1, $repo->riwayatIsian('tempat', 1));

        $this->expectException(InvalidArgumentException::class);
        $repo->riwayatIsian('nama_kegiatan');
    }

    public function test_saran_menggabungkan_riwayat_dan_baku(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['nama_kegiatan' => 'Rapat', 'tempat' => 'Gedung Serbaguna Kota Malang', 'peserta_jenis' => 'guru']);

        $saran = SaranPelaksanaan::untuk($repo);
        $this->assertSame(['nilai' => 'Gedung Serbaguna Kota Malang', 'riwayat' => true], $saran['tempat'][0]);

        // "guru" dari riwayat menggantikan "Guru" baku (tidak muncul dua kali); saran baku lain tetap ada.
        $peserta = array_column($saran['peserta'], 'riwayat', 'nilai');
        $this->assertSame(true, $peserta['guru']);
        $this->assertArrayNotHasKey('Guru', $peserta);
        $this->assertSame(false, $peserta['Siswa kelas IX']);
        $this->assertCount(count(StubData::saranPelaksanaan()['tempat']) + 2, $saran['tempat'], 'Riwayat baru + tempat kegiatan contoh 2 + saran baku.');

        $html = Livewire::test(Form::class)->html();
        $this->assertMatchesRegularExpression('/<option value="Gedung Serbaguna Kota Malang"\s+label="Pernah dipakai"\s*>/', $html);
        $this->assertMatchesRegularExpression('/<option value="Laboratorium IPA"\s*>/', $html);
    }
}
