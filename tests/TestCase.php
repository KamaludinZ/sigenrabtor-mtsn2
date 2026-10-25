<?php

namespace Tests;

use App\Support\Stub\StubKegiatanRepository;
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

    /** Banyaknya contoh kegiatan yang disiapkan repository tiruan. */
    protected function jumlahContohKegiatan(): int
    {
        return count(StubKegiatanRepository::contohSemua());
    }

    /** Id yang akan diberikan pada kegiatan baru pertama dalam satu tes. */
    protected function idKegiatanBaru(): int
    {
        return $this->jumlahContohKegiatan() + 1;
    }
}
