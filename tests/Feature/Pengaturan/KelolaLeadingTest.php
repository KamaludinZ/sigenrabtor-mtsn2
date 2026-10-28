<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaLeadingTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    private function leading(string $kode): ?array
    {
        return collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', $kode);
    }

    private function riwayat(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0];
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

    public function test_tambah_ubah_dan_kode_terpakai(): void
    {
        $versi = KelolaLeading::sidik();
        $hasil = KelolaLeading::simpan(['kode' => ' 1 . 10 ', 'nama_unit' => '  Literasi   Madrasah ', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => ''], null, self::ADMIN, $versi);
        $this->assertSame(['kode' => '1.10', 'nama_unit' => 'Literasi Madrasah', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => 10, 'is_active' => true], $hasil['leading']);
        $this->assertSame(['tambah_leading', 'Leading 1.10 Literasi Madrasah — jabatan Koordinator Literasi'], [$this->riwayat()['aksi'], $this->riwayat()['keterangan']]);

        $this->assertNull(KelolaLeading::simpan(['kode' => '1.10', 'nama_unit' => 'Literasi Madrasah', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => '10'], '1.10', self::ADMIN)['ringkasan']);
        $this->assertSame('Urutan: 10 → 0', KelolaLeading::simpan(['kode' => '1.10', 'nama_unit' => 'Literasi Madrasah', 'jabatan_penanda_tangan' => 'Koordinator Literasi', 'urutan' => '0'], '1.10', self::ADMIN)['ringkasan']);

        // 1.6 dipakai kegiatan & akun: kodenya tetap walau isian kode diganti.
        $this->assertGreaterThan(0, KelolaLeading::kodeTerpakai('1.6'));
        KelolaLeading::simpan(['kode' => '2.6', 'nama_unit' => 'Tim Penjamin Mutu', 'jabatan_penanda_tangan' => 'Ketua Tim Penjamin Mutu Madrasah', 'urutan' => '6'], '1.6', self::ADMIN);
        $this->assertSame('Ketua Tim Penjamin Mutu Madrasah', $this->leading('1.6')['jabatan_penanda_tangan']);
        $this->assertNull($this->leading('2.6'));

        $this->assertArrayHasKey('kode', $this->galat(fn () => KelolaLeading::simpan(['kode' => '1.1', 'nama_unit' => 'Unit Lain', 'jabatan_penanda_tangan' => 'Ketua', 'urutan' => ''], null, self::ADMIN)));
        $this->assertArrayHasKey('nama_unit', $this->galat(fn () => KelolaLeading::simpan(['kode' => '3.1', 'nama_unit' => 'waka kurikulum', 'jabatan_penanda_tangan' => 'Ketua', 'urutan' => ''], null, self::ADMIN)));
        $this->assertSame(['kode' => 'Leading 7.7 tidak ditemukan.'], $this->galat(fn () => KelolaLeading::simpan(['kode' => '7.7', 'nama_unit' => 'X', 'jabatan_penanda_tangan' => 'Y', 'urutan' => ''], '7.7', self::ADMIN)));
    }

    public function test_status_tautan_versi_dan_izin(): void
    {
        KelolaLeading::ubahStatus('1.8', false, self::ADMIN);
        $this->assertFalse($this->leading('1.8')['is_active']);
        $this->assertSame('Leading 1.8 PMM dinonaktifkan', $this->riwayat()['keterangan']);
        $this->assertSame(['kegiatan' => 0, 'akun' => []], KelolaLeading::dampakNonaktif('1.8'));
        $this->assertSame(['Muslimin, M.Pd.'], KelolaLeading::dampakNonaktif('1.6')['akun']);

        foreach (app(LeadingRepository::class)->semua() as $l) {
            if ($l['is_active'] && $l['kode'] !== '1.1') {
                KelolaLeading::ubahStatus($l['kode'], false, self::ADMIN);
            }
        }
        $this->assertSame(['status' => 'Minimal satu leading harus tetap aktif.'], $this->galat(fn () => KelolaLeading::ubahStatus('1.1', false, self::ADMIN)));
        KelolaLeading::ubahStatus('1.2', true, self::ADMIN);

        $this->assertSame('Akun Siti Rohmah, M.Pd. ditautkan ke 1.2 Waka Kesiswaan', KelolaLeading::tautkan('1.2', 3, self::ADMIN));
        $this->assertSame(['1.1', '1.2'], app(PenggunaRepository::class)->temukan(3)['leadings']);
        $this->assertSame(['akun' => 'Leading nonaktif tidak bisa diberi tautan baru.'], $this->galat(fn () => KelolaLeading::tautkan('1.9', 3, self::ADMIN)));
        $this->assertSame(['akun' => 'Pilih akun leading yang akan ditautkan.'], $this->galat(fn () => KelolaLeading::tautkan('1.2', 2, self::ADMIN)), 'Akun admin tidak bertaut.');

        $this->assertSame(['keterangan' => 'Akun Siti Rohmah, M.Pd. dilepas dari 1.1 Waka Kurikulum', 'tanpa_tautan' => false], KelolaLeading::lepas('1.1', 3, self::ADMIN));
        $this->assertTrue(KelolaLeading::lepas('1.2', 3, self::ADMIN)['tanpa_tautan']);
        $this->assertSame('ubah_tautan_leading', $this->riwayat()['aksi']);

        $versiLama = KelolaLeading::sidik();
        app(PenggunaRepository::class)->catatMasuk(1);
        $this->assertSame($versiLama, KelolaLeading::sidik(), 'Waktu masuk bukan perubahan data.');
        app(LeadingRepository::class)->simpan(['kode' => '1.3', 'nama_unit' => 'Waka Sarpras', 'jabatan_penanda_tangan' => 'Wakil Kepala Sarpras'], '1.3');
        try {
            KelolaLeading::tautkan('1.1', 3, self::ADMIN, $versiLama);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertSame([], app(PenggunaRepository::class)->temukan(3)['leadings']);
        }

        $this->expectException(AuthorizationException::class);
        KelolaLeading::ubahStatus('1.8', true, ['role' => 'leading']);
    }

    public function test_endpoint_data_leading(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.leading.data'))->assertForbidden();
        $respons = $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.leading.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('versi', KelolaLeading::sidik())
            ->assertJsonCount(count(app(LeadingRepository::class)->semua()), 'leading');
        $tpm = collect($respons->json('leading'))->firstWhere('kode', '1.6');
        $this->assertSame([['id' => 1, 'name' => 'Muslimin, M.Pd.', 'is_active' => true]], $tpm['akun']);
        $this->assertSame(['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => null, 'nip' => null], $tpm['pejabat']);
        $this->assertTrue($tpm['kode_terpakai']);
        $this->assertGreaterThan(0, $tpm['kegiatan_tahun_aktif']);
        $this->assertFalse(collect($respons->json('leading'))->firstWhere('kode', '1.7')['kode_terpakai']);
    }
}
