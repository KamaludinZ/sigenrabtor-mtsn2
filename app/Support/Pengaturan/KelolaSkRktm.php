<?php

namespace App\Support\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Kelola nomor & tanggal SK RKTM/RKAM per tahun anggaran di server (PRD §13, kolom tahun_anggaran.no_sk_rktm/
 * tanggal_sk_rktm; ubah hanya admin, tahun terkunci hanya dilihat) — dipakai kartu SK di Pengaturan › Dasar hukum
 * (`Pengaturan\DasarHukum::simpanSk`) dan endpoint `pengaturan.madrasah.sk_rktm.data`. Aturan isian lewat SkRktm
 * (nomor dirapikan ≤ MAKS_NOMOR, tanggal Y-m-d sah, keduanya diisi bersama; keduanya kosong = dikosongkan); tanggal di
 * luar akhir tahun sebelumnya s.d. tahun anggarannya hanya peringatan. Tanpa perubahan tidak disimpan; riwayat
 * `ubah_sk_rktm` berisi ringkasan "Nomor/Tanggal: lama → baru".
 */
final class KelolaSkRktm
{
    /**
     * @param  array{role: string}  $pengguna
     * @return array{sk: array{nomor: string|null, tanggal: string|null}, ringkasan: string|null, peringatan: string|null} ringkasan null = tidak berubah (tidak disimpan).
     *
     * @throws ValidationException Berkunci `nomor`/`tanggal`, atau `tahun` bila tahunnya belum dibuat.
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, string $nomor, string $tanggal, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('SK RKTM/RKAM diatur admin.');
        }
        if (KelolaDasarHukum::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — SK-nya hanya bisa dilihat.");
        }
        $nomor = SkRktm::rapikanNomor($nomor);
        $tanggal = trim($tanggal);
        if (($galat = SkRktm::galat($nomor, $tanggal)) !== []) {
            throw ValidationException::withMessages($galat);
        }

        $lama = SkRktm::untuk($tahun);
        $baru = ['nomor' => $nomor ?: null, 'tanggal' => $tanggal ?: null];
        $tampil = fn (array $sk) => ['nomor' => $sk['nomor'], 'tanggal' => $sk['tanggal'] ? Format::tanggal($sk['tanggal']) : null];
        $ringkasan = RiwayatPengaturan::perubahan($tampil($lama), $tampil($baru), ['nomor' => 'Nomor', 'tanggal' => 'Tanggal']);
        if ($ringkasan !== null) {
            try {
                app(TahunAnggaranRepository::class)->simpanSk($tahun, $baru['nomor'], $baru['tanggal']);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$tahun} belum dibuat."]);
            }
            RiwayatPengaturan::catat('ubah_sk_rktm', "SK RKTM/RKAM TA {$tahun} — {$ringkasan}");
        }

        return ['sk' => $baru, 'ringkasan' => $ringkasan, 'peringatan' => SkRktm::peringatanTanggal($baru['tanggal'], $tahun)];
    }

    /**
     * SK satu tahun untuk endpoint: nomor, tanggal (Y-m-d & terbaca), lengkap, peringatan tanggal, butir dasar hukum yang
     * memakai penanda SK, dan apakah dokumen masih kurang SK.
     *
     * @return array<string, mixed>
     */
    public static function data(int $tahun): array
    {
        $sk = SkRktm::untuk($tahun);
        $butir = array_column(app(DasarHukumRepository::class)->untukTahun($tahun)['dasar_hukum'], 'teks');

        return [
            'tahun' => $tahun,
            'terkunci' => KelolaDasarHukum::terkunci($tahun),
            'nomor' => $sk['nomor'],
            'tanggal' => $sk['tanggal'],
            'tanggal_cetak' => SkRktm::nilai($tahun)[SkRktm::TANGGAL],
            'lengkap' => SkRktm::lengkap($tahun),
            'peringatan' => SkRktm::peringatanTanggal($sk['tanggal'], $tahun),
            'penanda' => SkRktm::PENANDA,
            'butir_memakai' => array_values(array_map(fn (int $i) => $i + 1, array_keys(array_filter($butir, SkRktm::dipakai(...))))),
            'kurang' => DaftarDasarHukum::skKurang($butir, $tahun),
        ];
    }
}
