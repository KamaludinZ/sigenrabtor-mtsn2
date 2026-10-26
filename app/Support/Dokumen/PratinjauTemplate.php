<?php

namespace App\Support\Dokumen;

/**
 * Pratinjau isi master KAK/TOR: paragraf dokumen dipecah menjadi teks & variabel, lalu tiap variabel diberi status
 * (terdaftar / tidak) dan nilainya untuk contoh kegiatan × sumber dana (NilaiVariabel) — untuk mode "penanda" maupun
 * "uji isi" (PRD: admin menguji hasil penggantian sebelum template dipakai; variabel kosong ditandai).
 */
final readonly class PratinjauTemplate
{
    /**
     * @param  list<array{bagian: string, segmen: list<array{jenis: string, teks?: string, nama?: string, terdaftar?: bool, nilai?: string|list<string>|null}>}>  $paragraf
     */
    public function __construct(
        public array $paragraf,
    ) {}

    /**
     * @param  list<array{bagian: string, teks: string}>  $paragraf  Hasil PembacaTemplateDocx::paragraf().
     * @param  array<string, string|list<string>|null>  $nilai  Hasil NilaiVariabel::untuk().
     */
    public static function susun(array $paragraf, array $nilai): self
    {
        return new self(array_map(function (array $p) use ($nilai) {
            $segmen = [];
            $potong = preg_split('/(\$\{[^{}]*\})/u', $p['teks'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($potong as $bagian) {
                if (preg_match('/^\$\{([^{}]*)\}$/u', $bagian, $cocok)) {
                    $nama = trim((string) preg_replace('/\s+/u', ' ', $cocok[1]));
                    $terdaftar = VariabelTemplate::terdaftar($nama);
                    $segmen[] = ['jenis' => 'variabel', 'nama' => $nama, 'terdaftar' => $terdaftar, 'nilai' => $terdaftar ? ($nilai[$nama] ?? null) : null];
                } else {
                    $segmen[] = ['jenis' => 'teks', 'teks' => $bagian];
                }
            }

            return ['bagian' => $p['bagian'], 'segmen' => $segmen];
        }, $paragraf));
    }

    /**
     * Variabel terdaftar yang dipakai tetapi belum ada nilainya untuk contoh ini, urut kemunculan.
     *
     * @return list<string>
     */
    public function kosong(): array
    {
        return array_values(array_unique(array_column(array_filter($this->variabel(), fn (array $v) => $v['terdaftar'] && in_array($v['nilai'], [null, '', []], true)), 'nama')));
    }

    /**
     * @return list<string>
     */
    public function tidakTerdaftar(): array
    {
        return array_values(array_unique(array_column(array_filter($this->variabel(), fn (array $v) => ! $v['terdaftar']), 'nama')));
    }

    /**
     * @return list<array{jenis: string, nama: string, terdaftar: bool, nilai: string|list<string>|null}>
     */
    private function variabel(): array
    {
        return array_values(array_filter(array_merge([], ...array_column($this->paragraf, 'segmen')), fn (array $s) => $s['jenis'] === 'variabel'));
    }
}
