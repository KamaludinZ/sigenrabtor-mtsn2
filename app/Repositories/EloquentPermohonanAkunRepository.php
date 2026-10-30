<?php

namespace App\Repositories;

use App\Contracts\PermohonanAkunRepository;
use App\Support\Stub\StubPenggunaRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Permohonan akun di PostgreSQL (padanan StubPermohonanAkunRepository; tabel `permohonan_akun` + `permohonan_akun_leading`
 * migrasi 000048): bentuk & urutan kunci sama dengan tiruan (leading urut alami, waktu ISO bermikrodetik zona aplikasi,
 * `diputuskan_oleh` = id akun pemutus), terbaru dulu; id bukan uuid = tidak ada. `ajukan()` satu transaksi (permohonan +
 * leading); penolakan database → RuntimeException berpesan ramah (surel sudah menjadi akun / sudah ada permohonan menunggu).
 * Binding masih tiruan.
 */
class EloquentPermohonanAkunRepository implements PermohonanAkunRepository
{
    public function semua(): array
    {
        $leading = $this->leading();

        return DB::table('permohonan_akun')->orderByDesc('diajukan_pada')->orderByDesc('id')->get()
            ->map(fn (object $p) => $this->bentuk($p, $leading[$p->id] ?? []))->all();
    }

    public function temukan(string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        $p = DB::table('permohonan_akun')->where('id', $id)->first();

        return $p === null ? null : $this->bentuk($p, $this->leading($id)[$id] ?? []);
    }

    public function ajukan(array $permohonan): array
    {
        $id = (string) Str::uuid();
        $waktu = now()->format('Y-m-d H:i:s.uP');
        try {
            DB::transaction(function () use ($id, $permohonan, $waktu) {
                DB::table('permohonan_akun')->insert([
                    'id' => $id,
                    'nama' => $permohonan['nama'],
                    'surel' => $permohonan['surel'],
                    'jabatan' => $permohonan['jabatan'] ?? null,
                    'nip' => $permohonan['nip'] ?? null,
                    'catatan' => $permohonan['catatan'] ?? null,
                    'sandi_hash' => $permohonan['sandi_hash'] ?? null,
                    'status' => 'menunggu',
                    'diajukan_pada' => $waktu,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
                DB::table('permohonan_akun_leading')->insert(array_map(
                    fn (string $kode) => ['permohonan_id' => $id, 'kode_leading' => $kode],
                    array_values(array_unique(array_map('strval', $permohonan['leadings'] ?? []))),
                ));
            });
        } catch (QueryException $e) {
            report($e);
            throw new RuntimeException(match (true) {
                str_contains($e->getMessage(), 'permohonan_akun_satu_menunggu') => 'Permohonan dengan surel ini sedang menunggu persetujuan admin.',
                str_contains($e->getMessage(), 'sudah terdaftar sebagai akun') => 'Surel ini sudah terdaftar sebagai akun.',
                default => 'Permohonan akun gagal dikirim — coba lagi sebentar lagi.',
            }, previous: $e);
        }

        return $this->temukan($id);
    }

    public function putuskan(string $id, string $status, ?int $olehId, ?string $alasan = null, ?int $akunId = null): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        $waktu = now()->format('Y-m-d H:i:s.uP');
        try {
            $diubah = DB::table('permohonan_akun')->where('id', $id)->where('status', 'menunggu')->update([
                'status' => $status, 'diputuskan_pada' => $waktu, 'diputuskan_oleh' => $olehId, 'alasan' => $alasan,
                'akun_id' => $status === 'disetujui' ? $akunId : null, 'updated_at' => $waktu,
            ]);
        } catch (QueryException $e) {
            report($e);
            throw new RuntimeException('Keputusan permohonan akun gagal disimpan — coba lagi.', previous: $e);
        }

        return $diubah === 0 ? null : $this->temukan($id);
    }

    /** @return array<string, list<string>> Id permohonan → kode leading (urut alami). */
    private function leading(?string $id = null): array
    {
        $baris = DB::table('permohonan_akun_leading')->when($id !== null, fn ($q) => $q->where('permohonan_id', $id))->get();
        $hasil = [];
        foreach ($baris as $b) {
            $hasil[$b->permohonan_id][] = (string) $b->kode_leading;
        }

        return array_map(StubPenggunaRepository::urutKode(...), $hasil);
    }

    /**
     * @param  list<string>  $leading
     * @return array<string, mixed>
     */
    private function bentuk(object $p, array $leading): array
    {
        return [
            'id' => (string) $p->id,
            'nama' => (string) $p->nama,
            'surel' => (string) $p->surel,
            'jabatan' => $p->jabatan,
            'nip' => $p->nip,
            'leadings' => $leading,
            'catatan' => $p->catatan,
            'sandi_hash' => $p->sandi_hash,
            'status' => (string) $p->status,
            'diajukan_pada' => $this->iso($p->diajukan_pada),
            'diputuskan_pada' => $p->diputuskan_pada === null ? null : $this->iso($p->diputuskan_pada),
            'diputuskan_oleh' => $p->diputuskan_oleh === null ? null : (int) $p->diputuskan_oleh,
            'alasan' => $p->alasan,
            'akun_id' => $p->akun_id === null ? null : (int) $p->akun_id,
        ];
    }

    private function iso(string $waktu): string
    {
        return CarbonImmutable::parse($waktu)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');
    }
}
