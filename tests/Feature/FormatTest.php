<?php

namespace Tests\Feature;

use App\Support\Format;
use Tests\TestCase;

class FormatTest extends TestCase
{
    public function test_tanggal_memakai_nama_bulan_indonesia(): void
    {
        $this->assertSame('23 Desember 2025', Format::tanggal('2025-12-23'));
        $this->assertSame('3 Januari 2026', Format::tanggal('2026-01-03'));
    }

    public function test_tanggal_kosong_atau_tidak_valid_menghasilkan_null(): void
    {
        $this->assertNull(Format::tanggal(null));
        $this->assertNull(Format::tanggal(''));
        $this->assertNull(Format::tanggal('bukan-tanggal'));
    }
}
