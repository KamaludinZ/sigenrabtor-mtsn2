<?php

namespace App\Support\Dokumen;

use App\Enums\StatusKegiatan;
use App\Support\Rab\PeringatanPagu;

/**
 * Cap "DRAF" dokumen (PRD aturan 8): dokumen tetap bisa dicetak, tetapi bercap DRAF selama kegiatan belum disetujui,
 * isiannya belum lengkap, atau RAB-nya melebihi pagu. Satu-satunya aturan cap — dipakai SusunanKak & SusunanRab — beserta
 * alasan yang ditampilkan agar pengguna tahu cara menghilangkannya.
 */
final readonly class StatusDraf
{
    /** Status kegiatan yang dokumennya boleh tanpa cap DRAF (selama lengkap & dalam pagu); status lain selalu DRAF. */
    public const STATUS_TANPA_CAP = [StatusKegiatan::Disetujui, StatusKegiatan::Final];

    /**
     * @param  list<array{kunci: string, teks: string}>  $alasan  Kunci: status | kurang | pagu.
     */
    public function __construct(
        public bool $draf,
        public array $alasan,
    ) {}

    /**
     * @param  list<string>  $kurang
     */
    public static function hitung(StatusKegiatan $status, array $kurang, ?PeringatanPagu $peringatanPagu = null): self
    {
        $alasan = [];
        if (! in_array($status, self::STATUS_TANPA_CAP, true)) {
            $alasan[] = ['kunci' => 'status', 'teks' => "Belum disetujui admin (status {$status->label()})."];
        }
        if ($kurang !== []) {
            $contoh = implode(', ', array_slice($kurang, 0, 3)).(count($kurang) > 3 ? ', dan '.(count($kurang) - 3).' lainnya' : '');
            $alasan[] = ['kunci' => 'kurang', 'teks' => count($kurang)." isian belum lengkap: {$contoh}."];
        }
        if ($peringatanPagu?->status->melebihi()) {
            $alasan[] = ['kunci' => 'pagu', 'teks' => "RAB {$peringatanPagu->sumber} ".mb_lcfirst($peringatanPagu->status->teks()).'.'];
        }

        return new self($alasan !== [], $alasan);
    }

    /** Gabungan beberapa dokumen satu set (cover + KAK/TOR + RAB): DRAF bila salah satunya DRAF, alasan tanpa dobel. */
    public static function gabung(self ...$status): self
    {
        $alasan = collect($status)->flatMap(fn (self $s) => $s->alasan)->unique('teks')->values()->all();

        return new self(collect($status)->contains(fn (self $s) => $s->draf), $alasan);
    }
}
