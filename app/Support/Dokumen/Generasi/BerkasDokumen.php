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
        return [
            'Content-Type' => $this->mime(),
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $this->nama).'"; filename*=UTF-8\'\''.rawurlencode($this->nama),
            'Content-Length' => (string) $this->ukuran(),
            'X-Nama-Berkas' => $this->nama,
            'X-Format-Berkas' => $this->format,
            'X-Draf' => $this->draf ? '1' : '0',
            'Cache-Control' => 'no-store, private',
        ];
    }
}
