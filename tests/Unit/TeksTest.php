<?php

namespace Tests\Unit;

use App\Support\Teks;
use PHPUnit\Framework\TestCase;

class TeksTest extends TestCase
{
    public function test_rapikan_menggabungkan_spasi_berulang_seperti_di_dokumen_lama(): void
    {
        $this->assertSame(
            'Peningkatan pelayanan dan kualitas pendidikan perlu terus diupayakan.',
            Teks::rapikan('Peningkatan   pelayanan   dan kualitas  pendidikan perlu terus diupayakan.  '),
        );
    }

    public function test_rapikan_mempertahankan_paragraf_tetapi_membuang_baris_kosong_berlebih(): void
    {
        $this->assertSame(
            "Paragraf satu.\n\nParagraf dua.\nBaris lanjut.",
            Teks::rapikan("  Paragraf satu.  \r\n\r\n\r\n\n  Paragraf dua.\t\nBaris lanjut. "),
        );
    }
}
