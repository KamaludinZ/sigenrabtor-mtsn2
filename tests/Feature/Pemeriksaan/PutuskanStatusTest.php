<?php

namespace Tests\Feature\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\RincianRab;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PutuskanStatusTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    /** Kegiatan diajukan (Diperiksa) dengan RAB Komite senilai $rab (0 = tanpa rincian) terhadap pagu $pagu. */
    private function diajukan(int $rab = 500_000, int $pagu = 1_000_000, int $tahun = 2026): int
    {
        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja Madrasah', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => $tahun, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => $pagu]]]);
        $rab > 0 && RincianRab::tambah($id, 'Komite', ['uraian' => 'Konsumsi rapat', 'kategori' => 'bahan', 'volume' => '1', 'satuan' => 'paket', 'harga_satuan' => (string) $rab]);
        $repo->simpan(['status' => 'diperiksa', 'riwayat_status' => [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING)]] + $repo->temukan($id), $id);

        return $id;
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    public function test_setujui_kembalikan_dan_buka_kunci(): void
    {
        $id = $this->diajukan();
        $repo = app(KegiatanRepository::class);
        $this->assertSame(['setujui', 'kembalikan'], array_keys(KeputusanPemeriksaan::tersedia($repo->temukan($id), self::ADMIN)));
        $this->assertSame([], KeputusanPemeriksaan::tersedia($repo->temukan($id), self::LEADING));

        $versi = KeputusanPemeriksaan::versi($repo->temukan($id));
        $hasil = KeputusanPemeriksaan::putuskan($id, 'setujui', '', self::ADMIN, $versi);
        $this->assertSame(['disetujui', 'setujui', 'Koordinator PUSKOM', 'admin'], [$hasil['kegiatan']['status'], $hasil['entri']['aksi'], $hasil['entri']['oleh'], $hasil['entri']['peran']]);
        $this->assertArrayNotHasKey('catatan', $hasil['entri']);
        $this->assertNotSame($versi, $hasil['versi']);
        $this->assertSame(['ajukan', 'setujui'], array_column($repo->temukan($id)['riwayat_status'], 'aksi'));

        $this->assertSame(['catatan' => RiwayatStatus::CATATAN_WAJIB['kembalikan']], $this->galat(fn () => KeputusanPemeriksaan::putuskan($id, 'kembalikan', "  \n ", self::ADMIN)));
        $kembali = KeputusanPemeriksaan::putuskan($id, 'kembalikan', "Lengkapi   sasaran.\n\n\n\nPerbaiki tanggal.", self::ADMIN);
        $this->assertSame(['dikembalikan', "Lengkapi sasaran.\n\nPerbaiki tanggal."], [$kembali['kegiatan']['status'], $kembali['entri']['catatan']]);
        $this->assertArrayHasKey('sidik', $kembali['entri'], 'Titik awal perbaikan.');
        $this->assertSame(['keputusan' => 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.'], $this->galat(fn () => KeputusanPemeriksaan::putuskan($id, 'setujui', '', self::ADMIN)));

        $final = $repo->simpan(['nama_kegiatan' => 'Wisuda', 'leading' => '1.6', 'status' => 'final', 'riwayat_status' => [RiwayatStatus::entri('finalkan', StatusKegiatan::Final, self::LEADING)]]);
        $this->assertSame(['buka_kunci'], array_keys(KeputusanPemeriksaan::tersedia($repo->temukan($final), self::ADMIN)));
        $this->assertSame('dikembalikan', KeputusanPemeriksaan::putuskan($final, 'buka_kunci', 'Salah nominal termin.', self::ADMIN)['kegiatan']['status']);
    }

    public function test_batas_pagu_tahun_terkunci_versi_dan_izin(): void
    {
        $lebih = $this->diajukan(rab: 1_500_000, pagu: 1_000_000);
        $pesan = $this->galat(fn () => KeputusanPemeriksaan::putuskan($lebih, 'setujui', '', self::ADMIN));
        $this->assertStringStartsWith('RAB Komite Rp1.500.000 melebihi pagu Rp1.000.000', $pesan['keputusan']);
        $this->assertSame('dikembalikan', KeputusanPemeriksaan::putuskan($lebih, 'kembalikan', 'Kurangi rincian RAB.', self::ADMIN)['kegiatan']['status'], 'Mengembalikan tetap boleh.');

        $lalu = $this->diajukan(rab: 0, tahun: 2025); // RAB tahun terkunci tidak bisa ditulis
        $this->assertStringContainsString('Tahun Anggaran 2025', $this->galat(fn () => KeputusanPemeriksaan::putuskan($lalu, 'setujui', '', self::ADMIN))['keputusan']);

        $id = $this->diajukan();
        $versi = KeputusanPemeriksaan::versi(app(KegiatanRepository::class)->temukan($id));
        app(KegiatanRepository::class)->simpan(['status' => 'draf', 'riwayat_status' => [...app(KegiatanRepository::class)->temukan($id)['riwayat_status'], RiwayatStatus::entri('tarik', StatusKegiatan::Draf, self::LEADING)]] + app(KegiatanRepository::class)->temukan($id), $id);
        try {
            KeputusanPemeriksaan::putuskan($id, 'setujui', '', self::ADMIN, $versi);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertSame('draf', app(KegiatanRepository::class)->temukan($id)['status']);
        }

        $this->expectException(AuthorizationException::class);
        KeputusanPemeriksaan::putuskan($this->diajukan(), 'setujui', '', self::LEADING);
    }

    public function test_endpoint_keputusan(): void
    {
        $id = $this->diajukan();
        $url = route('pemeriksaan.putuskan', ['kegiatan' => $id]);

        $this->withSession(['stub_role' => 'leading'])->postJson($url, ['aksi' => 'setujui'])->assertForbidden();
        $this->withSession(['stub_role' => 'admin']);
        $this->postJson(route('pemeriksaan.putuskan', ['kegiatan' => 999]), ['aksi' => 'setujui'])->assertNotFound();
        $this->postJson($url, ['aksi' => 'kembalikan'])->assertStatus(422)->assertJsonPath('galat.catatan.0', RiwayatStatus::CATATAN_WAJIB['kembalikan']);
        $this->postJson($url, ['aksi' => 'setujui', 'versi' => 'usang'])->assertStatus(409);

        $versi = KeputusanPemeriksaan::versi(app(KegiatanRepository::class)->temukan($id));
        $this->postJson($url, ['aksi' => 'setujui', 'versi' => $versi])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'disetujui')
            ->assertJsonPath('label_status', 'Disetujui')
            ->assertJsonPath('entri.aksi', 'setujui')
            ->assertJsonPath('pesan', KeputusanPemeriksaan::PESAN['setujui']);
        $this->postJson($url, ['aksi' => 'setujui'])->assertStatus(422)->assertJsonPath('galat.keputusan.0', 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.');
    }
}
