<?php

namespace App\Support\Pengaturan;

use App\Contracts\PromptAiRepository;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Stub\StubData;

/**
 * Template prompt per bagian AI (PRD tabel prompt_templates: bagian, instruksi, gaya_bahasa, is_active). Tersimpan lewat
 * PromptAiRepository; bagian yang belum diubah memakai BAWAAN. Batas Aman selalu disertakan dan tidak bisa diubah.
 * susun() = teks prompt lengkap (gaya bahasa, aturan, tugas dengan penanda terisi, bahan kegiatan) — dipakai pratinjau
 * editor dan riwayat saran AI.
 */
final class PromptAi
{
    public const GAYA_BAHASA = 'Bahasa Indonesia baku dan kalimat efektif bergaya dokumen dinas madrasah; tanpa singkatan tidak baku dan tanpa sapaan.';

    public const BATAS_AMAN = 'Jangan menambah angka, kode, atau nama pejabat yang tidak ada di bahan; jangan menyebut NIP.';

    public const BAWAAN = [
        'nama_cover' => 'Rapikan nama kegiatan menjadi judul cover yang baku dan ringkas, ditulis kapital, tanpa kata "Kegiatan" di awal.',
        'latar_belakang' => 'Tulis 1–2 paragraf alasan kegiatan yang menyambung paragraf baku visi madrasah, berdasarkan program RKTM, IKSK, dan catatan konteks.',
        'tujuan' => 'Tulis satu frasa tujuan spesifik yang melanjutkan kalimat "Kegiatan {nama_kegiatan} bertujuan …", diawali kata kerja.',
        'sasaran' => 'Tulis satu frasa sasaran terukur yang melanjutkan kalimat "Terwujudnya …", menyebut kelompok sasaran dan hasil yang diharapkan.',
        'pelaksanaan' => 'Tulis 1–2 kalimat bentuk pelaksanaan (metode, narasumber, alur) yang menyambung kalimat waktu dan peserta.',
        'jadwal' => 'Usulkan 4–6 uraian jadwal beserta bulannya berdasarkan bulan pelaksanaan dan jenis kegiatan.',
    ];

    /** Penanda yang boleh dipakai di instruksi → keterangan. */
    public const PENANDA = ['{nama_kegiatan}' => 'Nama kegiatan (tanpa awalan "Kegiatan")', '{tahun}' => 'Tahun anggaran aktif'];

    public const PANJANG_INSTRUKSI = [20, 1000];

    public const MAKS_GAYA = 300;

    public static function gayaBahasa(): string
    {
        return trim((string) (app(PromptAiRepository::class)->ambil()['gaya_bahasa'] ?? '')) ?: self::GAYA_BAHASA;
    }

    /**
     * Prompt berlaku satu bagian.
     *
     * @return array{instruksi: string, gaya_bahasa: string|null, aktif: bool, bawaan: bool}
     */
    public static function untuk(string $bagian): array
    {
        $t = app(PromptAiRepository::class)->ambil()['bagian'][$bagian] ?? [];
        $instruksi = trim((string) ($t['instruksi'] ?? '')) ?: self::BAWAAN[$bagian];
        $gaya = trim((string) ($t['gaya_bahasa'] ?? '')) ?: null;
        $aktif = (bool) ($t['aktif'] ?? true);

        return ['instruksi' => $instruksi, 'gaya_bahasa' => $gaya, 'aktif' => $aktif, 'bawaan' => $instruksi === self::BAWAAN[$bagian] && $gaya === null && $aktif];
    }

    public static function aktif(string $bagian): bool
    {
        return self::untuk($bagian)['aktif'];
    }

    /**
     * Prompt per bagian dalam urutan BagianAi::BAGIAN.
     *
     * @return list<array{bagian: string, judul: string, instruksi: string, gaya_bahasa: string|null, aktif: bool, bawaan: bool}>
     */
    public static function semua(): array
    {
        return array_map(fn (string $b) => ['bagian' => $b, 'judul' => BagianAi::BAGIAN[$b]['judul']] + self::untuk($b), array_keys(BagianAi::BAGIAN));
    }

    /**
     * Galat isian template: instruksi wajib (panjang PANJANG_INSTRUKSI), hanya penanda terdaftar (saran bila mirip),
     * tanpa NIP; gaya bahasa khusus ≤ MAKS_GAYA.
     *
     * @return array<string, string>
     */
    public static function galat(string $instruksi, ?string $gaya = null): array
    {
        [$min, $maks] = self::PANJANG_INSTRUKSI;
        $galat = [];
        $panjang = mb_strlen(trim($instruksi));

        if ($panjang < $min || $panjang > $maks) {
            $galat['instruksi'] = "Instruksi {$min}–{$maks} karakter (sekarang {$panjang}).";
        } elseif (preg_match_all('/\{[^{}]*\}/u', $instruksi, $cocok)) {
            foreach (array_unique($cocok[0]) as $penanda) {
                if (! isset(self::PENANDA[$penanda])) {
                    $saran = collect(array_keys(self::PENANDA))->sortBy(fn (string $p) => levenshtein($p, mb_strtolower($penanda)))->first();
                    $galat['instruksi'] = "Penanda {$penanda} tidak dikenal".(levenshtein($saran, mb_strtolower($penanda)) <= 4 ? " — maksud Anda {$saran}?" : '').' Penanda yang boleh: '.implode(', ', array_keys(self::PENANDA)).'.';
                    break;
                }
            }
        }
        if (! isset($galat['instruksi']) && KonteksAi::peringatanPrivasi($instruksi) !== null) {
            $galat['instruksi'] = 'Instruksi tidak boleh memuat NIP.';
        }
        if ($gaya !== null && mb_strlen(trim($gaya)) > self::MAKS_GAYA) {
            $galat['gaya_bahasa'] = 'Gaya bahasa maksimal '.self::MAKS_GAYA.' karakter.';
        }

        return $galat;
    }

    /** Isi penanda instruksi dengan data kegiatan. */
    public static function isiPenanda(string $teks, array $kegiatan): string
    {
        return strtr($teks, [
            '{nama_kegiatan}' => KalimatKak::tanpaAwalanKegiatan((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: '…',
            '{tahun}' => (string) StubData::tahunAktif()['tahun'],
        ]);
    }

    /**
     * Prompt lengkap satu bagian untuk $kegiatan; $template mengganti template tersimpan (pratinjau editor).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{instruksi?: string, gaya_bahasa?: string|null}|null  $template
     */
    public static function susun(string $bagian, array $kegiatan, ?array $template = null): string
    {
        $t = ($template ?? []) + self::untuk($bagian);
        $bahan = array_filter(BagianAi::bahan($bagian, $kegiatan), fn (array $b) => $b['nilai'] !== null);

        return implode("\n", [
            'Gaya bahasa: '.(trim((string) ($t['gaya_bahasa'] ?? '')) ?: self::gayaBahasa()),
            'Aturan: '.self::BATAS_AMAN,
            'Tugas: '.self::isiPenanda((string) $t['instruksi'], $kegiatan),
            '',
            'Bahan bagian:',
            ...array_map(fn (array $b) => "- {$b['label']}: {$b['nilai']}", $bahan),
            'Bahan kegiatan:',
            KonteksAi::teks($kegiatan),
        ]);
    }
}
