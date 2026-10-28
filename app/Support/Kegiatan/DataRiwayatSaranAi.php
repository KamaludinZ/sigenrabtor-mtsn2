<?php

namespace App\Support\Kegiatan;

use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\SaranAi\Riwayat;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Stub\StubPengaturan;

/**
 * Bentuk JSON riwayat saran AI (PRD F25) untuk endpoint penarikan riwayat:
 * - `daftar($pengguna, $nilai)` (`saran_ai.data`): permintaan dalam cakupan akun (RiwayatSaranAi::untuk — admin semua,
 *   akun leading kegiatan leading-nya) dengan saringan URL halaman Riwayat Saran AI (`saringan()`: kegiatan, bagian,
 *   status, cari) + halaman `hal` (PER_HALAMAN = Riwayat::PER_HALAMAN), ringkasan semua yang tersaring, pilihan kegiatan;
 * - `kegiatan($kegiatan, $bagian)` (`kegiatan.saran_ai`): riwayat satu kegiatan (hak baca diperiksa pemanggil lewat
 *   AksesKegiatan), opsional satu bagian, beserta ringkasan per bagian;
 * - `baris($permintaan)`: satu permintaan — kegiatan & tautan suntingnya (RiwayatSaranAi::tautan), bagian, peminta,
 *   penyedia (nama PenyediaAi, "AI tiruan" bila kosong), waktu, keputusan (RiwayatSaranAi::keputusan) + nomor versi
 *   dipakai, instruksi, versi bernomor 1.. (status + label, `teks_dipakai` = teks yang benar-benar dipakai), dan
 *   bahan yang dikirim ke AI (`prompt`).
 */
final class DataRiwayatSaranAi
{
    public const PER_HALAMAN = Riwayat::PER_HALAMAN;

    public const MAKS_CARI = 100;

    /**
     * @param  array<string, mixed>  $nilai
     * @return array{kegiatan: int, bagian: string, status: string, cari: string, hal: int}
     */
    public static function saringan(array $nilai): array
    {
        $bagian = is_string($nilai['bagian'] ?? null) ? trim($nilai['bagian']) : '';
        $status = is_string($nilai['status'] ?? null) ? trim($nilai['status']) : '';

        return [
            'kegiatan' => max(0, (int) filter_var($nilai['kegiatan'] ?? 0, FILTER_VALIDATE_INT)),
            'bagian' => isset(BagianAi::BAGIAN[$bagian]) ? $bagian : '',
            'status' => isset(RiwayatSaranAi::STATUS[$status]) ? $status : '',
            'cari' => is_string($nilai['cari'] ?? null) ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', $nilai['cari'])), 0, self::MAKS_CARI) : '',
            'hal' => max(1, (int) filter_var($nilai['hal'] ?? 1, FILTER_VALIDATE_INT)),
        ];
    }

    /**
     * @param  array{id: int, role: string, leadings: list<string>}  $pengguna
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    public static function daftar(array $pengguna, array $nilai): array
    {
        $saringan = self::saringan($nilai);
        $semua = RiwayatSaranAi::untuk($pengguna, $saringan);
        $jumlahHalaman = max(1, (int) ceil(count($semua) / self::PER_HALAMAN));
        $saringan['hal'] = min($saringan['hal'], $jumlahHalaman);
        $pilihan = array_column(RiwayatSaranAi::untuk($pengguna), 'nama_kegiatan', 'kegiatan_id');

        return [
            'saringan' => $saringan,
            'ringkasan' => RiwayatSaranAi::ringkasan($semua),
            'halaman' => ['ke' => $saringan['hal'], 'per' => self::PER_HALAMAN, 'jumlah' => $jumlahHalaman, 'total' => count($semua)],
            'pilihan_kegiatan' => array_map(fn (int $id, string $nama) => ['id' => $id, 'nama' => $nama], array_keys($pilihan), $pilihan),
            'permintaan' => array_map(self::baris(...), array_slice($semua, ($saringan['hal'] - 1) * self::PER_HALAMAN, self::PER_HALAMAN)),
        ];
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function kegiatan(array $kegiatan, string $bagian = ''): array
    {
        $bagian = isset(BagianAi::BAGIAN[$bagian]) ? $bagian : '';
        $milik = array_values(array_filter(app(RiwayatSaranAiRepository::class)->semua(), fn (array $p) => $p['kegiatan_id'] === (int) $kegiatan['id']));
        $tampil = array_values(array_filter($milik, fn (array $p) => $bagian === '' || $p['bagian'] === $bagian));
        $perBagian = array_count_values(array_column($milik, 'bagian'));

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')),
            'bagian' => $bagian !== '' ? $bagian : null,
            'ringkasan' => RiwayatSaranAi::ringkasan($tampil),
            'per_bagian' => array_map(fn (string $b) => ['bagian' => $b, 'judul' => BagianAi::BAGIAN[$b]['judul'], 'permintaan' => $perBagian[$b] ?? 0], array_keys(BagianAi::BAGIAN)),
            'permintaan' => array_map(fn (array $p) => self::baris($p + ['nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')), 'leading' => $kegiatan['leading'] ?? null]), $tampil),
        ];
    }

    /**
     * @param  array<string, mixed>  $p  Permintaan bentuk RiwayatSaranAiRepository (boleh dilengkapi nama_kegiatan & leading).
     * @return array<string, mixed>
     */
    public static function baris(array $p): array
    {
        $bagian = BagianAi::BAGIAN[$p['bagian']] ?? null;
        $nama = array_column(StubPengaturan::pengguna(), 'name', 'id');
        $dipakai = collect($p['versi'])->search(fn (array $v) => in_array($v['status'], ['diterima', 'diedit'], true));

        return [
            'id' => $p['id'],
            'kegiatan' => ['id' => $p['kegiatan_id'], 'nama' => $p['nama_kegiatan'] ?? null, 'leading' => $p['leading'] ?? null, 'tautan' => RiwayatSaranAi::tautan($p)],
            'bagian' => ['kunci' => $p['bagian'], 'judul' => $bagian['judul'] ?? $p['bagian'], 'tercetak' => $bagian['tercetak'] ?? null],
            'peminta' => ['id' => $p['user_id'], 'nama' => $nama[$p['user_id']] ?? null],
            'penyedia' => ['kunci' => $p['penyedia'], 'nama' => PenyediaAi::nama($p['penyedia']) ?? 'AI tiruan'],
            'pada' => $p['pada'],
            'diputuskan_pada' => $p['diputuskan_pada'],
            'keputusan' => RiwayatSaranAi::keputusan($p),
            'versi_dipakai' => $dipakai === false ? null : $dipakai + 1,
            'instruksi' => $p['instruksi'] !== '' ? $p['instruksi'] : null,
            'versi' => array_map(fn (int $i, array $v) => [
                'nomor' => $i + 1,
                'judul' => $v['judul'],
                'ciri' => $v['ciri'],
                'teks' => $v['teks'],
                'status' => $v['status'],
                'label_status' => RiwayatSaranAi::STATUS[$v['status']][0] ?? $v['status'],
                'teks_dipakai' => match ($v['status']) {
                    'diterima' => $v['teks'],
                    'diedit' => $v['teks_dipakai'],
                    default => null,
                },
            ], array_keys($p['versi']), $p['versi']),
            'prompt' => $p['prompt'],
        ];
    }
}
