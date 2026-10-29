<?php

namespace App\Livewire\Dokumen;

use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman Riwayat Dokumen (PRD §7, F28; `/dokumen`, rute `dokumen.index`, semua pengguna): semua berkas KAK/TOR, RAB, dan
 * set dokumen yang pernah dicetak, diunduh, atau diarsipkan dalam cakupan akun (RiwayatDokumen::untuk), dikelompokkan per
 * tahun anggaran (terbaru dulu), dengan ringkasan jumlah dokumen, kegiatan, arsip final, dan dokumen bercap DRAF.
 */
#[Layout('layouts.app')]
class Riwayat extends Component
{
    /** @return list<array<string, mixed>> */
    #[Computed]
    public function semua(): array
    {
        return RiwayatDokumen::untuk(StubData::currentUser());
    }

    public function render()
    {
        return view('livewire.dokumen.riwayat')->title('Riwayat Dokumen');
    }
}
