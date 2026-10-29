<?php

namespace App\Support\Dokumen\Generasi;

/**
 * Hasil GeneratorBerkasDokumen: isi biner, nama berkas baku (CetakDokumen::namaBerkas / StatusCetak::namaBerkas, bersufiks
 * _DRAF hanya bila lembarnya DRAF), format, cap DRAF + alasan, dan permintaannya. `header()` = header jawaban unduhan
 * (Content-Type, Content-Disposition attachment, Content-Length, X-Nama-Berkas, X-Format-Berkas, X-Draf, no-store).
 */
final class BerkasDokumen
{
    /** @param  list<string>  $alasanDraf */
    public function __construct(
        public readonly string $isi,
        public readonly string $nama,
        public readonly string $format,
        public readonly bool $draf,
        public readonly array $alasanDraf,
        public readonly PermintaanBerkas $permintaan,
    ) {}

    public function mime(): string
    {
        return FormatBerkas::mime($this->format);
    }

    public function ukuran(): int
    {
        return strlen($this->isi);
    }

    /** @return array<string, string> */
    public function header(): array
    {
        return self::headerUnduhan($this->nama, $this->format, $this->draf, $this->ukuran());
    }

    /**
     * Header jawaban unduhan berkas dokumen (dipakai juga endpoint ekspor RAB yang tidak lewat GeneratorBerkasDokumen).
     *
     * @return array<string, string>
     */
    public static function headerUnduhan(string $nama, string $format, bool $draf, int $ukuran): array
    {
        return [
            'Content-Type' => FormatBerkas::mime($format),
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $nama).'"; filename*=UTF-8\'\''.rawurlencode($nama),
            'Content-Length' => (string) $ukuran,
            'X-Nama-Berkas' => $nama,
            'X-Format-Berkas' => $format,
            'X-Draf' => $draf ? '1' : '0',
            'Cache-Control' => 'no-store, private',
        ];
    }
}
