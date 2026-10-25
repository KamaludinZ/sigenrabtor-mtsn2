@props([
    'sumber',
    'awalan' => null,
])

@use('App\Enums\SumberDana')

@php
    // Label sumber dana yang sama di semua layar (kartu RAB, Semua RAB, rekap, Data Kegiatan); warna per sumber dari enum.
    $warna = SumberDana::tryFrom($sumber)?->warna() ?? 'bg-stone-100 text-stone-700 ring-stone-300';
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 font-semibold leading-none ring-1 ring-inset', $warna]) }} data-sumber-dana="{{ $sumber }}">{{ $awalan ? $awalan.' '.$sumber : $sumber }}</span>
