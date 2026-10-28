<?php

namespace App\Contracts;

/**
 * Hasil pembuatan dokumen otomatis (PRD F13; calon tabel `documents` + `dokumen_bagian`): satu hasil per kegiatan × jenis
 * DokumenKegiatan::JENIS × sumber dana, ditimpa setiap kali disusun ulang. Bentuk (disusun App\Support\Dokumen\PembuatDokumen):
 * {id (teks), kegiatan_id, jenis, sumber|null, status (StatusKegiatan saat disusun), bertanda_draf, alasan_draf,
 * sidik_isi (md5), bagian (baris HasilKakTor — kosong untuk RAB), rab (cuplikan RAB|null), cover (nilai kotak cover|null),
 * user_id, oleh, disusun_pada (ISO 8601 bermikrodetik zona aplikasi)}. `simpan()`/`perbaruiStatus()` melempar RuntimeException
 * bila gagal ditulis.
 */
interface HasilDokumenRepository
{
    /** @return array<string, mixed>|null */
    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array;

    /**
     * Semua hasil satu kegiatan, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function untukKegiatan(int $kegiatanId): array;

    /**
     * Simpan (atau timpa) hasil kegiatan × jenis × sumber; `id` tetap bila menimpa.
     *
     * @param  array<string, mixed>  $hasil  Tanpa id & disusun_pada.
     * @return array<string, mixed>
     */
    public function simpan(int $kegiatanId, array $hasil): array;

    /**
     * Perbarui status kegiatan & cap DRAF hasil yang tidak bisa disusun ulang (mis. sumber dananya tidak dipilih lagi,
     * StatusDokumen::sinkronkan); isi & `disusun_pada` tetap.
     *
     * @param  list<string>  $alasanDraf
     * @return bool false bila hasil tidak ada.
     */
    public function perbaruiStatus(int $kegiatanId, string $jenis, ?string $sumber, string $status, bool $bertandaDraf, array $alasanDraf): bool;
}
