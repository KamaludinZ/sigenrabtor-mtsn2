<?php

namespace App\Support\Kegiatan;

use App\Enums\StatusKegiatan;
use Illuminate\Support\Carbon;

/**
 * Jejak kunci dokumen final (PRD F18, kolom `dikunci_pada`/`dikunci_oleh`): diambil dari entri riwayat `finalkan`
 * terakhir selama kegiatan berstatus Final. Dipakai banner kunci di ruang kerja kegiatan dan cap "Final · terkunci"
 * pada pratinjau KAK/TOR & RAB.
 */
final readonly class KunciDokumen
{
    public function __construct(
        public ?string $pada,
        public ?string $oleh,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     */
    public static function dari(array $kegiatan): ?self
    {
        if (($kegiatan['status'] ?? null) !== StatusKegiatan::Final->value) {
            return null;
        }

        $entri = collect((array) ($kegiatan['riwayat_status'] ?? []))->last(fn (array $r) => ($r['aksi'] ?? null) === 'finalkan');

        return new self($entri['pada'] ?? null, $entri['oleh'] ?? null);
    }

    /** "2 Maret 2026, 10.00"; null bila waktu kunci tidak tercatat. */
    public function waktu(): ?string
    {
        return $this->pada ? Carbon::parse($this->pada)->locale('id')->translatedFormat('j F Y, H.i') : null;
    }

    /** "Dikunci 2 Maret 2026, 10.00 oleh Koordinator PUSKOM." (bagian yang tidak tercatat dilewati). */
    public function kalimat(): string
    {
        $kalimat = 'Dikunci'.($this->waktu() ? ' '.$this->waktu() : '').($this->oleh ? ' oleh '.$this->oleh : '');

        // Nama bergelar seperti "M.Pd." sudah diakhiri titik.
        return str_ends_with($kalimat, '.') ? $kalimat : $kalimat.'.';
    }
}
