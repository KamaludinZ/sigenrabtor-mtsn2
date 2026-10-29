<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Dokumen\AksiRiwayatDokumen;
use App\Support\Dokumen\OtorisasiDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class OtorisasiDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function catatan(string $id): array
    {
        return collect(RiwayatDokumen::untuk(self::ADMIN))->firstWhere('id', $id);
    }

    /** Catatan baru untuk kegiatan $kegiatan oleh akun $oleh. */
    private function catat(array $kegiatan, array $oleh, string $asal = 'cetak', ?string $sumber = 'Komite'): array
    {
        $id = app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri($kegiatan, 'lengkap', $sumber, 'pdf', $asal, 'BERKAS.pdf', 100, true, $oleh))['id'];

        return collect(RiwayatDokumen::untuk(self::ADMIN))->firstWhere('id', $id);
    }

    public function test_lihat_cakupan_akun_dan_akun_nonaktif(): void
    {
        $this->assertNull(OtorisasiDokumen::lihat($this->catatan('rd-2026-1'), self::LEADING));
        $this->assertSame(OtorisasiDokumen::PESAN_TOLAK, OtorisasiDokumen::lihat($this->catatan('rd-2025-1'), self::LEADING), 'Leading 1.2 milik akun lain.');
        $this->assertNull(OtorisasiDokumen::lihat($this->catatan('rd-2025-1'), self::ADMIN));
        $buatanSendiri = $this->catat(['nama_kegiatan' => 'Lomba Leading Lain', 'leading' => '1.2', 'tahun_anggaran' => 2025, 'status' => 'final'], self::LEADING);
        $this->assertNull(OtorisasiDokumen::lihat($buatanSendiri, self::LEADING), 'Dokumen buatan sendiri tetap terlihat.');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage(OtorisasiDokumen::PESAN_TOLAK);
        OtorisasiDokumen::pastikan('lihat', $this->catatan('rd-2025-1'), null, self::LEADING);
    }

    public function test_akun_nonaktif_tidak_melihat_apa_pun(): void
    {
        $akun = app(PenggunaRepository::class)->temukan(1);
        app(PenggunaRepository::class)->simpan(['is_active' => false] + $akun, 1);
        $this->assertFalse(OtorisasiDokumen::akunAktif(self::LEADING));
        $this->assertSame([], RiwayatDokumen::untuk(self::LEADING));
        $this->assertSame(OtorisasiDokumen::PESAN_NONAKTIF, OtorisasiDokumen::lihat($this->catatan('rd-2026-1'), self::LEADING));
        $this->getJson(route('dokumen.data'))->assertOk()->assertJsonPath('total', 0);
        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-2026-1']))->assertForbidden()->assertJsonPath('pesan', OtorisasiDokumen::PESAN_NONAKTIF);
    }

    public function test_buka_ulang_unduh_arsip_dan_duplikat(): void
    {
        $repo = app(KegiatanRepository::class);
        $izin = fn (array $c, array $p = self::LEADING, bool $terbaru = true) => OtorisasiDokumen::izin($c, $c['tersedia'] ? $repo->temukan((int) $c['kegiatan_id']) : null, $p, $terbaru);

        $this->assertSame(['lihat' => true, 'buka_ulang' => true, 'unduh_arsip' => false, 'duplikat' => true], $izin($this->catatan('rd-2026-1'))['izin']);
        $lama = $izin($this->catatan('rd-2025-5'));
        $this->assertSame(['lihat' => true, 'buka_ulang' => false, 'unduh_arsip' => false, 'duplikat' => false], $lama['izin'], 'Kegiatan tidak tersimpan.');
        $this->assertStringContainsString('tidak lagi tersimpan', $lama['alasan']['buka_ulang']);

        // Kegiatan leading lain buatan akun lain; catatannya dibuat akun leading 1.6 (terlihat), tetapi kegiatannya di luar cakupan.
        $lain = $repo->simpan(['nama_kegiatan' => 'Kegiatan Leading Lain', 'leading' => '1.2', 'user_id' => 2, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $luar = $izin($this->catat($repo->temukan($lain), self::LEADING));
        $this->assertSame([true, false, false], [$luar['izin']['lihat'], $luar['izin']['buka_ulang'], $luar['izin']['duplikat']]);
        $this->assertSame(OtorisasiDokumen::PESAN_KEGIATAN_LUAR, $luar['alasan']['duplikat']);

        // Kegiatan leading 1.2 buatan akun ini sendiri: boleh dibuka ulang, tetapi tidak boleh disalin karena leading-nya tidak tertaut.
        $buatan = $repo->simpan(['nama_kegiatan' => 'Kegiatan Buatan Sendiri', 'leading' => '1.2', 'user_id' => 1, 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 1_000_000]]]);
        $sendiri = $izin($this->catat($repo->temukan($buatan), self::LEADING));
        $this->assertSame([true, true, false], [$sendiri['izin']['lihat'], $sendiri['izin']['buka_ulang'], $sendiri['izin']['duplikat']]);
        $this->assertSame('Kegiatan baru hanya bisa dibuat untuk leading yang tertaut ke akun Anda — leading 1.2 tidak lagi tertaut.', $sendiri['alasan']['duplikat']);
        $this->assertTrue($izin($this->catat($repo->temukan($buatan), self::LEADING), self::ADMIN)['izin']['duplikat'], 'Admin boleh menyalin.');

        $arsipLama = $this->catat($repo->temukan(1), self::LEADING, 'arsip');
        $this->assertTrue($izin($arsipLama)['izin']['unduh_arsip']);
        $this->assertSame('Arsip final versi ini sudah diganti versi yang lebih baru.', $izin($arsipLama, self::LEADING, false)['alasan']['unduh_arsip']);

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $this->assertStringContainsString('2026', (string) $izin($this->catatan('rd-2026-1'))['alasan']['duplikat']);
    }

    public function test_layanan_dan_endpoint_memakai_otorisasi(): void
    {
        $rincian = AksiRiwayatDokumen::lihat('rd-2026-1', self::LEADING);
        $this->assertSame(['lihat' => true, 'buka_ulang' => true, 'unduh_arsip' => false, 'duplikat' => true], $rincian['izin']);

        $data = $this->getJson(route('dokumen.data'))->assertOk()->json();
        $rd1 = collect($data['dokumen'])->firstWhere('id', 'rd-2026-1');
        $this->assertSame(['lihat' => true, 'buka_ulang' => true, 'unduh_arsip' => false, 'duplikat' => true], $rd1['izin']);
        $arsip = collect($data['dokumen'])->firstWhere('id', 'rd-2025-5');
        $this->assertSame(['lihat' => true, 'buka_ulang' => false, 'unduh_arsip' => false, 'duplikat' => false], $arsip['izin']);
        $perKegiatan = $this->getJson(route('dokumen.data', ['tampilan' => 'kegiatan']))->assertOk()->json();
        $this->assertArrayHasKey('izin', $perKegiatan['kegiatan'][0]['dokumen'][0]['terbaru']);

        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-2026-1']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('dokumen.id', 'rd-2026-1')
            ->assertJsonPath('izin.duplikat', true)
            ->assertJsonPath('duplikat.mode', 'tahun_ini')
            ->assertJsonPath('alasan.unduh_arsip', 'Catatan ini bukan arsip final.');
        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-2025-5']))->assertOk()->assertJsonPath('duplikat.bisa', false);
        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-2025-1']))->assertForbidden()->assertJsonPath('pesan', OtorisasiDokumen::PESAN_TOLAK);
        $this->getJson(route('dokumen.detail', ['dokumen' => 'rd-tidak-ada']))->assertNotFound()->assertJsonPath('pesan', AksiRiwayatDokumen::PESAN_TIDAK_ADA);
    }
}
