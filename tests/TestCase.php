<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Data tiruan (mis. kegiatan JSON) ditulis ke disk "local"; jangan sentuh berkas pengembangan.
        Storage::fake('local');
    }
}
