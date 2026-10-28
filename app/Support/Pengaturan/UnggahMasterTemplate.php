<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\AnalisisTemplate;
use App\Support\Dokumen\PembacaTemplateDocx;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unggah & hapus master KAK/TOR .docx di server (PRD §7C, tabel document_templates; hanya admin): berkas .docx ≤ MAKS_KB
 * dengan nama ≤ 100 karakter dan jenis TemplateDokumenRepository::JENIS (`aturan()`/`pesan()`, berkunci berkas/nama/
 * jenis — dipakai juga tab KAK/TOR Pengaturan › Template Dokumen), variabel `${…}` dibaca PembacaTemplateDocx
 * (`periksa()`), dan hanya disimpan bila ada variabel dan semuanya terdaftar (AnalisisTemplate::bisaDisimpan). Template
 * tahun anggaran yang terkunci hanya bisa dilihat. Riwayat `unggah_master`/`hapus_master`.
 */
final class UnggahMasterTemplate
{
    /** Batas ukuran master .docx (KB). */
    public const MAKS_KB = 10240;

    /** @return array<string, list<mixed>> */
    public static function aturan(): array
    {
        return [
            'berkas' => ['required', 'file', 'max:'.self::MAKS_KB, 'extensions:docx'],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::in(array_keys(TemplateDokumenRepository::JENIS))],
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
            'nama.max' => 'Nama template maksimal 100 karakter.',
            'jenis.in' => 'Pilih jenis template dari daftar.',
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
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Master tersimpan (bentuk TemplateDokumenRepository).
     *
     * @throws ValidationException Berkunci berkas/nama/jenis.
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, ?UploadedFile $berkas, string $nama, string $jenis, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        Validator::make(['berkas' => $berkas, 'nama' => trim($nama), 'jenis' => $jenis], self::aturan(), self::pesan())->validate();
        $analisis = self::periksa($berkas);
        if ($pesan = self::pesanTidakBisaDisimpan($analisis)) {
            throw ValidationException::withMessages(['berkas' => $pesan]);
        }

        $repo = app(TemplateDokumenRepository::class);
        $master = $repo->temukan($repo->tambah($tahun, trim($nama), $jenis, $berkas, $analisis->namaTerdaftar()));
        RiwayatPengaturan::catat('unggah_master', "Master “{$master['nama']}” ({$master['jenis']}) diunggah — ".count($master['variabel']).' variabel'.($master['is_default'] ? ', menjadi default jenis ini' : '').'.');

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
