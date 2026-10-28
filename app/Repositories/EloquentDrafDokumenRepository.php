<?php

namespace App\Repositories;

use App\Contracts\DrafDokumenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Draf dokumen di PostgreSQL (tabel documents, migrasi 000032), padanan StubDrafDokumenRepository: satu baris per kegiatan ×
 * jenis × sumber dana (indeks unik NULLS NOT DISTINCT); `status` & `bertanda_draf` = keadaan saat draf disimpan, catatan
 * draf lainnya (alasan, kelengkapan, total, sidik, penyimpan, waktu bermikrodetik) di `konten.draf` sehingga kunci konten
 * lain (isi yang disunting, kelak) tidak tersentuh. Tahun dokumen = tahun kegiatannya; sumber dana dipasangkan dengan baris
 * anggaran_kegiatan-nya. Hapus draf membuang `konten.draf`; barisnya ikut dihapus hanya bila tidak berisi apa pun lagi
 * (tanpa nomor, tanpa berkas cetak). Penolakan database → RuntimeException berpesan ramah. Binding masih tiruan.
 */
class EloquentDrafDokumenRepository implements DrafDokumenRepository
{
    public const PESAN_GAGAL = 'Draf dokumen gagal disimpan — coba lagi.';

    public function untukKegiatan(int $kegiatanId): array
    {
        $draf = DB::table('documents')->where('activity_id', $kegiatanId)->whereNotNull('konten->draf')->get()
            ->map(fn (object $baris) => $this->bentuk($baris))->all();
        usort($draf, fn (array $a, array $b) => strcmp($b['disimpan_pada'], $a['disimpan_pada']));

        return $draf;
    }

    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array
    {
        $baris = $this->baris($kegiatanId, $jenis, $sumber)->whereNotNull('konten->draf')->first();

        return $baris === null ? null : $this->bentuk($baris);
    }

    public function simpan(int $kegiatanId, array $draf): array
    {
        try {
            DB::transaction(function () use ($kegiatanId, $draf): void {
                $tahun = DB::table('activities')->where('id', $kegiatanId)->value('tahun_anggaran')
                    ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
                $sumber = $draf['sumber'] ?? null;
                $anggaran = $sumber === null ? null : (DB::table('anggaran_kegiatan')->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->value('id')
                    ?? throw new RuntimeException("Sumber dana {$sumber} belum tersimpan untuk kegiatan ini."));
                $lama = $this->baris($kegiatanId, $draf['jenis'], $sumber)->lockForUpdate()->first();
                $sekarang = now();
                $konten = $lama === null ? [] : (array) json_decode((string) $lama->konten, true);
                $konten['draf'] = [
                    'alasan_draf' => array_values($draf['alasan_draf'] ?? []),
                    'lengkap' => $draf['lengkap'] ?? null,
                    'jumlah_bagian' => $draf['jumlah_bagian'] ?? null,
                    'total_rab' => $draf['total_rab'] ?? null,
                    'sidik' => (object) ($draf['sidik'] ?? []),
                    'user_id' => $draf['user_id'] ?? null,
                    'oleh' => $draf['oleh'],
                    'disimpan_pada' => $sekarang->format('Y-m-d\TH:i:s.uP'),
                ];
                $isi = [
                    'status' => $draf['status'],
                    'bertanda_draf' => (bool) $draf['bertanda_draf'],
                    'konten' => json_encode((object) $konten, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    'updated_at' => $sekarang->format('Y-m-d H:i:s.uP'),
                ];
                if ($lama !== null) {
                    DB::table('documents')->where('id', $lama->id)->update($isi);
                } else {
                    DB::table('documents')->insert($isi + [
                        'activity_id' => $kegiatanId,
                        'tahun_anggaran' => (int) $tahun,
                        'anggaran_kegiatan_id' => $anggaran,
                        'sumber_dana' => $sumber,
                        'jenis' => $draf['jenis'],
                        'created_at' => $isi['updated_at'],
                    ]);
                }
            });
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }

        return $this->temukan($kegiatanId, $draf['jenis'], $draf['sumber'] ?? null);
    }

    public function hapus(int $kegiatanId, string $jenis, ?string $sumber): bool
    {
        try {
            return DB::transaction(function () use ($kegiatanId, $jenis, $sumber): bool {
                $baris = $this->baris($kegiatanId, $jenis, $sumber)->whereNotNull('konten->draf')->lockForUpdate()->first();
                if ($baris === null) {
                    return false;
                }
                $konten = (array) json_decode((string) $baris->konten, true);
                unset($konten['draf']);
                // Baris yang menyimpan hasil susunan (sidik_isi), nomor, atau berkas cetak tetap ada.
                if ($konten === [] && $baris->sidik_isi === null && $baris->nomor_dokumen === null && $baris->file_docx_path === null && $baris->file_pdf_path === null) {
                    DB::table('documents')->where('id', $baris->id)->delete();
                } else {
                    DB::table('documents')->where('id', $baris->id)->update([
                        'konten' => json_encode((object) $konten, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                        'updated_at' => now()->format('Y-m-d H:i:s.uP'),
                    ]);
                }

                return true;
            });
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }
    }

    /**
     * @param  array<string, mixed>  $sidik
     * @return array<string, mixed>
     */
    private static function urutkanSidik(array $sidik): array
    {
        if (isset($sidik['kak']) && is_array($sidik['kak'])) {
            ksort($sidik['kak'], SORT_NUMERIC);
        }

        return array_merge(array_intersect_key(['cover' => null, 'kak' => null, 'rab' => null], $sidik), $sidik);
    }

    private function baris(int $kegiatanId, string $jenis, ?string $sumber): Builder
    {
        $kueri = DB::table('documents')->where('activity_id', $kegiatanId)->where('jenis', $jenis);

        return $sumber === null ? $kueri->whereNull('sumber_dana') : $kueri->where('sumber_dana', $sumber);
    }

    /**
     * Bentuk DrafDokumenRepository (urutan kunci sama dengan tiruan).
     *
     * @return array<string, mixed>
     */
    private function bentuk(object $baris): array
    {
        $draf = (array) (json_decode((string) $baris->konten, true)['draf'] ?? []);

        return [
            'id' => (string) $baris->id,
            'kegiatan_id' => (int) $baris->activity_id,
            'jenis' => $baris->jenis,
            'sumber' => $baris->sumber_dana,
            'status' => $baris->status,
            'bertanda_draf' => (bool) $baris->bertanda_draf,
            'alasan_draf' => array_values($draf['alasan_draf'] ?? []),
            'lengkap' => $draf['lengkap'] ?? null,
            'jumlah_bagian' => $draf['jumlah_bagian'] ?? null,
            'total_rab' => $draf['total_rab'] ?? null,
            // jsonb mengurutkan ulang kunci objek: kembalikan urutan sidik seperti DrafDokumen::sidik (cover, kak per nomor, rab).
            'sidik' => self::urutkanSidik((array) ($draf['sidik'] ?? [])),
            'user_id' => isset($draf['user_id']) ? (int) $draf['user_id'] : null,
            'oleh' => (string) ($draf['oleh'] ?? ''),
            'disimpan_pada' => CarbonImmutable::parse($draf['disimpan_pada'] ?? $baris->updated_at)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
