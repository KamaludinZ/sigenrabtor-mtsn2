<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan catatan konteks satu kegiatan di server (PRD §9, simpan otomatis Data Kegiatan): hanya kolom konteks yang
 * ditulis — isian lain tidak disentuh — setelah spasinya dirapikan (Teks::rapikan) dan diperiksa aturan
 * ValidasiKegiatan (maks. DataKonteks::BATAS karakter). Teks yang sama dengan yang tersimpan tidak ditulis ulang.
 * Ditolak bila kegiatan tidak ada (RuntimeException) atau statusnya mengunci isian (AuthorizationException → 403).
 */
final class SimpanKonteks
{
    /**
     * Hasil: `berubah` (ditulis atau sama dengan yang tersimpan), teks yang tersimpan, dan `diperbarui_pada` = waktu
     * konteks terakhir berubah (null di penyimpanan tiruan).
     *
     * @return array{berubah: bool, konteks: string, diperbarui_pada: string|null}
     *
     * @throws ValidationException Berkunci `konteks`.
     */
    public static function simpan(int $kegiatanId, string $teks): array
    {
        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $status = StatusKegiatan::tryFrom((string) ($tersimpan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException('Kegiatan berstatus '.$status->label().' sehingga isiannya terkunci.');
        }

        $bersih = Teks::rapikan($teks);
        ValidasiKegiatan::periksa(['konteks' => $bersih] + $tersimpan, ['konteks']);

        $berubah = (string) ($tersimpan['konteks'] ?? '') !== $bersih;
        if ($berubah) {
            $repo->simpan(array_merge($tersimpan, ['konteks' => $bersih]), $kegiatanId);
            $tersimpan = $repo->temukan($kegiatanId) ?? $tersimpan;
        }

        return ['berubah' => $berubah, 'konteks' => $bersih, 'diperbarui_pada' => $tersimpan['konteks_diperbarui_pada'] ?? null];
    }
}
