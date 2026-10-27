@props(['kegiatan', 'sumber', 'rentang'])

@use('App\Support\Dokumen\BukuDokumen')
@use('App\Support\Dokumen\CoverDokumen')
@use('App\Support\Dokumen\SusunanKak')
@use('App\Support\Dokumen\SusunanRab')
@use('App\Support\Dokumen\TeksCover')

@php
    // Bagian satu kegiatan di dalam buku (PRD §7B): cover kegiatan, KAK/TOR, RAB sumber dana buku — dengan rentang halaman.
    $kak = SusunanKak::dari($kegiatan, $sumber);
    $rab = SusunanRab::dari($kegiatan, $sumber);
    $desain = CoverDokumen::desain();
    $lembar = [
        'cover' => ['Cover kegiatan', BukuDokumen::teksRentang($rentang['cover'], null)],
        'kak' => ['KAK/TOR', BukuDokumen::teksRentang(...$rentang['kak'])],
        'rab' => ['RAB '.$sumber, BukuDokumen::teksRentang(...$rentang['rab'])],
    ];
@endphp

<div {{ $attributes->class(['space-y-6']) }} data-testid="dokumen-kegiatan-buku" data-kegiatan="{{ $kegiatan['id'] }}">
    @foreach ($lembar as $kunci => [$label, $halaman])
        <section aria-label="{{ $label }}" data-lembar="{{ $kunci }}">
            <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">
                {{ $label }}
                <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium tracking-normal normal-case text-stone-600 ring-1 ring-stone-200" data-rentang>{{ $halaman ?? 'tanpa nomor' }}</span>
                @if (($kunci === 'rab' ? $rab : $kak)->draf)
                    <x-dokumen.kepala-draf :status="$kunci === 'rab' ? $rab->statusDraf : $kak->statusDraf" />
                @endif
            </p>
            @switch($kunci)
                @case('cover')
                    <x-dokumen.cover :gambar="$desain['gambar']" :teks="$desain['teks']" :nilai="TeksCover::nilai($kegiatan, $sumber)" :draf="$kak->draf" :jangkar="false" class="max-w-[210mm]" />
                    @break
                @case('kak')
                    <x-dokumen.kak-tor :susunan="$kak" :bagian="range(1, 9)" />
                    @break
                @default
                    <x-dokumen.rab :susunan="$rab" />
            @endswitch
        </section>
    @endforeach
</div>
