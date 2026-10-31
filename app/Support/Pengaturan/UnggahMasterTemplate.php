<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\AnalisisTemplate;
use App\Support\Dokumen\PembacaTemplateDocx;
use App\Support\MasterKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unggah & hapus master KAK/TOR .docx di server (PRD §7C, tabel document_templates; hanya admin): berkas .docx ≤ MAKS_KB
 * dengan nama ≤ 100 karakter, jenis TemplateDokumenRepository::JENIS, pemilik (`leading` aktif; kosong = umum), catatan
 * versi ≤ MAKS_CATATAN, dan `default` pemilik × jenis (`rapikanAtribut()`) (`aturan()`/`pesan()`, berkunci berkas/nama/
 * jenis — dipakai juga tab KAK/TOR Pengaturan › Template Dokumen), variabel `${…}` dibaca PembacaTemplateDocx
 * (`periksa()`), dan hanya disimpan bila ada variabel dan semuanya terdaftar (AnalisisTemplate::bisaDisimpan). Template
 * tahun anggaran yang terkunci hanya bisa dilihat. Riwayat `unggah_master`/`hapus_master`.
 */
final class UnggahMasterTemplate
{
    /** Batas ukuran master .docx (KB). */
    public const MAKS_KB = 10240;

    /** Batas nama template (karakter, satu baris). */
    public const MAKS_NAMA = 100;

    /** Batas catatan versi (karakter, satu baris). */
    public const MAKS_CATATAN = 300;

    /** @return array<string, list<mixed>> */
    public static function aturan(): array
    {
        return [
            'berkas' => ['required', 'file', 'max:'.self::MAKS_KB, 'extensions:docx'],
            'nama' => ['required', 'string', 'max:'.self::MAKS_NAMA],
            'jenis' => ['required', Rule::in(array_keys(TemplateDokumenRepository::JENIS))],
            'leading' => ['nullable', Rule::in(array_column(MasterKode::leadings(), 'kode'))],
            'catatan' => ['nullable', 'string', 'max:'.self::MAKS_CATATAN],
            'default' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'berkas.required' => 'Pilih berkas master KAK/TOR (.docx).',
            'berkas.extensions' => 'Pilih dokumen Word .docx (bukan .doc atau PDF).',
            'berkas.max' => 'Ukuran berkas maksimal '.(self::MAKS_KB / 1024).' MB.',
            'nama.required' => 'Beri nama template, mis. “Master KAK/TOR 2026”.',
            'nama.max' => 'Nama template maksimal '.self::MAKS_NAMA.' karakter.',
            'jenis.in' => 'Pilih jenis template dari daftar.',
            'leading.in' => 'Pilih pemilik template: umum atau leading yang aktif.',
            'catatan.max' => 'Catatan versi maksimal '.self::MAKS_CATATAN.' karakter.',
        ];
    }

    /**
     * Variabel berkas dicocokkan dengan daftar terdaftar.
     *
     * @throws ValidationException Berkas bukan .docx yang sah (`berkas`).
     */
    public static function periksa(UploadedFile $berkas): AnalisisTemplate
    {
        try {
            return AnalisisTemplate::dari(PembacaTemplateDocx::variabel($berkas->getRealPath()));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['berkas' => $e->getMessage()]);
        }
    }

    /** Pesan untuk analisis yang belum bisa disimpan; null bila bisa. */
    public static function pesanTidakBisaDisimpan(AnalisisTemplate $analisis): ?string
    {
        return match (true) {
            $analisis->bisaDisimpan() => null,
            $analisis->variabel === [] => 'Belum ada variabel ${…} di dokumen — tandai bagian yang diisi otomatis, mis. ${nama_kegiatan}.',
            default => 'Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.',
        };
    }

    /**
     * Atribut pemilik & versi rapi: leading '' = null (umum), catatan satu baris berspasi tunggal ('' = null), default bool.
     *
     * @param  array<string, mixed>  $atribut
     * @return array{leading: string|null, catatan: string|null, default: bool}
     */
    public static function rapikanAtribut(array $atribut): array
    {
        $catatan = trim((string) preg_replace('/\s+/u', ' ', (string) ($atribut['catatan'] ?? '')));

        return [
            'leading' => filled($atribut['leading'] ?? null) ? trim((string) $atribut['leading']) : null,
            'catatan' => $catatan === '' ? null : $catatan,
            'default' => filter_var($atribut['default'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @param  array{leading?: string|null, catatan?: string|null, default?: bool}  $atribut  Pemilik (null/'' = umum), catatan versi, jadikan default pemilik × jenis.
     * @return array<string, mixed> Master tersimpan (bentuk TemplateDokumenRepository).
     *
     * @throws ValidationException Berkunci berkas/nama/jenis/leading/catatan.
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, ?UploadedFile $berkas, string $nama, string $jenis, array $pengguna, array $atribut = []): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        $atribut = self::rapikanAtribut($atribut);
        $nama = trim((string) preg_replace('/\s+/u', ' ', $nama));
        Validator::make(['berkas' => $berkas, 'nama' => $nama, 'jenis' => $jenis] + $atribut, self::aturan(), self::pesan())->validate();
        $analisis = self::periksa($berkas);
        if ($pesan = self::pesanTidakBisaDisimpan($analisis)) {
            throw ValidationException::withMessages(['berkas' => $pesan]);
        }

        $repo = app(TemplateDokumenRepository::class);
        $master = $repo->temukan($repo->tambah($tahun, $nama, $jenis, $berkas, $analisis->namaTerdaftar(), $atribut + [
            'jumlah' => array_column($analisis->variabel, 'jumlah', 'nama'),
            'oleh' => ['id' => $pengguna['id'] ?? null, 'nama' => $pengguna['name'] ?? null],
        ]));
        $pemilik = $master['leading'] === null ? '' : ' untuk leading '.$master['leading'];
        RiwayatPengaturan::catat('unggah_master', "Master “{$master['nama']}” ({$master['jenis']}){$pemilik} diunggah — ".count($master['variabel']).' variabel'
            .($master['is_default'] ? ', menjadi default '.($master['leading'] === null ? 'jenis ini' : 'leading itu untuk jenis ini') : '').'.');

        return $master;
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed>|null Master yang dihapus; null bila tidak ada.
     */
    public static function hapus(string $id, array $pengguna): ?array
    {
        $repo = app(TemplateDokumenRepository::class);
        $master = $repo->temukan($id);
        if ($master === null) {
            return null;
        }
        self::pastikanBoleh($master['tahun'], $pengguna);

        $repo->hapus($id);
        RiwayatPengaturan::catat('hapus_master', "Master “{$master['nama']}” ({$master['jenis']}) dihapus.");

        return $master;
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Template dokumen diatur admin.');
        }
        if (collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)['is_locked'] ?? false) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — templatenya hanya bisa dilihat.");
        }
    }
}
