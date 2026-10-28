<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\TautanAkunLeading;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TautanAkunLeadingTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

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

    public function test_cakupan_akses_per_leading(): void
    {
        $leading = ['role' => 'leading', 'leadings' => ['1.2', '1.4']];
        $this->assertTrue(TautanAkunLeading::bolehAkses(self::ADMIN, '1.6'));
        $this->assertTrue(TautanAkunLeading::bolehAkses(self::ADMIN, null));
        $this->assertSame([true, false, false], [TautanAkunLeading::bolehAkses($leading, '1.4'), TautanAkunLeading::bolehAkses($leading, '1.6'), TautanAkunLeading::bolehAkses($leading, null)]);
    }

    public function test_atur_tautan_satu_akun(): void
    {
        $hasil = TautanAkunLeading::atur(4, ['1.4', '1.1', '1.1'], self::ADMIN, KelolaLeading::sidik());
        $this->assertSame([['1.1', '1.4'], ['1.1'], ['1.2'], false], [$hasil['akun']['leadings'], $hasil['ditambah'], $hasil['dilepas'], $hasil['tanpa_tautan']]);
        $this->assertSame('Tautan akun Ahmad Fauzan, S.Pd. — ditautkan ke 1.1 Waka Kurikulum; dilepas dari 1.2 Waka Kesiswaan', $hasil['keterangan']);
        $riwayat = app(RiwayatPengaturanRepository::class)->semua();
        $this->assertSame(['ubah_tautan_leading', $hasil['keterangan']], [$riwayat[0]['aksi'], $riwayat[0]['keterangan']]);

        $this->assertNull(TautanAkunLeading::atur(4, ['1.1', '1.4'], self::ADMIN)['keterangan']);
        $this->assertCount(count($riwayat), app(RiwayatPengaturanRepository::class)->semua(), 'Tanpa perubahan: tidak tercatat.');

        // Tautan lama ke leading yang kini nonaktif boleh tetap; tautan baru ke leading nonaktif ditolak.
        app(LeadingRepository::class)->simpan(['kode' => '1.4', 'nama_unit' => 'Waka Humas', 'jabatan_penanda_tangan' => 'Wakil Kepala Bidang Hubungan Masyarakat', 'is_active' => false], '1.4');
        $this->assertSame(['1.4', '1.6'], TautanAkunLeading::atur(4, ['1.4', '1.6'], self::ADMIN)['akun']['leadings']);
        $this->assertSame(['leadings' => 'Leading 1.9 nonaktif — tidak bisa diberi tautan baru.'], $this->galat(fn () => TautanAkunLeading::atur(4, ['1.9'], self::ADMIN)));
        $this->assertSame(['leadings' => 'Leading 7.7 tidak dikenal.'], $this->galat(fn () => TautanAkunLeading::atur(4, ['7.7'], self::ADMIN)));
        $this->assertSame(['akun' => 'Akun admin tidak bertaut ke leading.'], $this->galat(fn () => TautanAkunLeading::atur(2, ['1.1'], self::ADMIN)));
        $this->assertSame(['akun' => 'Akun 99 tidak ditemukan.'], $this->galat(fn () => TautanAkunLeading::atur(99, [], self::ADMIN)));

        $kosong = TautanAkunLeading::atur(4, [], self::ADMIN);
        $this->assertSame([[], true], [$kosong['akun']['leadings'], $kosong['tanpa_tautan']]);
        $this->assertSame([], TautanAkunLeading::untukAkun(4));
    }

    public function test_tautkan_lepas_bacaan_versi_dan_izin(): void
    {
        $this->assertSame('Akun Siti Rohmah, M.Pd. ditautkan ke 1.6 Tim Penjamin Mutu', TautanAkunLeading::tautkan('1.6', 3, self::ADMIN));
        $this->assertSame([['kode' => '1.1', 'nama_unit' => 'Waka Kurikulum', 'is_active' => true], ['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu', 'is_active' => true]], TautanAkunLeading::untukAkun(3));
        $this->assertSame(['keterangan' => 'Akun Siti Rohmah, M.Pd. dilepas dari 1.6 Tim Penjamin Mutu', 'tanpa_tautan' => false], TautanAkunLeading::lepas('1.6', 3, self::ADMIN));
        $this->assertSame(['akun' => 'Akun ini tidak tertaut ke leading tersebut.'], $this->galat(fn () => TautanAkunLeading::lepas('1.6', 3, self::ADMIN)));
        $this->assertSame([4, 3, 6], array_column(TautanAkunLeading::calon('1.6'), 'id'), 'Akun leading belum tertaut: aktif dulu, lalu nama.');

        $versi = KelolaLeading::sidik();
        app(PenggunaRepository::class)->simpan(['jabatan' => 'Ubah di tab lain'], 4);
        try {
            TautanAkunLeading::atur(3, ['1.1', '1.2'], self::ADMIN, $versi);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertSame(['1.1'], app(PenggunaRepository::class)->temukan(3)['leadings']);
        }

        $this->expectException(AuthorizationException::class);
        TautanAkunLeading::tautkan('1.2', 3, ['role' => 'leading', 'leadings' => ['1.1']]);
    }

    public function test_endpoint_tautan_akun(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.akun.leading', ['akun' => 4]))->assertForbidden();
        $this->withSession(['stub_role' => 'admin']);
        $respons = $this->getJson(route('pengaturan.akun.leading', ['akun' => 4]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('akun', ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'role' => 'leading', 'is_active' => true])
            ->assertJsonPath('tautan.0.kode', '1.2');
        $this->assertSame(['1.1', '1.3', '1.5', '1.6', '1.7', '1.8'], array_column($respons->json('bisa_ditautkan'), 'kode'), 'Leading aktif yang belum tertaut.');
        $this->getJson(route('pengaturan.akun.leading', ['akun' => 2]))->assertJsonPath('bisa_ditautkan', []);
        $this->getJson(route('pengaturan.akun.leading', ['akun' => 99]))->assertNotFound();
    }
}
