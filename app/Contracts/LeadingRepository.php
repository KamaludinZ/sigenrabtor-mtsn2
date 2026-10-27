<?php

namespace App\Contracts;

/**
 * Leading = unit penerima mandat (PRD tabel leadings, F30): kode urut (1.1, 1.6, …), nama unit, jabatan penanda tangan,
 * urutan tampil, dan status aktif. Leading nonaktif tidak bisa dipilih lagi tetapi kegiatan lamanya tetap terbaca.
 * Tahap frontend memakai tiruan (berkas JSON); backend menggantinya dengan Eloquent tanpa mengubah komponen.
 */
interface LeadingRepository
{
    /**
     * Semua leading (aktif & nonaktif), urut `urutan`.
     *
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: int, is_active: bool}>
     */
    public function semua(): array;

    /**
     * Tambah ($kodeLama null) atau ubah leading berkode $kodeLama.
     *
     * @param  array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan?: int, is_active?: bool}  $data
     *
     * @throws \RuntimeException Penyimpanan gagal.
     */
    public function simpan(array $data, ?string $kodeLama = null): void;
}
