<?php

namespace App\Repositories;

use App\Contracts\HasilDokumenRepository;
use App\Support\Dokumen\SimpanDokumenLengkap;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Hasil pembuatan dokumen di PostgreSQL, padanan StubHasilDokumenRepository: baris `documents` per kegiatan × jenis ×
 * sumber dana (dipakai bersama draf — `konten.draf` tidak disentuh) dengan `status`, `bertanda_draf`, `sidik_isi`,
 * `disusun_pada`, `nomor_dokumen` (= nomor), cuplikan `konten.hasil` {alasan_draf, rab, cover, user_id, oleh, format}, dan bagian KAK/TOR di `dokumen_bagian`
 * (diganti seluruhnya tiap disusun). Tahun = tahun kegiatan, sumber dana dipasangkan dengan baris anggaran_kegiatan-nya.
 * Penolakan database → RuntimeException berpesan ramah. Binding masih tiruan.
 */
class EloquentHasilDokumenRepository implements HasilDokumenRepository
{
    public const PESAN_GAGAL = 'Hasil dokumen gagal disimpan — coba lagi.';

    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array
    {
        $baris = $this->baris($kegiatanId, $jenis, $sumber)->whereNotNull('sidik_isi')->first();

        return $baris === null ? null : $this->bentuk($baris);
    }

    public function untukKegiatan(int $kegiatanId): array
    {
        return DB::table('documents')->where('activity_id', $kegiatanId)->whereNotNull('sidik_isi')
            ->orderByDesc('disusun_pada')->orderByDesc('id')->get()->map(fn (object $b) => $this->bentuk($b))->all();
    }

    public function memakaiTemplate(array $templateIds): array
    {
        $uuid = array_values(array_filter($templateIds, fn (string $id) => Str::isUuid($id)));

        return $uuid === [] ? [] : DB::table('documents')->whereIn('template_id', $uuid)->whereNotNull('disusun_pada')
            ->orderBy('activity_id')->orderBy('id')->get()->map($this->bentuk(...))->all();
    }

