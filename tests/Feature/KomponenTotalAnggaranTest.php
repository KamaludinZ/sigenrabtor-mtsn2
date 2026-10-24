<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class KomponenTotalAnggaranTest extends TestCase
{
    private function render(string $atribut): string
    {
        return Blade::render("<x-rab.total-anggaran {$atribut} />");
    }

    public function test_menampilkan_nominal_sumber_dan_penanda_terbilang(): void
    {
        $html = $this->render(':nilai="10000000" sumber="Komite"');

        $this->assertStringContainsString('Jumlah yang diajukan · RAB Komite', $html);
        $this->assertStringContainsString('Rp10.000.000', $html);
        $this->assertStringContainsString('Terbilang disusun otomatis dari total.', $html);
    }

    public function test_menampilkan_terbilang_bila_tersedia(): void
    {
        $html = $this->render(':nilai="10000000" terbilang="Sepuluh Juta Rupiah"');

        $this->assertStringContainsString('“Sepuluh Juta Rupiah”', $html);
        $this->assertStringContainsString('Rp10.000.000,- (Sepuluh Juta Rupiah)', html_entity_decode($html), 'Teks salin memakai gaya dokumen.');
    }

    public function test_status_pagu(): void
    {
        $this->assertStringContainsString('Sesuai pagu', $this->render(':nilai="100" :pagu="100"'));
        $this->assertStringContainsString('Sisa pagu Rp40', $this->render(':nilai="60" :pagu="100"'));
        $this->assertStringContainsString('Melebihi pagu Rp20', $this->render(':nilai="120" :pagu="100"'));
        $this->assertStringContainsString('Pagu belum diisi', $this->render(':nilai="120"'));
        $this->assertStringContainsString('text-red-600', $this->render(':nilai="120" :pagu="100"'));
    }

    public function test_mode_ringkas_memakai_ukuran_lebih_kecil(): void
    {
        $this->assertStringContainsString('text-xl', $this->render(':nilai="1" ringkas'));
        $this->assertStringContainsString('text-3xl', $this->render(':nilai="1"'));
    }
}
