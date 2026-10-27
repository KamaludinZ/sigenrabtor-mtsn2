<?php

namespace App\Repositories;

use App\Contracts\RabRepository;
use App\Enums\SumberDana;
use App\Models\AnggaranKegiatan;
use App\Models\ItemBelanja;
use App\Models\Termin;
use App\Support\Terbilang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RAB di PostgreSQL (budget_items & termin_pembayaran milik anggaran_kegiatan), padanan StubRabRepository dengan bentuk
 * data yang sama. Baris anggaran sumber dana yang belum dipilih kegiatan dibuat dengan `dipilih = false` saat RAB-nya
 * pertama ditulis. Setiap perubahan rincian memperbarui salinan total_rab & terbilang di anggaran_kegiatan dalam
 * transaksi yang sama (jumlah per baris dihitung database).
 */
class EloquentRabRepository implements RabRepository
{
    /** Kolom isian item (selain id) yang disalin dari/ke bentuk data RabRepository. */
    private const ISIAN = ['kode', 'uraian', 'kategori', 'volume', 'satuan', 'harga_satuan'];

    public function rincian(int $kegiatanId, string $sumber): array
    {
        return $this->anggaran($kegiatanId, $sumber)?->rincian->map(fn (ItemBelanja $i) => self::keItem($i))->all() ?? [];
    }

    public function tambahItem(int $kegiatanId, string $sumber, array $item): string
    {
        return DB::transaction(function () use ($kegiatanId, $sumber, $item) {
            $anggaran = $this->anggaranUntukDitulis($kegiatanId, $sumber);
            $id = (string) Str::uuid();

            $anggaran->rincian()->create(['id' => $id, 'urutan' => $anggaran->rincian()->max('urutan') + 1] + self::isian($item));
            $this->hitungUlang($anggaran);

            return $id;
        });
    }

    public function ubahItem(int $kegiatanId, string $sumber, string $id, array $item): bool
    {
        return DB::transaction(function () use ($kegiatanId, $sumber, $id, $item) {
            $anggaran = $this->anggaran($kegiatanId, $sumber);
            $baris = $anggaran ? $this->item($anggaran, $id) : null;

            if ($baris === null) {
                return false;
            }

            $baris->update(self::isian($item));
            $this->hitungUlang($anggaran);

            return true;
        });
    }

    public function hapusItem(int $kegiatanId, string $sumber, string $id): ?array
    {
        return DB::transaction(function () use ($kegiatanId, $sumber, $id) {
            $anggaran = $this->anggaran($kegiatanId, $sumber);
            $baris = $anggaran ? $this->item($anggaran, $id) : null;

            if ($baris === null) {
                return null;
            }

            $index = $anggaran->rincian()->where('urutan', '<', $baris->urutan)->count();
            $item = self::keItem($baris);
            $baris->delete();
            $anggaran->rincian()->where('urutan', '>', $baris->urutan)->decrement('urutan');
            $this->hitungUlang($anggaran);

            return ['index' => $index, 'item' => $item];
        });
    }

    public function sisipkanItem(int $kegiatanId, string $sumber, int $index, array $item): void
    {
        DB::transaction(function () use ($kegiatanId, $sumber, $index, $item) {
            $anggaran = $this->anggaranUntukDitulis($kegiatanId, $sumber);
            $id = Str::isUuid((string) ($item['id'] ?? '')) ? (string) $item['id'] : (string) Str::uuid();

            // Urungkan yang terkirim dua kali tidak menggandakan item.
            if (ItemBelanja::query()->whereKey($id)->exists()) {
                return;
            }

            $urutan = max(0, min($index, $anggaran->rincian()->count())) + 1;
            $anggaran->rincian()->where('urutan', '>=', $urutan)->increment('urutan');
            $anggaran->rincian()->create(['id' => $id, 'urutan' => $urutan] + self::isian($item));
            $this->hitungUlang($anggaran);
        });
    }

    public function termin(int $kegiatanId, string $sumber): array
    {
        return $this->anggaran($kegiatanId, $sumber)?->termin
            ->map(fn (Termin $t) => ['tanggal' => $t->tanggal, 'nominal' => $t->nominal])->all() ?? [];
    }

    public function simpanTermin(int $kegiatanId, string $sumber, array $termin): void
    {
        DB::transaction(function () use ($kegiatanId, $sumber, $termin) {
            $anggaran = $this->anggaranUntukDitulis($kegiatanId, $sumber);

            $anggaran->termin()->delete();
            foreach (array_values($termin) as $i => $t) {
                $anggaran->termin()->create(['nomor_termin' => $i + 1, 'tanggal' => filled($t['tanggal'] ?? null) ? $t['tanggal'] : null, 'nominal' => (int) $t['nominal']]);
            }
            $anggaran->touch();
        });
    }

    public function sumberTersimpan(int $kegiatanId): array
    {
        $ada = AnggaranKegiatan::query()->where('activity_id', $kegiatanId)
            ->where(fn ($q) => $q->has('rincian')->orHas('termin'))
            ->pluck('sumber_dana')->all();

        return array_values(array_filter(array_column(SumberDana::cases(), 'value'), fn (string $s) => in_array($s, $ada, true)));
    }

    private function anggaran(int $kegiatanId, string $sumber): ?AnggaranKegiatan
    {
        return AnggaranKegiatan::query()->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->first();
    }

    /** Baris anggaran yang dikunci untuk ditulis; dibuat (belum dipilih) bila sumber dana itu belum punya baris. */
    private function anggaranUntukDitulis(int $kegiatanId, string $sumber): AnggaranKegiatan
    {
        return AnggaranKegiatan::query()->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->lockForUpdate()->first()
            ?? AnggaranKegiatan::query()->create(['activity_id' => $kegiatanId, 'sumber_dana' => $sumber, 'dipilih' => false]);
    }

    private function item(AnggaranKegiatan $anggaran, string $id): ?ItemBelanja
    {
        return Str::isUuid($id) ? $anggaran->rincian()->whereKey($id)->first() : null;
    }

    /** Salinan total_rab & terbilang dari jumlah tiap baris (dihitung database). */
    private function hitungUlang(AnggaranKegiatan $anggaran): void
    {
        $total = (int) $anggaran->rincian()->sum('jumlah');
        $anggaran->fill(['total_rab' => $total, 'terbilang' => $total > 0 ? Terbilang::rupiah($total) : null])->touch();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function isian(array $item): array
    {
        return ['kode' => (string) ($item['kode'] ?? '')] + array_intersect_key($item, array_flip(self::ISIAN));
    }

    /** @return array{id: string, kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int} */
    private static function keItem(ItemBelanja $item): array
    {
        $volume = (float) $item->volume;

        return [
            'id' => $item->id,
            'kode' => $item->kode,
            'uraian' => $item->uraian,
            'kategori' => $item->kategori,
            'volume' => floor($volume) === $volume ? (int) $volume : $volume,
            'satuan' => $item->satuan,
            'harga_satuan' => $item->harga_satuan,
        ];
    }
}
