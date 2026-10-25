@props([
    'nilai',
    'sumber' => null,
    'pagu' => null,
    'terbilang' => null,
    'ringkas' => false,
    'kosong' => 'Belum ada rincian belanja — terbilang muncul setelah item ditambahkan.',
])

@use('App\Support\Format')
@use('App\Support\Rab\StatusPagu')
@use('App\Support\Terbilang')

@php
    // Terbilang selalu diturunkan dari angka (PRD F3); prop hanya untuk menimpa dalam kasus khusus.
    // Nilai nol/kosong tidak diterbilangkan ("Nol Rupiah" menyesatkan untuk RAB yang belum diisi).
    $nilai = $nilai === null ? null : (int) $nilai;
    $terbilang ??= Terbilang::tampil($nilai);
    $status = StatusPagu::hitung((int) $nilai, $pagu === null ? null : (int) $pagu);
    $teksSalin = Format::rupiahDokumen((int) $nilai).($terbilang ? ' ('.$terbilang.')' : '');
@endphp

{{--
    Tampilan total anggaran RAB yang seragam: total RAB = total KAK = jumlah yang diajukan (PRD F2),
    disertai terbilang (PRD F3) dan status terhadap pagu (PRD F12).
--}}
<div {{ $attributes->class(['min-w-0']) }} data-testid="total-anggaran" x-data="{ tersalin: false }">
    <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">
        Jumlah yang diajukan{{ $sumber ? ' · RAB '.$sumber : '' }}
    </p>

    <div class="mt-1 flex items-start justify-between gap-2">
        <x-ui.uang :nilai="(int) $nilai" @class(['font-bold text-stone-900', 'text-xl' => $ringkas, 'text-3xl' => ! $ringkas]) data-testid="total-anggaran-nilai" />
        <button type="button" title="Salin nominal & terbilang" aria-label="Salin nominal dan terbilang"
                class="shrink-0 rounded-md p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700"
                data-teks="{{ $teksSalin }}" data-testid="salin-total-anggaran"
                x-on:click="navigator.clipboard?.writeText($el.dataset.teks).then(() => { tersalin = true; setTimeout(() => tersalin = false, 1500) })">
            <x-ui.icon name="check" class="size-4 text-brand-700" x-show="tersalin" x-cloak />
            <x-ui.icon name="copy" class="size-4" x-show="! tersalin" />
        </button>
    </div>

    @if ($terbilang)
        <p @class(['mt-1 italic leading-snug text-stone-600', 'text-xs' => $ringkas, 'text-sm' => ! $ringkas]) data-testid="total-anggaran-terbilang">“{{ $terbilang }}”</p>
    @else
        <p class="mt-1 text-xs leading-snug text-stone-400" data-testid="total-anggaran-terbilang-kosong">{{ $kosong }}</p>
    @endif

    <p class="mt-2 text-xs">
        <x-rab.status-pagu :status="$status" data-testid="total-anggaran-status" />
    </p>
</div>
