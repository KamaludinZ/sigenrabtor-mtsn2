<?php

namespace Tests\Feature\Kegiatan;

use App\Enums\StatusKegiatan;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Stub\StubData;
use App\Support\Stub\StubKegiatanRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class EloquentKegiatanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentKegiatanRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentKegiatanRepository;
    }

    /** @return array<string, mixed> */
    private static function isian(array $contoh): array
    {
        return Arr::except($contoh, ['id', 'status', 'dibuat_pada', 'diperbarui_pada']);
    }

    public function test_bentuk_data_sama_dengan_tiruan(): void
    {
        $tiruan = new StubKegiatanRepository;
        // Tautan Data Pagu wajib menunjuk data pagu yang ada (FK activities.data_pagu_id).
        DB::table('data_pagu')->insert(['id' => '0b5c2a7e-3a8f-4c1d-9e2b-6f1a2d3c4b5a', 'tahun_anggaran' => 2026, 'nama_kegiatan' => 'Data pagu uji']);
        foreach (StubKegiatanRepository::contohSemua() as $contoh) {
            $data = self::isian($contoh) + [
                'status' => 'diperiksa',
                'riwayat_status' => [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, StubData::currentUser(), 'Mohon diperiksa.')],
                'kode_hilang' => ['kode_edm' => ['kode' => 'Z9', 'asal' => 'impor', 'tahun' => 2026]],
                'riwayat_kode' => [['pada' => '2026-01-03T08:00:00+07:00', 'oleh' => 'Operator', 'perubahan' => ['SK.9 → SK.10']]],
                'teks_khusus' => ['persiapan' => ['Rapat persiapan panitia {nama_kegiatan}.'], 'pengarah' => 'Pengarah kegiatan {nama_kegiatan} adalah Kepala Madrasah.'],
                'asal' => 'impor_perkin',
                'data_pagu_id' => '0b5c2a7e-3a8f-4c1d-9e2b-6f1a2d3c4b5a',
            ];

            $harapan = Arr::except($tiruan->temukan($tiruan->simpan($data)), ['id', 'dibuat_pada', 'diperbarui_pada']);
            $hasil = $this->repo->temukan($this->repo->simpan($data));

            foreach ($harapan as $kunci => $nilai) {
                $this->assertSame($nilai, $hasil[$kunci], "Kunci {$kunci} kegiatan {$contoh['id']}");
            }
            $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}\+07:00$/', $hasil['diperbarui_pada']);
        }
    }

    public function test_pembuat_dan_tahun_dicatat_sekali(): void
    {
        $id = $this->repo->simpan(['nama_kegiatan' => 'Rapat Kerja']);
        $k = $this->repo->temukan($id);
        $this->assertSame(
            [StubData::currentUser()['id'], StubData::tahunAktif()['tahun'], 'draf', 'manual', true, [], [], []],
            [$k['user_id'], $k['tahun_anggaran'], $k['status'], $k['asal'], $k['laporan_otomatis'], $k['bulan_pelaksanaan'], $k['sumber_dana'], $k['riwayat_status']],
        );

        $this->repo->simpan(['nama_kegiatan' => 'Rapat Kerja Tahunan', 'user_id' => 99, 'tahun_anggaran' => 2020], $id);
        $k = $this->repo->temukan($id);
        $this->assertSame(['Rapat Kerja Tahunan', StubData::currentUser()['id'], StubData::tahunAktif()['tahun']], [$k['nama_kegiatan'], $k['user_id'], $k['tahun_anggaran']]);

        // Id yang tidak ada = kegiatan baru (seperti tiruan).
        $this->assertNotSame(999, $this->repo->simpan(['nama_kegiatan' => 'Kegiatan Lain'], 999));
        $this->assertNull($this->repo->temukan(999));
    }

    public function test_hanya_kunci_yang_dikirim_yang_berubah(): void
    {
        $sumber = $this->repo->simpan(['nama_kegiatan' => 'Workshop 2025', 'tahun_anggaran' => 2025]);
        $id = $this->repo->simpan(self::isian(StubKegiatanRepository::contohDuaSumber()) + [
            'asal' => 'impor_excel',
            'duplikat_dari' => ['id' => $sumber, 'tahun' => 2025],
            'riwayat_kode' => [['pada' => '2026-01-05T09:00:00+07:00', 'oleh' => 'Operator', 'perubahan' => []]],
        ]);

        $this->repo->simpan(['nama_kegiatan' => 'Supervisi Akademik'], $id);
        $k = $this->repo->temukan($id);
        $this->assertSame(['Supervisi Akademik', 'impor_excel', ['id' => $sumber, 'tahun' => 2025], 2, 2], [$k['nama_kegiatan'], $k['asal'], $k['duplikat_dari'], count($k['sumber_dana']), count($k['panitia'])]);
        $this->assertSame('Guru', $k['peserta_jenis']);

        // Sumber dana & panitia dikirim → diganti seluruhnya; sumber yang tidak dipilih lagi hanya ditandai (RAB-nya tetap).
        $this->repo->simpan(['sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]], 'panitia' => [['nama' => 'Siti Aminah, S.Pd.', 'jabatan' => '', 'peran' => 'Ketua']]], $id);
        $k = $this->repo->temukan($id);
        $this->assertSame([['sumber' => 'Komite', 'pagu' => 3_000_000]], $k['sumber_dana']);
        $this->assertSame([['nama' => 'Siti Aminah, S.Pd.', 'jabatan' => '', 'peran' => 'Ketua']], $k['panitia']);
        $this->assertSame([['DIPA', false, null], ['Komite', true, 3_000_000]], DB::table('anggaran_kegiatan')->where('activity_id', $id)->orderBy('sumber_dana')->get()->map(fn ($a) => [$a->sumber_dana, $a->dipilih, $a->pagu])->all());

        // Dipilih lagi: baris yang sama dipakai kembali.
        $this->repo->simpan(['sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 3_000_000]]], $id);
        $this->assertSame([['sumber' => 'DIPA', 'pagu' => 1_000_000], ['sumber' => 'Komite', 'pagu' => 3_000_000]], $this->repo->temukan($id)['sumber_dana']);
        $this->assertSame(2, DB::table('anggaran_kegiatan')->where('activity_id', $id)->count());
    }

    public function test_riwayat_hanya_ditambah_dan_jejak_kunci_final(): void
    {
        $pengguna = StubData::currentUser();
        $dibuat = RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, $pengguna);
        $ajukan = RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, $pengguna);
        $id = $this->repo->simpan(['nama_kegiatan' => 'Rapat Kerja', 'riwayat_status' => [$dibuat]]);

        $this->repo->simpan(['status' => 'diperiksa', 'riwayat_status' => [$dibuat, $ajukan]], $id);
        $this->repo->simpan(['riwayat_status' => [$dibuat]], $id);
        $this->assertSame([$dibuat, $ajukan], $this->repo->temukan($id)['riwayat_status']);

        $finalkan = RiwayatStatus::entri('finalkan', StatusKegiatan::Final, $pengguna);
        $this->repo->simpan(['status' => 'final', 'riwayat_status' => [$dibuat, $ajukan, $finalkan]], $id);
        $baris = DB::table('activities')->find($id);
        $this->assertSame([$pengguna['id'], $finalkan['pada']], [$baris->dikunci_oleh, Carbon::parse($baris->dikunci_pada)->setTimezone(config('app.timezone'))->toIso8601String()]);

        // Buka kunci (catatan & sidik ikut tersimpan) → jejak kunci dilepas.
        $buka = RiwayatStatus::entri('buka_kunci', StatusKegiatan::Dikembalikan, $pengguna, 'Salah tanggal.') + ['sidik' => ['identitas' => 'abc']];
        $this->repo->simpan(['status' => 'dikembalikan', 'riwayat_status' => [$dibuat, $ajukan, $finalkan, $buka]], $id);
        $this->assertSame($buka, $this->repo->temukan($id)['riwayat_status'][3]);
        $this->assertNull(DB::table('activities')->find($id)->dikunci_pada);
    }

    public function test_urutan_daftar_dan_riwayat_isian(): void
    {
        $a = $this->repo->simpan(['nama_kegiatan' => 'Kegiatan A', 'tempat' => 'Aula Madrasah', 'peserta_jenis' => 'Guru']);
        $b = $this->repo->simpan(['nama_kegiatan' => 'Kegiatan B', 'tempat' => ' aula madrasah ', 'peserta_jenis' => '']);
        $c = $this->repo->simpan(['nama_kegiatan' => 'Kegiatan C', 'tempat' => 'Ruang Rapat']);
        $versi = $this->repo->temukan($a)['diperbarui_pada'];
        $this->repo->simpan(['tempat' => 'Lab Komputer'], $a);

        $this->assertSame([$a, $c, $b], array_column($this->repo->semua(), 'id'));
        $this->assertNotSame($versi, $this->repo->temukan($a)['diperbarui_pada']);
        $this->assertSame([['id', 'nama_kegiatan', 'status', 'diperbarui_pada'], 2], [array_keys($this->repo->terbaru(2)[0]), count($this->repo->terbaru(2))]);
        $this->assertSame(['Lab Komputer', 'Ruang Rapat', 'aula madrasah'], $this->repo->riwayatIsian('tempat'));
        $this->assertSame(['Lab Komputer'], $this->repo->riwayatIsian('tempat', 1));
        $this->assertSame(['Guru'], $this->repo->riwayatIsian('peserta_jenis'));

        $this->expectException(InvalidArgumentException::class);
        $this->repo->riwayatIsian('konteks');
    }
}
