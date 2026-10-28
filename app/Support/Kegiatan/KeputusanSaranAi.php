<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pencatatan keputusan atas satu permintaan saran AI lewat API (PRD F25, endpoint `saran_ai.putuskan`): hanya akun peminta
 * yang kegiatannya masih dalam cakupan (AksesKegiatan), hanya sekali (sudah diputuskan → PengaturanBerubah/409). Nomor
 * versi 1.. dipakai (versi lain ditolak) atau null = semua ditolak; `teks_dipakai` opsional untuk versi kalimat yang
 * dipilih — dirapikan (nama cover kapital satu baris), ≤ SaranKalimatAi::MAKS_TEKS, berbeda dari saran = "diedit".
 * Usulan jadwal dipakai apa adanya. Kalimat KAK & Matrik & Jadwal mencatat keputusannya langsung lewat RiwayatSaranAi.
 */
final class KeputusanSaranAi
{
    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed> DataRiwayatSaranAi::baris sesudah diputuskan.
     *
     * @throws RuntimeException Permintaan tidak ada.
     * @throws AuthorizationException
     * @throws PengaturanBerubah Sudah diputuskan.
     * @throws ValidationException Berkunci `versi` atau `teks_dipakai`.
     */
    public static function putuskan(string $id, mixed $nomor, mixed $teksDipakai, array $pengguna): array
    {
        $repo = app(RiwayatSaranAiRepository::class);
        $permintaan = $repo->temukan($id) ?? throw new RuntimeException('Saran AI tidak ditemukan.');
        $kegiatan = app(KegiatanRepository::class)->temukan($permintaan['kegiatan_id']);
        if ($kegiatan === null || ! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if ((int) $permintaan['user_id'] !== (int) $pengguna['id']) {
            throw new AuthorizationException('Hanya akun yang meminta saran ini yang bisa memutuskannya.');
        }
        if ($permintaan['diputuskan_pada'] !== null) {
            throw new PengaturanBerubah('Saran ini sudah diputuskan — minta saran baru bila perlu.');
        }

        $jumlah = count($permintaan['versi']);
        $nomor = $nomor === null || $nomor === '' ? null : filter_var($nomor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => max(1, $jumlah)]]);
        $teks = is_string($teksDipakai) ? self::rapikan($permintaan['bagian'], $teksDipakai) : '';
        $maks = SaranKalimatAi::MAKS_TEKS[$permintaan['bagian']] ?? null;
        $galat = array_filter([
            'versi' => $nomor === false ? "Pilih nomor versi 1–{$jumlah}, atau kosongkan untuk menolak semua versi." : null,
            'teks_dipakai' => match (true) {
                $teksDipakai !== null && ! is_string($teksDipakai) => 'Teks yang dipakai harus berupa teks.',
                $teks === '' => null,
                $nomor === null => 'Teks yang dipakai hanya untuk versi yang dipilih.',
                $maks === null => 'Usulan jadwal dipakai apa adanya — sunting barisnya di Matrik & Jadwal.',
                mb_strlen($teks) > $maks => "Teks yang dipakai maksimal {$maks} karakter.",
                default => null,
            },
        ]);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        RiwayatSaranAi::putuskan($id, $nomor === null ? null : $nomor - 1, $teks !== '' ? $teks : null);

        return DataRiwayatSaranAi::baris($repo->temukan($id) + ['nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')), 'leading' => $kegiatan['leading'] ?? null]);
    }

    private static function rapikan(string $bagian, string $teks): string
    {
        return $bagian === 'nama_cover' ? mb_strtoupper(Teks::rapikan((string) preg_replace('/\s+/u', ' ', $teks))) : Teks::rapikan($teks);
    }
}
