<?php

namespace App\Support\Akun;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Kegiatan\StatusKuotaAi;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\Nip;
use App\Support\Pengaturan\PejabatBerlaku;
use App\Support\Stub\StubData;
use Illuminate\Support\Str;

/**
 * Data tab Profil halaman Akun saya (PRD §8 "Kelola Profil & Sandi": dokumen tersimpan atas nama pengguna & leading-nya)
 * di luar identitas `DataAkun::untuk` — satu-satunya penyusunnya:
 * - `kegiatan` = kegiatan buatan akun (`user_id`): semua tahun, tahun aktif, per `StatusKegiatan` tahun aktif;
 * - `dokumen` = catatan Riwayat Dokumen yang dibuat akun ini (`RiwayatDokumen::untuk` + `user_id`), tahun aktif, terbaru;
 * - `tercetak` = per leading tertaut (akun leading): penanda tangan Pembuat lembar pengesahan TA aktif
 *   (`PejabatBerlaku::pejabatLeading` — `SUMBER` pejabat diatur admin / nama akun ini selama pejabat kosong
 *   (`namaAkunLeading` untuk kegiatan buatannya) / kosong) beserta keterangan;
 * - `kuota` = `StatusKuotaAi::untuk`; `sesi` = waktu masuk sesi ini (`SesiAkun`) + perangkat.
 */
final class ProfilAkun
{
    public const SUMBER = [
        'pejabat' => 'Pejabat leading diatur admin',
        'akun' => 'Nama Anda',
        'kosong' => 'Belum ada nama',
    ];

    /**
     * @param  array{id: int, name: string, role: string, leadings: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna): array
    {
        $id = (int) $pengguna['id'];
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $buatan = array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => (int) ($k['user_id'] ?? 0) === $id));
        $tahunIni = array_values(array_filter($buatan, fn (array $k) => TahunKegiatan::dari($k) === $tahun));
        $perStatus = [];
        foreach (StatusKegiatan::cases() as $s) {
            $perStatus[$s->value] = count(array_filter($tahunIni, fn (array $k) => ($k['status'] ?? 'draf') === $s->value));
        }
        $dokumen = array_values(array_filter(RiwayatDokumen::untuk($pengguna), fn (array $d) => ($d['user_id'] ?? null) === $id));

        return [
            'tahun' => $tahun,
            'kegiatan' => ['semua' => count($buatan), 'tahun_aktif' => count($tahunIni), 'per_status' => $perStatus],
            'dokumen' => [
                'jumlah' => count($dokumen),
                'tahun_aktif' => count(array_filter($dokumen, fn (array $d) => (int) $d['tahun_anggaran'] === $tahun)),
                'terakhir' => $dokumen[0] ?? null,
            ],
            'tercetak' => $pengguna['role'] === 'admin' ? [] : self::tercetak($pengguna, $tahun),
            'kuota' => StatusKuotaAi::untuk($pengguna),
            'sesi' => ['masuk_pada' => SesiAkun::pengguna()['masuk_pada'], 'perangkat' => Str::limit((string) request()->userAgent(), 80) ?: null],
        ];
    }

    /**
     * @param  array{id: int, name: string, leadings: list<string>}  $pengguna
     * @return list<array{kode: string, nama_unit: string, jabatan: string, nama: string|null, nip: string|null, sumber: string, label_sumber: string, keterangan: string}>
     */
    private static function tercetak(array $pengguna, int $tahun): array
    {
        $berlaku = PejabatBerlaku::tahun($tahun);
        $hasil = [];
        foreach ($pengguna['leadings'] as $kode) {
            $leading = MasterKode::leading($kode);
            $pejabat = $berlaku->pejabatLeading($kode);
            if ($leading === null || $pejabat === null) {
                continue;
            }
            $namaAkun = $berlaku->namaAkunLeading($kode, (int) $pengguna['id']);
            [$sumber, $nama, $nip, $keterangan] = match (true) {
                filled($pejabat['nama']) => ['pejabat', $pejabat['nama'], $pejabat['nip'] ? Nip::format((string) $pejabat['nip']) : null, "Lembar pengesahan TA {$tahun} memakai pejabat leading yang diisi admin di Pengaturan › Profil & Pejabat."],
                $namaAkun === $pengguna['name'] => ['akun', $namaAkun, null, 'Selama pejabat leading belum diisi admin, nama Anda tercetak sebagai Pembuat pada kegiatan yang Anda buat (tanpa NIP).'],
                default => ['kosong', null, null, 'Pejabat leading belum diisi dan akun Anda tidak dipakai sebagai Pembuat — minta admin mengisi pejabat leading.'],
            };
            $hasil[] = ['kode' => $kode, 'nama_unit' => $leading['nama_unit'], 'jabatan' => (string) $pejabat['jabatan'], 'nama' => $nama, 'nip' => $nip,
                'sumber' => $sumber, 'label_sumber' => self::SUMBER[$sumber], 'keterangan' => $keterangan];
        }

        return $hasil;
    }
}
