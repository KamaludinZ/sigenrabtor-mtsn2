<?php

namespace App\Support\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Support\Kegiatan\KalimatKak;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelola paragraf latar belakang baku & kalimat penutup per tahun anggaran di server (PRD §13; ubah hanya admin, tahun
 * terkunci hanya dilihat) — dipakai editor latar belakang `Pengaturan\DasarHukum` dan endpoint
 * `pengaturan.madrasah.latar_belakang.data`. Teks disimpan mentah berpenanda: dirapikan LatarBelakangBaku::rapikan
 * (satu paragraf) lalu diperiksa `galatParagraf()`/`galatPenutup()` (galat berkunci `latar_belakang`/`kalimat_penutup`);
 * butir dasar hukum tahun itu tetap. `$versi` usang → PengaturanBerubah; tanpa perubahan tidak disimpan; riwayat
 * `ubah_latar_belakang` hanya bila tersimpan.
 */
final class KelolaLatarBelakang
{
    /**
     * @param  array<string, mixed>  $isian  {latar_belakang, kalimat_penutup} teks mentah berpenanda.
     * @param  array{role: string}  $pengguna
     * @return array{latar_belakang: string, kalimat_penutup: string, berubah: list<string>} `berubah` kosong = tidak disimpan.
     *
     * @throws ValidationException
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     * @throws PengaturanBerubah
     */
    public static function simpan(int $tahun, array $isian, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        $rapi = [
            'latar_belakang' => LatarBelakangBaku::rapikan((string) ($isian['latar_belakang'] ?? '')),
            'kalimat_penutup' => LatarBelakangBaku::rapikan((string) ($isian['kalimat_penutup'] ?? '')),
        ];
        $galat = array_filter([
            'latar_belakang' => LatarBelakangBaku::galatParagraf($rapi['latar_belakang']),
            'kalimat_penutup' => LatarBelakangBaku::galatPenutup($rapi['kalimat_penutup']),
        ]);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $repo = app(DasarHukumRepository::class);
        $lama = $repo->untukTahun($tahun);
        if ($versi !== null && $lama['diperbarui_pada'] !== $versi) {
            throw new PengaturanBerubah("Dasar hukum & latar belakang TA {$tahun} baru saja diubah di tempat lain.");
        }
        $berubah = array_keys(array_filter([
            'paragraf baku' => $rapi['latar_belakang'] !== $lama['latar_belakang'],
            'kalimat penutup' => $rapi['kalimat_penutup'] !== $lama['kalimat_penutup'],
        ]));
        if ($berubah === []) {
            return $rapi + ['berubah' => []];
        }

        $repo->simpan($tahun, $rapi + ['dasar_hukum' => $lama['dasar_hukum']]);
        $keterangan = "Latar belakang TA {$tahun} — ubah ".implode(' & ', $berubah);
        if (in_array('kalimat penutup', $berubah, true)) {
            $keterangan .= ': '.Str::limit($rapi['kalimat_penutup'], 120);
        }
        RiwayatPengaturan::catat('ubah_latar_belakang', $keterangan);

        return $rapi + ['berubah' => $berubah];
    }

    /**
     * Latar belakang baku satu tahun untuk endpoint: teks mentah, teks siap cetak (tahun terisi; kalimat penutup terpotong
     * di penanda), teks bawaan, penanda yang boleh, batas panjang, dan — bila $kegiatan diberikan — kalimat penutup
     * untuk kegiatan itu persis seperti tercetak di KAK.
     *
     * @param  array<string, mixed>|null  $kegiatan
     * @return array<string, mixed>
     */
    public static function data(int $tahun, ?array $kegiatan = null): array
    {
        $repo = app(DasarHukumRepository::class);
        $pengaturan = $repo->untukTahun($tahun);

        return [
            'tahun' => $tahun,
            'terkunci' => KelolaDasarHukum::terkunci($tahun),
            'diatur' => $pengaturan['diatur'],
            'diperbarui_pada' => $pengaturan['diperbarui_pada'],
            'latar_belakang' => $pengaturan['latar_belakang'],
            'kalimat_penutup' => $pengaturan['kalimat_penutup'],
            'cetak' => [
                'latar_belakang' => TeksBaku::isi($pengaturan['latar_belakang'], $tahun),
                'kalimat_penutup' => TeksBaku::potong(TeksBaku::isi($pengaturan['kalimat_penutup'], $tahun)),
            ],
            'bawaan' => $repo->teksBawaan(),
            'penanda' => ['latar_belakang' => [TeksBaku::TAHUN], 'kalimat_penutup' => TeksBaku::bolehPenutup()],
            'variabel_kegiatan' => TeksBaku::VARIABEL_KEGIATAN,
            'maks' => ['latar_belakang' => LatarBelakangBaku::MAKS_PARAGRAF, 'kalimat_penutup' => LatarBelakangBaku::MAKS_PENUTUP],
            ...($kegiatan !== null ? ['kegiatan' => [
                'id' => $kegiatan['id'] ?? null,
                'kalimat_penutup' => KalimatKak::latarBelakangKegiatan($kegiatan, $tahun),
                'kosong' => TeksBaku::kosong($pengaturan['kalimat_penutup'], TeksBaku::nilaiKegiatan($kegiatan)),
            ]] : []),
        ];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Latar belakang baku diatur admin.');
        }
        if (KelolaDasarHukum::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — latar belakangnya hanya bisa dilihat.");
        }
    }
}
