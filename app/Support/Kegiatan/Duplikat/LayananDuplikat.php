<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\JejakDuplikatRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
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
 */
final class LayananDuplikat
{
    public const MAKS = 100;

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

        $hasil = ['dibuat' => [], 'dilewati' => [], 'gagal' => []];
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
                $hasil['gagal'][] = ['id' => $id, 'alasan' => $alasan];

                continue;
            }
            $kunci = PemeriksaImporKegiatan::kunciNama($nama);
            if (isset($sudahAda[$kunci])) {
                $hasil['dilewati'][] = ['id' => $id, 'nama' => $nama, 'alasan' => "Kegiatan bernama sama sudah ada di TA {$tahun}."];

                continue;
            }

            $salinan = DuplikatKegiatan::jalankan($kegiatan, $pengguna);
            $sudahAda[$kunci] = true;
            app(JejakDuplikatRepository::class)->catat([
                'sumber_id' => $id, 'sumber_tahun' => TahunKegiatan::dari($kegiatan), 'hasil_id' => $salinan['id'], 'tahun' => $tahun,
                'catatan' => $salinan['catatan'], 'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name'],
            ]);
            $hasil['dibuat'][] = ['id' => $id, 'hasil_id' => $salinan['id'], 'nama' => $salinan['nama'], 'catatan' => $salinan['catatan']];
        }

        $bagian = array_filter([
            count($hasil['dibuat']) ? count($hasil['dibuat'])." kegiatan diduplikat menjadi draf TA {$tahun}" : null,
            count($hasil['dilewati']) ? count($hasil['dilewati']).' dilewati (sudah ada)' : null,
            count($hasil['gagal']) ? count($hasil['gagal']).' gagal' : null,
        ]);

        return $hasil + ['pesan' => ucfirst(implode(', ', $bagian)).'.'];
    }
}
