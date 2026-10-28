<?php

namespace App\Contracts;

/**
 * Draf dokumen kegiatan (PRD F4 "Simpan Draf"; calon tabel `documents` — satu baris per kegiatan × jenis
 * DokumenKegiatan::JENIS × sumber dana, `status`, `bertanda_draf`, `konten`): titik lanjut penyusunan yang disimpan
 * leading — jenis & sumber dana yang sedang disusun, cap DRAF & kelengkapan saat itu, dan sidik isi tiap bagian untuk
 * mengenali bagian yang berubah sesudahnya. Isi dokumen sendiri selalu disusun dari data terbaru. Menyimpan lagi jenis ×
 * sumber yang sama menimpanya. Bentuk draf disusun App\Support\Dokumen\DrafDokumen::rekam(): {id (uuid), kegiatan_id,
 * jenis, sumber|null, status (StatusKegiatan saat disimpan), bertanda_draf, alasan_draf (teks StatusDraf), lengkap &
 * jumlah_bagian (SusunanOtomatis; null tanpa KAK/TOR), total_rab|null, sidik {kak: {nomor: md5}, rab: md5, cover: md5},
 * user_id, oleh, disimpan_pada}. `simpan()`/`hapus()`/`perbaruiStatus()` melempar RuntimeException bila penyimpanan gagal ditulis.
 */
interface DrafDokumenRepository
{
    /**
     * Semua draf satu kegiatan, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function untukKegiatan(int $kegiatanId): array;

    /** @return array<string, mixed>|null */
    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array;

    /**
     * Simpan (atau timpa) draf kegiatan × jenis × sumber; `id` tetap bila menimpa, `disimpan_pada` ISO 8601 bermikrodetik
     * zona aplikasi.
     *
     * @param  array<string, mixed>  $draf  Hasil DrafDokumen::rekam() (tanpa id & waktu).
     * @return array<string, mixed>
     */
    public function simpan(int $kegiatanId, array $draf): array;

    /** @return bool false bila draf tidak ada. */
    public function hapus(int $kegiatanId, string $jenis, ?string $sumber): bool;

    /**
     * Perbarui status kegiatan & cap DRAF draf yang ada (sesudah status kegiatan berpindah, StatusDokumen::sinkronkan);
     * catatan lain — sidik, kelengkapan, penyimpan, `disimpan_pada` — tetap.
     *
     * @param  list<string>  $alasanDraf
     * @return bool false bila draf tidak ada.
     */
    public function perbaruiStatus(int $kegiatanId, string $jenis, ?string $sumber, string $status, bool $bertandaDraf, array $alasanDraf): bool;
}