    public function simpan(int $kegiatanId, array $hasil): array
    {
        try {
            DB::transaction(function () use ($kegiatanId, $hasil): void {
                $tahun = DB::table('activities')->where('id', $kegiatanId)->value('tahun_anggaran')
                    ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
                $sumber = $hasil['sumber'] ?? null;
                $anggaran = $sumber === null ? null : (DB::table('anggaran_kegiatan')->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->value('id')
                    ?? throw new RuntimeException("Sumber dana {$sumber} belum tersimpan untuk kegiatan ini."));
                $lama = $this->baris($kegiatanId, $hasil['jenis'], $sumber)->lockForUpdate()->first();
                $sekarang = now()->format('Y-m-d H:i:s.uP');
                $konten = $lama === null ? [] : (array) json_decode((string) $lama->konten, true);
                $konten['hasil'] = [
                    'alasan_draf' => array_values($hasil['alasan_draf'] ?? []),
                    'rab' => $hasil['rab'] ?? null,
                    'cover' => $hasil['cover'] ?? null,
                    'user_id' => $hasil['user_id'] ?? null,
                    'oleh' => $hasil['oleh'] ?? '',
                    'format' => $hasil['format'] ?? null,
                ];
                $isi = [
                    'nomor_dokumen' => $hasil['nomor'] ?? null,
                    'template_id' => $hasil['template'] ?? null,
                    'status' => $hasil['status'],
                    'bertanda_draf' => (bool) $hasil['bertanda_draf'],
                    'sidik_isi' => $hasil['sidik_isi'],
                    'disusun_pada' => $sekarang,
                    'konten' => json_encode((object) $konten, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    'updated_at' => $sekarang,
                ];
                if ($lama !== null) {
                    DB::table('documents')->where('id', $lama->id)->update($isi);
                    $dokumen = (int) $lama->id;
                } else {
                    $dokumen = DB::table('documents')->insertGetId($isi + [
                        'activity_id' => $kegiatanId,
                        'tahun_anggaran' => (int) $tahun,
                        'anggaran_kegiatan_id' => $anggaran,
                        'sumber_dana' => $sumber,
                        'jenis' => $hasil['jenis'],
                        'created_at' => $sekarang,
                    ]);
                }

                DB::table('dokumen_bagian')->where('document_id', $dokumen)->delete();
                DB::table('dokumen_bagian')->insert(array_map(fn (array $b) => [
                    'document_id' => $dokumen,
                    'jenis_dokumen' => $hasil['jenis'],
                    'nomor' => $b['nomor'],
                    'judul' => $b['judul'],
                    'isi' => json_encode($b['isi'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    'status' => $b['status'],
                    'kurang' => json_encode(array_values($b['kurang']), JSON_UNESCAPED_UNICODE),
                    'periksa' => json_encode(array_values($b['periksa']), JSON_UNESCAPED_UNICODE),
                    'sidik' => $b['sidik'],
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ], $hasil['bagian'] ?? []));
            });
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }

        return $this->temukan($kegiatanId, $hasil['jenis'], $hasil['sumber'] ?? null);
    }

    public function perbaruiStatus(int $kegiatanId, string $jenis, ?string $sumber, string $status, bool $bertandaDraf, array $alasanDraf): bool
    {
        try {
            return DB::transaction(function () use ($kegiatanId, $jenis, $sumber, $status, $bertandaDraf, $alasanDraf): bool {
                $baris = $this->baris($kegiatanId, $jenis, $sumber)->whereNotNull('sidik_isi')->lockForUpdate()->first();
                if ($baris === null) {
                    return false;
                }
                $konten = (array) json_decode((string) $baris->konten, true);
                $konten['hasil']['alasan_draf'] = array_values($alasanDraf);
                DB::table('documents')->where('id', $baris->id)->update([
                    'status' => $status,
                    'bertanda_draf' => $bertandaDraf,
                    'konten' => json_encode((object) $konten, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    'updated_at' => now()->format('Y-m-d H:i:s.uP'),
                ]);

                return true;
            });
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }
    }

    private function baris(int $kegiatanId, string $jenis, ?string $sumber): Builder
    {
        $kueri = DB::table('documents')->where('activity_id', $kegiatanId)->where('jenis', $jenis);

        return $sumber === null ? $kueri->whereNull('sumber_dana') : $kueri->where('sumber_dana', $sumber);
    }

    /**
     * Bentuk HasilDokumenRepository (urutan kunci sama dengan tiruan).
     *
     * @return array<string, mixed>
     */
    private function bentuk(object $baris): array
    {
        $hasil = (array) (json_decode((string) $baris->konten, true)['hasil'] ?? []);
        $bagian = DB::table('dokumen_bagian')->where('document_id', $baris->id)->orderBy('nomor')->get()->map(fn (object $b) => [
            'nomor' => (int) $b->nomor,
            'judul' => $b->judul,
            'isi' => json_decode($b->isi, true),
            'status' => $b->status,
            'kurang' => json_decode($b->kurang, true),
            'periksa' => json_decode($b->periksa, true),
            'sidik' => $b->sidik,
        ])->all();

        return [
            'id' => (string) $baris->id,
            'kegiatan_id' => (int) $baris->activity_id,
            'jenis' => $baris->jenis,
            'sumber' => $baris->sumber_dana,
            'status' => $baris->status,
            'bertanda_draf' => (bool) $baris->bertanda_draf,
            'alasan_draf' => array_values($hasil['alasan_draf'] ?? []),
            'sidik_isi' => trim((string) $baris->sidik_isi),
            'bagian' => $bagian,
            'rab' => $hasil['rab'] ?? null,
            'cover' => $hasil['cover'] ?? null,
            'user_id' => isset($hasil['user_id']) ? (int) $hasil['user_id'] : null,
            'oleh' => (string) ($hasil['oleh'] ?? ''),
            'nomor' => $baris->nomor_dokumen,
            'format' => is_array($hasil['format'] ?? null) ? SimpanDokumenLengkap::urutkanFormat($hasil['format']) : null,
            'template' => $baris->template_id,
            'disusun_pada' => CarbonImmutable::parse($baris->disusun_pada)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
