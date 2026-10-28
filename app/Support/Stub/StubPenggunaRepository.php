<?php

namespace App\Support\Stub;

use App\Contracts\PenggunaRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Akun tiruan di berkas JSON (disk "local"), tanpa sandi (masuk masih lewat /demo/peran). Saat berkas belum ada, contoh
 * disiapkan: akun 1 (leading 1.6) & 2 (admin) yang dipakai StubData::currentUser(), akun leading lain — satu tertaut ke
 * dua leading — admin kedua, dan satu akun nonaktif. Nama & surel fiktif (example.com).
 */
class StubPenggunaRepository implements PenggunaRepository
{
    /** Entri riwayat masuk yang disimpan per akun tiruan. */
    public const MAKS_RIWAYAT_MASUK = 20;

    public function semua(): array
    {
        $semua = array_map(self::rapikan(...), $this->baca());
        usort($semua, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return $semua;
    }

    public function temukan(int $id): ?array
    {
        return collect($this->semua())->firstWhere('id', $id);
    }

    public function simpan(array $data, ?int $id = null): int
    {
        $semua = $this->baca();
        $indeks = $id === null ? false : array_search($id, array_map('intval', array_column($semua, 'id')), true);
        $lama = $indeks === false ? [] : $semua[$indeks];
        $id ??= max([0, ...array_map('intval', array_column($semua, 'id'))]) + 1;

        $baris = self::rapikan(['id' => $id] + $data + ['dibuat_pada' => $lama['dibuat_pada'] ?? now()->toIso8601String(), 'terakhir_masuk' => $lama['terakhir_masuk'] ?? null] + $lama);
        if ($indeks === false) {
            $semua[] = $baris;
        } else {
            $semua[$indeks] = $baris;
        }
        $this->tulis($semua);

        return $id;
    }

    public function catatMasuk(int $id, ?string $alamatIp = null, ?string $agen = null): void
    {
        $semua = $this->baca();
        $indeks = array_search($id, array_map('intval', array_column($semua, 'id')), true);
        if ($indeks === false) {
            return;
        }
        $waktu = now()->format('Y-m-d\\TH:i:s.uP');
        $semua[$indeks]['terakhir_masuk'] = now()->toIso8601String();
        $semua[$indeks]['riwayat_masuk'] = array_slice([
            ['waktu' => $waktu, 'alamat_ip' => $alamatIp, 'agen' => $agen === null ? null : mb_substr($agen, 0, 255)],
            ...(array) ($semua[$indeks]['riwayat_masuk'] ?? []),
        ], 0, self::MAKS_RIWAYAT_MASUK);
        $this->tulis($semua);
    }

    public function riwayatMasuk(int $id, int $batas = 20): array
    {
        $akun = collect($this->baca())->first(fn (array $a) => (int) $a['id'] === $id);

        return array_slice(array_values((array) ($akun['riwayat_masuk'] ?? [])), 0, $batas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function contoh(): array
    {
        return [
            ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'email' => 'muslimin@example.com', 'role' => 'leading', 'nip' => '198001102006041001', 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => ['1.6'], 'is_active' => true, 'terakhir_masuk' => '2026-01-05T07:45:00+07:00', 'dibuat_pada' => '2025-01-02T08:00:00+07:00'],
            ['id' => 2, 'name' => 'Koordinator PUSKOM', 'email' => 'puskom@example.com', 'role' => 'admin', 'nip' => null, 'jabatan' => 'Admin Aplikasi', 'leadings' => [], 'is_active' => true, 'terakhir_masuk' => '2026-01-06T08:10:00+07:00', 'dibuat_pada' => '2025-01-02T08:00:00+07:00'],
            ['id' => 3, 'name' => 'Siti Rohmah, M.Pd.', 'email' => 'siti.rohmah@example.com', 'role' => 'leading', 'nip' => '197803152005012003', 'jabatan' => 'Wakil Kepala Bidang Kurikulum', 'leadings' => ['1.1'], 'is_active' => true, 'terakhir_masuk' => null, 'dibuat_pada' => '2025-01-03T09:00:00+07:00'],
            ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.', 'email' => 'ahmad.fauzan@example.com', 'role' => 'leading', 'nip' => null, 'jabatan' => 'Wakil Kepala Bidang Kesiswaan', 'leadings' => ['1.2', '1.4'], 'is_active' => true, 'terakhir_masuk' => null, 'dibuat_pada' => '2025-01-03T09:05:00+07:00'],
            ['id' => 5, 'name' => 'Rina Kusumawati, M.Pd.', 'email' => 'rina.kusumawati@example.com', 'role' => 'admin', 'nip' => null, 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => [], 'is_active' => true, 'terakhir_masuk' => null, 'dibuat_pada' => '2025-01-04T10:00:00+07:00'],
            ['id' => 6, 'name' => 'Dewi Kartika, S.E.', 'email' => 'dewi.kartika@example.com', 'role' => 'leading', 'nip' => null, 'jabatan' => 'Kepala Tata Usaha', 'leadings' => ['1.5'], 'is_active' => false, 'terakhir_masuk' => '2025-11-20T13:00:00+07:00', 'dibuat_pada' => '2025-01-04T10:05:00+07:00'],
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{id: int, name: string, email: string, role: string, nip: string|null, jabatan: string|null, leadings: list<string>, is_active: bool, terakhir_masuk: string|null, dibuat_pada: string|null}
     */
    private static function rapikan(array $a): array
    {
        $role = ($a['role'] ?? 'leading') === 'admin' ? 'admin' : 'leading';

        return [
            'id' => (int) $a['id'],
            'name' => (string) ($a['name'] ?? ''),
            'email' => (string) ($a['email'] ?? ''),
            'role' => $role,
            'nip' => filled($a['nip'] ?? null) ? (string) $a['nip'] : null,
            'jabatan' => filled($a['jabatan'] ?? null) ? (string) $a['jabatan'] : null,
            'leadings' => $role === 'admin' ? [] : self::urutKode(array_values(array_unique(array_map('strval', (array) ($a['leadings'] ?? []))))),
            'is_active' => (bool) ($a['is_active'] ?? true),
            'terakhir_masuk' => $a['terakhir_masuk'] ?? null,
            'dibuat_pada' => $a['dibuat_pada'] ?? null,
        ];
    }

    /**
     * Kode leading urut alami (1.2 sebelum 1.10).
     *
     * @param  list<string>  $kode
     * @return list<string>
     */
    public static function urutKode(array $kode): array
    {
        usort($kode, 'version_compare');

        return $kode;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $semua
     */
    private function tulis(array $semua): void
    {
        if (! $this->disk()->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Data akun gagal ditulis.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.pengguna_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
