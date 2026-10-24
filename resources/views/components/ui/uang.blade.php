@props([
    'nilai',
    'polos' => false,
    'tanda' => false,
])

{{--
    Tampilan nominal uang yang seragam (PRD F4): titik ribuan, awalan "Rp", angka sejajar (tabular).
    polos: tanpa "Rp" (untuk sel tabel yang judul kolomnya sudah "(Rp)").
    tanda: warnai negatif merah.
--}}
@php
    $teks = $polos ? \App\Support\Format::angka($nilai) : \App\Support\Format::rupiah($nilai);
@endphp
<span {{ $attributes->class(['whitespace-nowrap tabular-nums', 'text-red-600' => $tanda && $nilai < 0]) }}>{{ $teks }}</span>
