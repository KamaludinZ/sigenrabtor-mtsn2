<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\JejakDuplikatRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\KelengkapanKode;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\ValidasiKodeMaster;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Layanan duplikasi kegiatan tahun lalu menjadi draf tahun aktif (PRD §19 F22) — dipakai tab Duplikat
 * (`Kegiatan\SalinImpor::duplikat`) dan kelak endpoint duplikasi: tahun aktif harus terbuka (KunciKegiatan →
 * AuthorizationException), minimal satu & paling banyak MAKS kegiatan (galat `kegiatan`); tiap kegiatan diperiksa sendiri
 * (urutan = urutan pilihan, id ganda sekali saja): tidak ada / bukan tahun sumber (DuplikatKegiatan::tahunSumber) /
 * di luar cakupan akun (AksesKegiatan) / leading kini nonaktif → `gagal`; nama sudah ada di tahun aktif (termasuk yang
 * baru dibuat dalam permintaan yang sama, pembanding PemeriksaImporKegiatan::kunciNama) → `dilewati`; selain itu disalin
 * DuplikatKegiatan::jalankan lalu dicatat di JejakDuplikatRepository → `dibuat` {id sumber, hasil_id, nama, catatan}.
 * `rencana()` = pemeriksaan yang sama tanpa menyimpan, dengan validasi kode draf terhadap master tahun aktif.
 */
final class LayananDuplikat
{
    public const MAKS = 100;

    /**
     * Rencana duplikat tanpa menyimpan (pratinjau & validasi kode): pemeriksaan per kegiatan sama dengan `jalankan()`;
     * kegiatan `siap` membawa catatan DuplikatKegiatan::rencana, perbandingan tiap kode sumber dengan kode draf (`tetap`
     * atau `dikosongkan` — tidak ada di master tahun aktif / IKSK bukan indikator SK-nya), sumber dana & pagu draf, dan
     * `galat_kode` = ValidasiKodeMaster::galat data draf terhadap master tahun aktif (kosong bila semua kode sah).
     *
     * @param  list<int|string>  $ids
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{siap: list<array<string, mixed>>, dilewati: list<array{id: int, nama: string, alasan: string}>, gagal: list<array{id: int, alasan: string}>, tahun: int}
     *
     * @throws AuthorizationException Tahun anggaran aktif terkunci.
     * @throws ValidationException Berkunci `kegiatan`.
     */
    public static function rencana(array $ids, array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $hasil = ['siap' => [], 'dilewati' => [], 'gagal' => []];
        foreach (self::periksa($ids, $pengguna) as $p) {
            if ($p['status'] !== 'siap') {
                $hasil[$p['status']][] = array_diff_key($p, ['status' => true, 'kegiatan' => true]);

                continue;
            }
            ['data' => $data, 'catatan' => $catatan] = DuplikatKegiatan::rencana($p['kegiatan']);
            $hasil['siap'][] = [
                'id' => $p['id'],
                'nama' => $p['nama'],
                'catatan' => $catatan,
                'kode' => array_map(fn (string $isian) => [
                    'isian' => $isian,
                    'label' => KelengkapanKode::KODE[$isian][0],
                    'kode_sumber' => blank($p['kegiatan'][$isian] ?? null) ? null : (string) $p['kegiatan'][$isian],
                    'kode_draf' => blank($data[$isian] ?? null) ? null : (string) $data[$isian],
                    'status' => blank($p['kegiatan'][$isian] ?? null) ? 'kosong' : (blank($data[$isian] ?? null) ? 'dikosongkan' : 'tetap'),
                ], array_keys(KelengkapanKode::KODE)),
                'sumber_dana' => $data['sumber_dana'] ?? [],
                'data_pagu_id' => $data['data_pagu_id'] ?? null,
                'galat_kode' => ValidasiKodeMaster::galat($data + ['tahun_anggaran' => $tahun]),
            ];
        }

        return $hasil + ['tahun' => $tahun];
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{dibuat: list<array{id: int, hasil_id: int, nama: string, catatan: list<array{jenis: string, teks: string}>}>, dilewati: list<array{id: int, nama: string, alasan: string}>, gagal: list<array{id: int, alasan: string}>, pesan: string}
     *
     * @throws AuthorizationException Tahun anggaran aktif terkunci.
     * @throws ValidationException Berkunci `kegiatan`.
     */
    public static function jalankan(array $ids, array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $hasil = ['dibuat' => [], 'dilewati' => [], 'gagal' => []];
        foreach (self::periksa($ids, $pengguna) as $p) {
            if ($p['status'] !== 'siap') {
                $hasil[$p['status']][] = array_diff_key($p, ['status' => true, 'kegiatan' => true]);

                continue;
            }
            $salinan = DuplikatKegiatan::jalankan($p['kegiatan'], $pengguna);
            app(JejakDuplikatRepository::class)->catat([
                'sumber_id' => $p['id'], 'sumber_tahun' => TahunKegiatan::dari($p['kegiatan']), 'hasil_id' => $salinan['id'], 'tahun' => $tahun,
                'catatan' => $salinan['catatan'], 'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name'],
            ]);
            $hasil['dibuat'][] = ['id' => $p['id'], 'hasil_id' => $salinan['id'], 'nama' => $salinan['nama'], 'catatan' => $salinan['catatan']];
        }

        $bagian = array_filter([
            count($hasil['dibuat']) ? count($hasil['dibuat'])." kegiatan diduplikat menjadi draf TA {$tahun}" : null,
            count($hasil['dilewati']) ? count($hasil['dilewati']).' dilewati (sudah ada)' : null,
            count($hasil['gagal']) ? count($hasil['gagal']).' gagal' : null,
        ]);

        return $hasil + ['pesan' => ucfirst(implode(', ', $bagian)).'.'];
    }

