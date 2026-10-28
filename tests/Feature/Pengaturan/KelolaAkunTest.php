<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaAkun;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaAkunTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

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

    public function test_tambah_ubah_dan_aturan(): void
    {
        $hasil = KelolaAkun::simpan(['name' => '  Laila   Fitri, S.Pd. ', 'email' => ' Laila@Example.com ', 'role' => 'leading', 'jabatan' => '', 'nip' => '19850312.201001.2.004', 'leadings' => ['1.6', '1.2', '1.6']], null, self::ADMIN, KelolaLeading::sidik());
        $this->assertSame(7, $hasil['id']);
        $this->assertSame(['Laila Fitri, S.Pd.', 'laila@example.com', '198503122010012004', null, ['1.2', '1.6']], [$hasil['akun']['name'], $hasil['akun']['email'], $hasil['akun']['nip'], $hasil['akun']['jabatan'], $hasil['akun']['leadings']]);
        $this->assertSame(['tambah_akun', 'Akun Laila Fitri, S.Pd. (laila@example.com) — Leading 1.2, 1.6'], [$this->riwayat()['aksi'], $this->riwayat()['keterangan']]);

        $this->assertNull(KelolaAkun::simpan(['name' => 'Laila Fitri, S.Pd.', 'email' => 'laila@example.com', 'role' => 'leading', 'jabatan' => '', 'nip' => '198503122010012004', 'leadings' => ['1.2', '1.6']], 7, self::ADMIN)['ringkasan']);
        $this->assertSame('Jabatan: — → Koordinator Literasi', KelolaAkun::simpan(['name' => 'Laila Fitri, S.Pd.', 'email' => 'laila@example.com', 'role' => 'leading', 'jabatan' => 'Koordinator Literasi', 'nip' => '198503122010012004', 'leadings' => ['1.2', '1.6']], 7, self::ADMIN)['ringkasan']);
        $this->assertSame('Akun Laila Fitri, S.Pd. — Jabatan: — → Koordinator Literasi', $this->riwayat()['keterangan']);

        $isian = fn (array $ubah) => $ubah + ['name' => 'Akun Uji', 'email' => 'uji@example.com', 'role' => 'leading', 'jabatan' => '', 'nip' => '', 'leadings' => ['1.6']];
        $this->assertSame(['email' => 'Surel sudah dipakai akun Muslimin, M.Pd.'], $this->galat(fn () => KelolaAkun::simpan($isian(['email' => 'MUSLIMIN@example.com']), null, self::ADMIN)));
        $this->assertSame(['leadings' => 'Akun leading wajib ditautkan ke minimal satu leading.'], $this->galat(fn () => KelolaAkun::simpan($isian(['leadings' => []]), null, self::ADMIN)));
        $this->assertSame(['leadings' => 'Leading yang dipilih tidak dikenal atau nonaktif.'], $this->galat(fn () => KelolaAkun::simpan($isian(['leadings' => ['1.9']]), null, self::ADMIN)));
        $this->assertSame(['role' => 'Peran akun Anda sendiri tidak bisa diubah.'], $this->galat(fn () => KelolaAkun::simpan(['name' => 'Koordinator PUSKOM', 'email' => 'puskom@example.com', 'role' => 'leading', 'jabatan' => '', 'nip' => '', 'leadings' => ['1.6']], 2, self::ADMIN)));
        $this->assertSame(['id' => 'Akun 99 tidak ditemukan.'], $this->galat(fn () => KelolaAkun::simpan($isian([]), 99, self::ADMIN)));
    }

    public function test_status_versi_dan_izin(): void
    {
        $this->assertSame(['status' => 'Akun Anda sendiri tidak bisa dinonaktifkan.'], $this->galat(fn () => KelolaAkun::ubahStatus(2, false, self::ADMIN)));
        $adminLain = ['id' => 99, 'role' => 'admin'];
        KelolaAkun::ubahStatus(5, false, $adminLain);
        $this->assertSame(['status' => 'Minimal satu admin aktif harus tetap ada.'], $this->galat(fn () => KelolaAkun::ubahStatus(2, false, $adminLain)));
        $this->assertSame('Akun Rina Kusumawati, M.Pd. dinonaktifkan', $this->riwayat()['keterangan']);

        app(LeadingRepository::class)->simpan(['kode' => '1.5', 'nama_unit' => 'Ka. TU', 'jabatan_penanda_tangan' => 'Kepala Tata Usaha', 'is_active' => false], '1.5');
        $hasil = KelolaAkun::ubahStatus(6, true, self::ADMIN);
        $this->assertSame([true, true], [$hasil['akun']['is_active'], $hasil['tanpa_leading_aktif']], 'Akun 6 hanya tertaut ke 1.5 yang nonaktif.');
        $this->assertSame(1, KelolaAkun::kegiatanBuatan(2));

        $versi = KelolaLeading::sidik();
        app(PenggunaRepository::class)->simpan(['jabatan' => 'Diubah di tab lain'], 3);
        try {
            KelolaAkun::ubahStatus(3, false, self::ADMIN, $versi);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertTrue(app(PenggunaRepository::class)->temukan(3)['is_active']);
        }

        $this->expectException(AuthorizationException::class);
        KelolaAkun::simpan(['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'], null, ['id' => 1, 'role' => 'leading']);
    }

    public function test_endpoint_data_akun(): void
    {
        app(PenggunaRepository::class)->catatMasuk(1, '10.0.0.5', 'Peramban Uji');
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.akun.data'))->assertForbidden();
        $respons = $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.akun.data'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('versi', KelolaLeading::sidik())
            ->assertJsonPath('ringkasan', ['aktif' => 5, 'nonaktif' => 1, 'per_peran' => ['admin' => 2, 'leading' => 3]])
            ->assertJsonCount(6, 'akun');
        $akun = collect($respons->json('akun'))->keyBy('id');
        $this->assertSame([2, 1], [$akun[1]['kegiatan_buatan'], $akun[2]['kegiatan_buatan']]);
        $this->assertSame([['10.0.0.5', 'Peramban Uji']], array_map(fn (array $r) => [$r['alamat_ip'], $r['agen']], $akun[1]['riwayat_masuk']));
        $this->assertSame([], $akun[3]['riwayat_masuk']);
    }
}
