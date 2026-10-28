<?php

namespace App\Repositories;

use App\Contracts\RiwayatSaranAiRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Riwayat saran AI di PostgreSQL (tabel ai_suggestions + ai_suggestion_versions), padanan StubRiwayatSaranAiRepository:
 * bentuk data sama (id uuid, `pada`/`diputuskan_pada` ISO bermikrodetik zona aplikasi, versi urut `urutan`, terbaru dulu).
 * Kolom `teks_dipakai` database terisi untuk versi diterima (= teks saran, aturan CHECK) maupun diedit, tetapi bentuk data
 * hanya membawanya untuk diedit (seperti tiruan). Putuskan dalam satu transaksi: semua versi ditolak dulu lalu versi
 * terpilih ditandai (indeks unik "satu versi dipakai" tidak pernah terlanggar di tengah jalan); id tak dikenal diabaikan.
 */
class EloquentRiwayatSaranAiRepository implements RiwayatSaranAiRepository
{
    public function catat(array $permintaan): string
    {
        $id = (string) Str::uuid();

        DB::transaction(function () use ($id, $permintaan) {
            DB::table('ai_suggestions')->insert([
                'id' => $id,
                'activity_id' => (int) $permintaan['kegiatan_id'],
                'bagian' => $permintaan['bagian'],
                'prompt_terkirim' => $permintaan['prompt'],
                'instruksi' => $permintaan['instruksi'],
                'provider' => $permintaan['penyedia'],
                'user_id' => (int) $permintaan['user_id'],
                'dibuat_pada' => now()->format('Y-m-d H:i:s.uP'),
            ]);
            DB::table('ai_suggestion_versions')->insert(array_map(fn (int $i) => [
                'suggestion_id' => $id,
                'urutan' => $i + 1,
                'judul' => $permintaan['versi'][$i]['judul'],
                'ciri' => $permintaan['versi'][$i]['ciri'],
                'teks' => $permintaan['versi'][$i]['teks'],
            ], array_keys(array_values($permintaan['versi']))));
        });

        return $id;
    }

    public function putuskan(string $id, ?int $versi, ?string $teksDipakai = null): void
    {
        DB::transaction(function () use ($id, $versi, $teksDipakai) {
            if (! DB::table('ai_suggestions')->where('id', $id)->lockForUpdate()->exists()) {
                return;
            }
            $daftar = DB::table('ai_suggestion_versions')->where('suggestion_id', $id)->orderBy('urutan')->get()->values();
            DB::table('ai_suggestion_versions')->where('suggestion_id', $id)->update(['status' => 'ditolak', 'teks_dipakai' => null]);
            if ($versi !== null && ($dipilih = $daftar->get($versi)) !== null) {
                $diedit = $teksDipakai !== null && $teksDipakai !== $dipilih->teks;
                DB::table('ai_suggestion_versions')->where('id', $dipilih->id)->update(['status' => $diedit ? 'diedit' : 'diterima', 'teks_dipakai' => $diedit ? $teksDipakai : $dipilih->teks]);
            }
            DB::table('ai_suggestions')->where('id', $id)->update(['diputuskan_pada' => now()->format('Y-m-d H:i:s.uP')]);
        });
    }

    public function temukan(string $id): ?array
    {
        $baris = DB::table('ai_suggestions')->where('id', $id)->first();

        return $baris === null ? null : self::permintaan($baris, $this->versi([$id])->get($id, collect()));
    }

    public function semua(): array
    {
        $semua = DB::table('ai_suggestions')->orderByDesc('dibuat_pada')->get();
        $versi = $this->versi($semua->pluck('id')->all());

        return $semua->map(fn (object $r) => self::permintaan($r, $versi->get($r->id, collect())))->all();
    }

    /**
     * @param  list<string>  $id
     * @return Collection<string, Collection<int, object>>
     */
    private function versi(array $id): Collection
    {
        return $id === [] ? collect() : DB::table('ai_suggestion_versions')->whereIn('suggestion_id', $id)->orderBy('urutan')->get()->groupBy('suggestion_id');
    }

    /**
     * @param  Collection<int, object>  $versi
     * @return array<string, mixed>
     */
    private static function permintaan(object $r, Collection $versi): array
    {
        $waktu = fn (?string $nilai) => $nilai === null ? null : CarbonImmutable::parse($nilai)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');

        return [
            'id' => $r->id,
            'kegiatan_id' => (int) $r->activity_id,
            'bagian' => $r->bagian,
            'prompt' => $r->prompt_terkirim,
            'instruksi' => $r->instruksi,
            'penyedia' => $r->provider,
            'user_id' => $r->user_id === null ? null : (int) $r->user_id,
            'pada' => $waktu($r->dibuat_pada),
            'diputuskan_pada' => $waktu($r->diputuskan_pada),
            'versi' => $versi->map(fn (object $v) => [
                'judul' => $v->judul, 'ciri' => $v->ciri, 'teks' => $v->teks, 'status' => $v->status,
                'teks_dipakai' => $v->status === 'diedit' ? $v->teks_dipakai : null,
            ])->values()->all(),
        ];
    }
}
