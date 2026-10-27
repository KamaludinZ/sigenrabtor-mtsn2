<?php

namespace App\Support\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Tambah & ubah satu rincian belanja RAB di server (PRD F11, RAB per sumber dana): isian dirapikan (`rapikan()` —
 * volume format Indonesia "1,5", harga rupiah berformat, spasi, kelompok ditebak dari uraian bila kosong), diperiksa
 * ValidasiItemBelanja, lalu disimpan lewat RabRepository. Ditolak bila kegiatan tidak ada (RuntimeException), sudah
 * terkunci statusnya (AuthorizationException → 403), atau sumber dananya bukan DIPA/Komite. Dipakai halaman RAB.
 */
final class SimpanRincianRab
{
    /**
     * Bentuk simpan item: {kode, uraian, kategori, volume (int bila bulat, 2 desimal), satuan, harga_satuan}.
     *
     * @param  array<string, mixed>  $isian
     * @return array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}
     */
    public static function rapikan(array $isian): array
    {
        $uraian = trim(preg_replace('/\s+/u', ' ', (string) ($isian['uraian'] ?? '')));
        $volume = ItemBelanjaForm::angkaVolume((string) ($isian['volume'] ?? '')) ?? 0;

        return [
            'kode' => trim((string) ($isian['kode'] ?? '')),
            'uraian' => $uraian,
            'kategori' => filled($isian['kategori'] ?? null) ? (string) $isian['kategori'] : ItemBelanjaForm::tebakKategori($uraian),
            'volume' => floor($volume) == $volume ? (int) $volume : round($volume, 2),
            'satuan' => trim((string) ($isian['satuan'] ?? '')),
            'harga_satuan' => (int) Format::parseRupiah($isian['harga_satuan'] ?? null),
        ];
    }

    /**
     * Tambah item di akhir RAB sumber dana itu.
     *
     * @param  array<string, mixed>  $isian
     * @return array{id: string, item: array<string, mixed>, jumlah: int}
     */
    public static function tambah(int $kegiatanId, string $sumber, array $isian): array
    {
        $item = self::siapkan($kegiatanId, $sumber, $isian);
        $id = app(RabRepository::class)->tambahItem($kegiatanId, $sumber, $item);

        return ['id' => $id, 'item' => $item, 'jumlah' => KalkulatorRab::jumlah($item['volume'], $item['harga_satuan'])];
    }

    /**
     * Ganti isi item (id tetap); null bila item sudah tidak ada di RAB sumber dana itu (dihapus/dipindah di tempat lain).
     *
     * @param  array<string, mixed>  $isian
     * @return array{id: string, item: array<string, mixed>, jumlah: int}|null
     */
    public static function ubah(int $kegiatanId, string $sumber, string $id, array $isian): ?array
    {
        $item = self::siapkan($kegiatanId, $sumber, $isian);

        return app(RabRepository::class)->ubahItem($kegiatanId, $sumber, $id, $item)
            ? ['id' => $id, 'item' => $item, 'jumlah' => KalkulatorRab::jumlah($item['volume'], $item['harga_satuan'])]
            : null;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @return array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}
     */
    private static function siapkan(int $kegiatanId, string $sumber, array $isian): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException('RAB kegiatan berstatus '.$status->label().' sehingga terkunci.');
        }
        if (SumberDana::tryFrom($sumber) === null) {
            throw ValidationException::withMessages(['sumber' => 'Sumber dana harus DIPA atau Komite.']);
        }

        // Teks diperiksa sesudah dirapikan; volume & harga tetap mentah agar pesan salah ketiknya tepat ("harus berupa angka").
        $item = self::rapikan($isian);
        ValidasiItemBelanja::periksa(array_merge($isian, array_intersect_key($item, array_flip(['kode', 'uraian', 'kategori', 'satuan']))));

        return $item;
    }
}
