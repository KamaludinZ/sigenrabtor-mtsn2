<?php

namespace Tests\Feature\Pengaturan;

use App\Support\Pengaturan\Nip;
use Tests\TestCase;

class NipTest extends TestCase
{
    /** Kasus bersama dengan nip()/galatNip() klien (tests/e2e/format.spec.js). */
    public function test_kasus_bersama_nip(): void
    {
        $kasus = json_decode(file_get_contents(base_path('tests/fixtures/nip.json')), true);

        foreach ($kasus as $k) {
            $this->assertSame($k['digit'], Nip::bersihkan($k['masukan']), $k['masukan']);
            $this->assertSame($k['format'], Nip::format($k['masukan']), $k['masukan']);
            $this->assertSame($k['galat'], Nip::galat($k['masukan']), $k['masukan']);
        }
        $this->assertSame('', Nip::format(null));
        $this->assertNull(Nip::galat(null));
    }
}
