<?php

namespace App\Contracts;

/**
 * Riwayat dokumen (PRD §7 Riwayat Dokumen, F28; tabel `riwayat_dokumen` migrasi 000043, model BerkasDokumenTercatat): satu catatan per berkas yang pernah
 * dicetak, diunduh, atau diarsipkan — kegiatan × jenis DokumenKegiatan::JENIS × sumber dana × format — hanya tambah,
 * bernomor versi per kegiatan × jenis × sumber × format. Cuplikan kegiatan (nama, leading, tahun) & isi ringkas ikut
 * tersimpan agar catatan tetap terbaca walau kegiatannya dihapus atau berasal dari tahun lain. Bentuk (disusun
 * App\Support\Dokumen\RiwayatDokumen::entri()): {id, kegiatan_id|null, tahun_anggaran, kode_leading, nama_kegiatan, jenis,
 * sumber|null, format pdf|docx|xlsx, asal cetak|unduh|arsip, nama_berkas, ukuran|null, status (StatusKegiatan saat itu),
 * bertanda_draf, nomor_dokumen|null, versi, ringkas {total_rab|null, bagian_lengkap|null, jumlah_bagian|null},
 * user_id|null, dibuat_oleh, dibuat_pada (ISO 8601 bermikrodetik zona aplikasi)}. `catat()` melempar RuntimeException bila
 * gagal ditulis.
 */
interface RiwayatDokumenRepository
{
    /**
     * Semua catatan, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function semua(): array;

    /** @return array<string, mixed>|null */
    public function temukan(string $id): ?array;

    /**
     * Tambah satu catatan (id, versi berikutnya, dan waktu diisi repositori).
     *
     * @param  array<string, mixed>  $entri  Tanpa id, versi & dibuat_pada.
     * @return array<string, mixed>
     */
    public function catat(array $entri): array;
}
