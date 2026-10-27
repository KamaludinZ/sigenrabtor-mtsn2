<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubPengaturan;

/**
 * Aturan riwayat saran AI (PRD F25): pencatatan permintaan Kalimat KAK & usulan jadwal (bahan yang terkirim tanpa
 * NIP/nama pejabat), keputusan per versi, cakupan akun (admin semua, akun leading kegiatan leading-nya), saringan, dan
 * ringkasan untuk halaman Riwayat Saran AI.
 */
final class RiwayatSaranAi
{
    /** Status versi → [label, kelas lencana]. */
    public const STATUS = [
        'menunggu' => ['Belum diputuskan', 'bg-stone-100 text-stone-600 ring-stone-200'],
        'diterima' => ['Diterima', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'diedit' => ['Diedit lalu dipakai', 'bg-gold-50 text-gold-700 ring-gold-200'],
        'ditolak' => ['Ditolak', 'bg-red-50 text-red-700 ring-red-200'],
    ];

    /**
     * Teks permintaan yang dicatat sebagai "prompt terkirim" (bahan sudah diredaksi KonteksAi).
     */
    public static function prompt(PermintaanSaranKalimat $permintaan): string
    {
        $b = BagianAi::BAGIAN[$permintaan->bagian];
        $baris = [
            "Bagian: {$b['judul']} ({$b['tercetak']})",
            ($permintaan->instruksi !== '' ? 'Teks yang diperbaiki: ' : 'Teks saat ini: ').($permintaan->teks !== '' ? $permintaan->teks : '(kosong)'),
            'Instruksi: '.($permintaan->instruksi !== '' ? $permintaan->instruksi : '-'),
            'Bahan bagian:',
            ...array_map(fn (string $label, string $nilai) => "- {$label}: {$nilai}", array_keys($permintaan->bahanBagian), $permintaan->bahanBagian),
            'Bahan kegiatan:',
            $permintaan->bahan,
            // Template yang berlaku saat permintaan dikirim (Pengaturan AI › Prompt per bagian).
            'Tugas: '.PromptAi::isiPenanda(PromptAi::untuk($permintaan->bagian)['instruksi'], ['nama_kegiatan' => $permintaan->namaKegiatan]),
            'Gaya bahasa: '.(PromptAi::untuk($permintaan->bagian)['gaya_bahasa'] ?? PromptAi::gayaBahasa()),
            'Aturan: '.PromptAi::BATAS_AMAN,
        ];

        return implode("\n", $baris);
    }

    /**
     * Catat satu permintaan beserta versinya; mengembalikan id permintaan.
     *
     * @param  list<array{judul: string, ciri: string, teks: string}>  $versi
     * @param  array{id: int}  $pengguna
     */
    public static function catat(int $kegiatanId, string $bagian, string $prompt, string $instruksi, array $versi, array $pengguna): string
    {
        return app(RiwayatSaranAiRepository::class)->catat([
            'kegiatan_id' => $kegiatanId, 'bagian' => $bagian, 'prompt' => $prompt, 'instruksi' => $instruksi,
            'penyedia' => StubPengaturan::pengaturanAi()['provider_aktif'], 'user_id' => (int) $pengguna['id'], 'versi' => $versi,
        ]);
    }

    public static function putuskan(string $id, ?int $versi, ?string $teksDipakai = null): void
    {
        app(RiwayatSaranAiRepository::class)->putuskan($id, $versi, $teksDipakai);
    }

    /**
     * Permintaan dalam cakupan akun, dengan saringan kegiatan (id), bagian, status versi, dan cari (nama kegiatan/teks).
     * Tiap permintaan dilengkapi nama kegiatan & leading.
     *
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @param  array{kegiatan?: int, bagian?: string, status?: string, cari?: string}  $saringan
     * @return list<array<string, mixed>>
     */
    public static function untuk(array $pengguna, array $saringan = []): array
    {
        $kegiatan = array_column(app(KegiatanRepository::class)->semua(), null, 'id');
        $cari = mb_strtolower(trim($saringan['cari'] ?? ''));
        $hasil = [];

        foreach (app(RiwayatSaranAiRepository::class)->semua() as $p) {
            $k = $kegiatan[$p['kegiatan_id']] ?? null;
            if ($pengguna['role'] !== 'admin' && ! in_array($k['leading'] ?? null, $pengguna['leadings'], true)) {
                continue;
            }
            $p['nama_kegiatan'] = trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tidak ditemukan';
            $p['leading'] = $k['leading'] ?? null;
            $teks = mb_strtolower($p['nama_kegiatan'].' '.implode(' ', array_column($p['versi'], 'teks')));

            if ((($saringan['kegiatan'] ?? 0) && $p['kegiatan_id'] !== (int) $saringan['kegiatan'])
                || (($saringan['bagian'] ?? '') !== '' && $p['bagian'] !== $saringan['bagian'])
                || (($saringan['status'] ?? '') !== '' && ! in_array($saringan['status'], array_column($p['versi'], 'status'), true))
                || ($cari !== '' && ! str_contains($teks, $cari))) {
                continue;
            }
            $hasil[] = $p;
        }

        return $hasil;
    }

    /**
     * @param  list<array<string, mixed>>  $permintaan
     * @return array{permintaan: int, versi: int, status: array<string, int>, dipakai: int}
     */
    public static function ringkasan(array $permintaan): array
    {
        $status = array_fill_keys(array_keys(self::STATUS), 0);
        foreach ($permintaan as $p) {
            foreach ($p['versi'] as $v) {
                $status[$v['status']] = ($status[$v['status']] ?? 0) + 1;
            }
        }

        return [
            'permintaan' => count($permintaan),
            'versi' => array_sum($status),
            'status' => $status,
            'dipakai' => count(array_filter($permintaan, fn (array $p) => array_intersect(['diterima', 'diedit'], array_column($p['versi'], 'status')) !== [])),
        ];
    }
}
