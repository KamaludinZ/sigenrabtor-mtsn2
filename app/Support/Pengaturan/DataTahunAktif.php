<?php

namespace App\Support\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Data tahun anggaran aktif untuk endpoint `tahun_aktif` (`GET /tahun-aktif`, semua pengguna; PRD F6 & aturan 3 "Satu
 * tahun untuk semua"): tahun yang dipakai seluruh aplikasi (StubData::tahunAktif — `tercatat` false bila belum ada tahun
 * aktif tersimpan dan aplikasi memakai tahun bawaan), terkunci, SK RKTM/RKAM (SkRktm), jejak aktivasi, master kode
 * tersedia, jumlah kegiatan yang boleh dilihat akun itu (AksesKegiatan), bagian yang mengikuti tahun aktif beserta
 * kesiapannya (KontrolTahunAktif::dipakai), dan pilihan tahun — lengkap untuk admin (jumlah kegiatan & jejak aktivasi,
 * kesiapan wajib/opsional, `bisa_ganti`), ringkas untuk akun leading (hanya melihat).
 */
final class DataTahunAktif
{
    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $tersimpan = app(TahunAnggaranRepository::class)->aktif();
        $sk = SkRktm::untuk($tahun);
        $admin = $pengguna['role'] === 'admin';
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun);

        $data = [
            'tahun' => $tahun,
            'tercatat' => $tersimpan !== null,
            'terkunci' => (bool) ($tersimpan['is_locked'] ?? false),
            'sk_rktm' => ['nomor' => $sk['nomor'], 'tanggal' => $sk['tanggal'], 'tanggal_teks' => $sk['tanggal'] ? Format::tanggal($sk['tanggal']) : null, 'lengkap' => SkRktm::lengkap($tahun)],
            'diaktifkan' => ['oleh' => $tersimpan['diaktifkan_oleh'] ?? null, 'pada' => $tersimpan['diaktifkan_pada'] ?? null],
            'master_tersedia' => MasterKode::tersedia($tahun),
            'jumlah_kegiatan' => count(AksesKegiatan::saring($kegiatan, $pengguna)),
            'dipakai' => KontrolTahunAktif::dipakai(),
            'bisa_ganti' => $admin,
            'pilihan' => array_map(fn (array $t) => $admin ? $t : array_intersect_key($t, array_flip(['tahun', 'is_active', 'is_locked', 'master_tersedia'])), KontrolTahunAktif::pilihan()),
        ];

        if ($admin) {
            $kesiapan = KesiapanPengaturan::tahunAktif();
            $data['kesiapan'] = ['wajib_siap' => $kesiapan->jumlahWajibSiap(), 'wajib' => $kesiapan->jumlahWajib(), 'butir' => $kesiapan->butir];
        }

        return $data;
    }
}
