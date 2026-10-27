@props(['hilang' => [], 'tahun' => null, 'href' => null])

{{--
    Lencana ringkas kode master yang hilang untuk baris daftar: "n kode hilang" (atau "n kode tak ada di TA …" bila $tahun
    pembandingnya diberikan, mis. sumber duplikat) dengan rincian di title (butir berisi label & kode — dari
    KodeHilang::dari() atau ::tidakAdaDi()). $href = tautan ke halaman Kode & Klasifikasi.
--}}
@if ($hilang !== [])
    @php
        $rincian = ($tahun ? "Tidak ada di master TA {$tahun}: " : 'Kode master hilang: ').implode(', ', array_map(fn ($h) => $h['label'].' '.$h['kode'], $hilang));
    @endphp
    <{{ $href ? 'a' : 'span' }} @if ($href) href="{{ $href }}" wire:navigate @endif title="{{ $rincian }}"
        {{ $attributes->class(['inline-flex items-center gap-1 rounded bg-gold-50 px-1.5 py-px text-[10px] font-semibold text-gold-700 ring-1 ring-gold-200 ring-inset', 'hover:bg-gold-100' => $href]) }}
        data-testid="lencana-kode-hilang">
        <x-ui.icon name="alert" class="size-3" /> {{ count($hilang) }} {{ $tahun ? "kode tak ada di TA {$tahun}" : 'kode hilang' }}
        <span class="sr-only">— {{ $rincian }}</span>
    </{{ $href ? 'a' : 'span' }}>
@endif
