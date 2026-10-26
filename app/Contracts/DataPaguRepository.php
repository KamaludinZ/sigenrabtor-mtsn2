<?php

namespace App\Contracts;

/**
 * Data Pagu per tahun anggaran: daftar nama kegiatan beserta pagu DIPA/Komite (dari cascade Perkin) yang dipilih saat
 * membuat kegiatan sehingga nama & pagu tidak diketik ulang. Tahap frontend memakai tiruan (berkas JSON); backend
 * menggantinya dengan Eloquent tanpa mengubah komponen.
 */
interface DataPaguRepository
{
    /**
     * Semua data pagu tahun $tahun, urut nama.
     *
     * @return list<array{id: string, tahun: int, nama_kegiatan: string, leading: string|null, pagu: array<string, int|null>, asal: string, dibuat_pada: string|null, diperbarui_pada: string|null}>
     */
    public function semua(int $tahun): array;

    /**
     * @return array{id: string, tahun: int, nama_kegiatan: string, leading: string|null, pagu: array<string, int|null>, asal: string, dibuat_pada: string|null, diperbarui_pada: string|null}|null
     */
    public function temukan(string $id): ?array;

    /**
     * Tambah (id null) atau ubah satu data pagu; kembalikan id-nya.
     *
     * @param  array{tahun: int, nama_kegiatan: string, leading: string|null, pagu: array<string, int|null>, asal?: string}  $data
     */
    public function simpan(array $data, ?string $id = null): string;

    public function hapus(string $id): void;
}
