<?php

namespace App\Support\Dokumen\Generasi;

use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\JenisDokumen;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan satu berkas dokumen kegiatan untuk GeneratorBerkasDokumen, selalu sah: `dari($kegiatan, $isian, $pengguna)`
 * merapikan isian mentah {format, jenis?, sumber?} — format FormatBerkas::FORMAT (galat `format`), jenis
 * DokumenKegiatan::JENIS (kosong = bawaan format: Excel → RAB, Word → KAK+TOR, PDF → JenisDokumen::BAWAAN; kegiatan tanpa
 * sumber dana → TANPA_SUMBER; galat `jenis`), jenis × format harus cocok (FormatBerkas::galat → galat `format`), sumber dana
 * harus dipilih kegiatan itu (galat `sumber`; kosong = sumber pertama; jenis/format per sumber dana pada kegiatan tanpa
 * sumber dana → galat `sumber`). Hak akses kegiatan diperiksa pemanggil (AksesKegiatan).
 */
final class PermintaanBerkas
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  list<string>  $lembar
     * @param  array<string, mixed>  $pengguna
     */
    public function __construct(
        public readonly array $kegiatan,
        public readonly string $jenis,
        public readonly ?string $sumber,
        public readonly string $format,
        public readonly array $lembar,
        public readonly array $pengguna = [],
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $isian
     * @param  array<string, mixed>  $pengguna
     *
     * @throws ValidationException Berkunci `format`, `jenis`, atau `sumber`.
     */
    public static function dari(array $kegiatan, array $isian, array $pengguna = []): self
    {
        $galat = [];
        $format = is_string($isian['format'] ?? null) ? strtolower(trim($isian['format'])) : '';
        if (! isset(FormatBerkas::FORMAT[$format])) {
            $galat['format'] = 'Format berkas harus salah satu dari: '.implode(', ', array_map(FormatBerkas::label(...), array_keys(FormatBerkas::FORMAT))).'.';
        }

        $dipilih = array_values(array_unique(array_column($kegiatan['sumber_dana'] ?? [], 'sumber')));
        $jenis = is_string($isian['jenis'] ?? null) && trim($isian['jenis']) !== '' ? trim($isian['jenis']) : match (true) {
            $format === 'xlsx' => 'RAB',
            $format === 'docx' || $dipilih === [] => JenisDokumen::TANPA_SUMBER,
            default => JenisDokumen::BAWAAN,
        };
        if (! isset(DokumenKegiatan::JENIS[$jenis])) {
            $galat['jenis'] = 'Jenis dokumen harus salah satu dari: '.implode(', ', array_keys(DokumenKegiatan::JENIS)).'.';
        } elseif (! isset($galat['format']) && ($pesan = FormatBerkas::galat($jenis, $format)) !== null) {
            $galat['format'] = $pesan;
        }

        $sumber = is_string($isian['sumber'] ?? null) && trim($isian['sumber']) !== '' ? trim($isian['sumber']) : null;
        if ($sumber !== null && ! in_array($sumber, $dipilih, true)) {
            $galat['sumber'] = "Sumber dana {$sumber} tidak dipilih untuk kegiatan ini.";
        }
        $sumber ??= $dipilih[0] ?? null;
        $perlu = (isset(DokumenKegiatan::JENIS[$jenis]) && JenisDokumen::perluSumber($jenis)) || FormatBerkas::perluSumber($format);
        if ($sumber === null && $perlu && ! isset($galat['sumber'])) {
            $galat['sumber'] = 'Kegiatan ini belum memilih sumber dana, padahal '.(DokumenKegiatan::JENIS[$jenis] ?? 'berkas ini').' disusun per sumber dana.';
        }
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return new self($kegiatan, $jenis, $sumber, $format, FormatBerkas::lembar($jenis, $sumber), $pengguna);
    }

    /** @return array{kegiatan_id: int|null, jenis: string, sumber: string|null, format: string, lembar: list<string>} */
    public function toArray(): array
    {
        return ['kegiatan_id' => isset($this->kegiatan['id']) ? (int) $this->kegiatan['id'] : null, 'jenis' => $this->jenis, 'sumber' => $this->sumber, 'format' => $this->format, 'lembar' => $this->lembar];
    }
}