    /**
     * Pemeriksaan bersama rencana & jalankan, urut pilihan: status siap | dilewati | gagal (+ alasan; siap membawa kegiatan).
     *
     * @param  list<int|string>  $ids
     * @return list<array{status: string, id: int, nama?: string, alasan?: string, kegiatan?: array<string, mixed>}>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private static function periksa(array $ids, array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        if (($terkunci = KunciKegiatan::tahunTerkunci(['tahun_anggaran' => $tahun])) !== null) {
            throw new AuthorizationException(KunciKegiatan::pesanTahun($terkunci));
        }
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => filter_var($id, FILTER_VALIDATE_INT)))));
        if ($ids === []) {
            throw ValidationException::withMessages(['kegiatan' => 'Pilih minimal satu kegiatan tahun lalu.']);
        }
        if (count($ids) > self::MAKS) {
            throw ValidationException::withMessages(['kegiatan' => 'Paling banyak '.self::MAKS.' kegiatan sekali duplikat.']);
        }

        $semua = app(KegiatanRepository::class)->semua();
        $perId = array_column($semua, null, 'id');
        $sumberTahun = DuplikatKegiatan::tahunSumber();
        $sudahAda = [];
        foreach ($semua as $k) {
            if (TahunKegiatan::dari($k) === $tahun) {
                $sudahAda[PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? ''))] = true;
            }
        }

        $hasil = [];
        foreach ($ids as $id) {
            $kegiatan = $perId[$id] ?? null;
            $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? ''));
            $leading = filled($kegiatan['leading'] ?? null) ? MasterKode::leading($kegiatan['leading']) : null;
            $alasan = match (true) {
                $kegiatan === null => 'Kegiatan tidak ditemukan.',
                ! in_array(TahunKegiatan::dari($kegiatan), $sumberTahun, true) => "Kegiatan “{$nama}” bukan kegiatan tahun lalu — hanya kegiatan tahun anggaran lain yang bisa diduplikat.",
                ! AksesKegiatan::bolehLihat($kegiatan, $pengguna) => AksesKegiatan::PESAN_TOLAK,
                $leading !== null && ! ($leading['is_active'] ?? true) => "Leading {$leading['kode']} {$leading['nama_unit']} kini nonaktif — kegiatan “{$nama}” tidak bisa diduplikat.",
                default => null,
            };
            if ($alasan !== null) {
                $hasil[] = ['status' => 'gagal', 'id' => $id, 'alasan' => $alasan];

                continue;
            }
            $kunci = PemeriksaImporKegiatan::kunciNama($nama);
            if (isset($sudahAda[$kunci])) {
                $hasil[] = ['status' => 'dilewati', 'id' => $id, 'nama' => $nama, 'alasan' => "Kegiatan bernama sama sudah ada di TA {$tahun}."];

                continue;
            }
            $sudahAda[$kunci] = true;
            $hasil[] = ['status' => 'siap', 'id' => $id, 'nama' => $nama, 'kegiatan' => $kegiatan];
        }

        return $hasil;
    }
}
