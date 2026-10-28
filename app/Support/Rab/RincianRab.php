<?php

namespace App\Support\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use App\Support\Kegiatan\KunciKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Tambah, ubah, hapus, dan kembalikan (urungkan) satu rincian belanja RAB di server (PRD F11, RAB per sumber dana): isian
 * dirapikan (`rapikan()` — volume format Indonesia "1,5", harga rupiah berformat, spasi, kelompok ditebak dari uraian bila
 * kosong), diperiksa ValidasiItemBelanja, lalu ditulis lewat RabRepository. Setiap aksi ditolak bila kegiatan tidak ada
 * (RuntimeException), sudah terkunci statusnya (AuthorizationException → 403), atau sumber dananya bukan DIPA/Komite
 * (`pastikanDapatDiubah()`). Setiap perubahan mengembalikan dampaknya pada termin tersimpan (`termin`, DampakTermin).
 * Dipakai halaman RAB.
 */
final class RincianRab
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
     * @return array{id: string, item: array<string, mixed>, jumlah: int, termin: array{status: string, pesan: string, tipe: string}|null}
     */
    public static function tambah(int $kegiatanId, string $sumber, array $isian): array
    {
        $item = self::siapkan($kegiatanId, $sumber, $isian);
        $totalSebelum = DampakTermin::total($kegiatanId, $sumber);
        $id = app(RabRepository::class)->tambahItem($kegiatanId, $sumber, $item);

        return ['id' => $id, 'item' => $item, 'jumlah' => KalkulatorRab::jumlah($item['volume'], $item['harga_satuan']), 'termin' => DampakTermin::sesudahPerubahan($kegiatanId, $sumber, $totalSebelum)];
    }

    /**
     * Ganti isi item (id tetap); null bila item sudah tidak ada di RAB sumber dana itu (dihapus/dipindah di tempat lain).
     *
     * @param  array<string, mixed>  $isian
     * @return array{id: string, item: array<string, mixed>, jumlah: int, termin: array{status: string, pesan: string, tipe: string}|null}|null
     */
    public static function ubah(int $kegiatanId, string $sumber, string $id, array $isian): ?array
    {
        $item = self::siapkan($kegiatanId, $sumber, $isian);
        $totalSebelum = DampakTermin::total($kegiatanId, $sumber);

        return app(RabRepository::class)->ubahItem($kegiatanId, $sumber, $id, $item)
            ? ['id' => $id, 'item' => $item, 'jumlah' => KalkulatorRab::jumlah($item['volume'], $item['harga_satuan']), 'termin' => DampakTermin::sesudahPerubahan($kegiatanId, $sumber, $totalSebelum)]
            : null;
    }

    /**
     * Hapus item; mengembalikan posisi & isinya (untuk urungkan lewat kembalikan()) atau null bila sudah tidak ada.
     *
     * @return array{index: int, item: array<string, mixed>, termin: array{status: string, pesan: string, tipe: string}|null}|null
     */
    public static function hapus(int $kegiatanId, string $sumber, string $id): ?array
    {
        self::pastikanDapatDiubah($kegiatanId, $sumber);
        $totalSebelum = DampakTermin::total($kegiatanId, $sumber);
        $terhapus = app(RabRepository::class)->hapusItem($kegiatanId, $sumber, $id);

        return $terhapus === null ? null : $terhapus + ['termin' => DampakTermin::sesudahPerubahan($kegiatanId, $sumber, $totalSebelum)];
    }

    /**
     * Kembalikan item yang dihapus/dipindah ke posisinya semula (id sama; terkirim dua kali tidak menggandakan item).
     *
     * @param  array<string, mixed>  $item  Item lengkap dengan id, dari hapus().
     * @return array{status: string, pesan: string, tipe: string}|null Dampak pada termin tersimpan (DampakTermin).
     */
    public static function kembalikan(int $kegiatanId, string $sumber, int $index, array $item): ?array
    {
        self::pastikanDapatDiubah($kegiatanId, $sumber);
        $totalSebelum = DampakTermin::total($kegiatanId, $sumber);

        if (! in_array($item['id'] ?? null, array_column(app(RabRepository::class)->rincian($kegiatanId, $sumber), 'id'), true)) {
            app(RabRepository::class)->sisipkanItem($kegiatanId, $sumber, $index, $item);
        }

        return DampakTermin::sesudahPerubahan($kegiatanId, $sumber, $totalSebelum);
    }

    /**
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws AuthorizationException Status kegiatan mengunci RAB.
     * @throws ValidationException Sumber dana bukan DIPA/Komite.
     */
    public static function pastikanDapatDiubah(int $kegiatanId, string $sumber): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException('RAB kegiatan berstatus '.$status->label().' sehingga terkunci.');
        }
        KunciKegiatan::pastikanTahunTerbuka($kegiatan);
        if (SumberDana::tryFrom($sumber) === null) {
            throw ValidationException::withMessages(['sumber' => 'Sumber dana harus DIPA atau Komite.']);
        }
    }

    /**
     * @param  array<string, mixed>  $isian
     * @return array{kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int}
     */
    private static function siapkan(int $kegiatanId, string $sumber, array $isian): array
    {
        self::pastikanDapatDiubah($kegiatanId, $sumber);

        // Teks diperiksa sesudah dirapikan; volume & harga tetap mentah agar pesan salah ketiknya tepat ("harus berupa angka").
        $item = self::rapikan($isian);
        ValidasiItemBelanja::periksa(array_merge($isian, array_intersect_key($item, array_flip(['kode', 'uraian', 'kategori', 'satuan']))));

        return $item;
    }
}
