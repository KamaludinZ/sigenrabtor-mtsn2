<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Forms\KegiatanForm;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan kode & klasifikasi kegiatan (PRD F7: leading, RKTM, SK, IKSK, EDM, 5 Budaya Mutu) sebagai satu perubahan,
 * terpisah dari isian kegiatan lain: diperiksa dengan aturan ValidasiKegiatan untuk isian kode + kode wajib
 * (KegiatanForm::KODE_WAJIB), ditolak bila kode tersimpan berubah di tempat lain sejak dimuat, lalu dicatat di
 * riwayat_kode (maks. BATAS_RIWAYAT entri terbaru) dan penanda kode hilang gugur untuk kolom yang kini diisi
 * (KodeHilang::sisa). Dipakai halaman Kode & Klasifikasi (Kegiatan\Klasifikasi::simpan).
 */
final class SimpanKlasifikasi
{
    public const BATAS_RIWAYAT = 20;

    /**
     * Hasil: `tersimpan` (perubahan = kode yang berubah [lama, baru]), `tanpa_perubahan`, atau `bentrok` (perubahan = kode
     * yang diubah di tempat lain sejak dimuat; tidak ada yang disimpan).
     *
     * @param  array<string, mixed>  $kode  Isian kode (kunci RingkasanKode::ISIAN; lainnya diabaikan).
     * @param  array<string, mixed>|null  $kodeDimuat  Kode tersimpan saat halaman dimuat (RingkasanKode::nilaiBaku); null = timpa.
     * @param  array{name: string}  $pengguna  Akun yang menyimpan (pilihan leading & pencatat riwayat).
     * @return array{status: string, perubahan: array<string, array{0: mixed, 1: mixed}>}
     *
     * @throws ValidationException Kode tidak sah, berkunci isian (mis. `kode_iksk`).
     * @throws RuntimeException Kegiatan tidak ditemukan.
     */
    public static function simpan(int $kegiatanId, array $kode, ?array $kodeDimuat, array $pengguna): array
    {
        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $kodeTersimpan = RingkasanKode::nilaiBaku($tersimpan);
        $kodeBaru = RingkasanKode::nilaiBaku($kode);

        ValidasiKegiatan::periksa(array_merge($tersimpan, $kodeBaru), RingkasanKode::ISIAN, $pengguna, $tersimpan['leading'] ?? null);
        $wajib = array_filter(KegiatanForm::KODE_WAJIB, fn (string $pesan, string $isian) => blank($kodeBaru[$isian] ?? null), ARRAY_FILTER_USE_BOTH);
        if ($wajib !== []) {
            throw ValidationException::withMessages($wajib);
        }

        if ($kodeDimuat !== null && ($bentrok = RingkasanKode::selisih($kodeDimuat, $kodeTersimpan))) {
            return ['status' => 'bentrok', 'perubahan' => $bentrok];
        }

        $perubahan = RingkasanKode::selisih($kodeTersimpan, $kodeBaru);
        if ($perubahan === []) {
            return ['status' => 'tanpa_perubahan', 'perubahan' => []];
        }

        $riwayat = [...(array) ($tersimpan['riwayat_kode'] ?? []), ['pada' => now()->toIso8601String(), 'oleh' => $pengguna['name'], 'perubahan' => $perubahan]];
        $repo->simpan(array_merge($tersimpan, $kodeBaru, [
            'riwayat_kode' => array_slice($riwayat, -self::BATAS_RIWAYAT),
            'kode_hilang' => KodeHilang::sisa((array) ($tersimpan['kode_hilang'] ?? []), $kodeBaru),
        ]), $kegiatanId);

        return ['status' => 'tersimpan', 'perubahan' => $perubahan];
    }
}
