@props([
    'nilai',
    'sumber' => null,
    'pagu' => null,
    'terbilang' => null,
    'ringkas' => false,
])

@use('App\Support\Format')

@php
    $selisih = $pagu !== null ? $pagu - $nilai : null;
    $status = match (true) {
        $pagu === null => ['Pagu belum diisi', 'info', 'text-stone-500'],
        $nilai === $pagu => ['Sesuai pagu', 'check-circle', 'text-brand-700'],
        $nilai < $pagu => ['Sisa pagu '.Format::rupiah($selisih), 'check-circle', 'text-brand-700'],
        default => ['Melebihi pagu '.Format::rupiah(-$selisih), 'alert', 'text-red-600'],
    };
    $teksSalin = Format::rupiahDokumen($nilai).($terbilang ? ' ('.$terbilang.')' : '');
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
        <x-ui.uang :nilai="$nilai" @class(['font-bold text-stone-900', 'text-xl' => $ringkas, 'text-3xl' => ! $ringkas]) data-testid="total-anggaran-nilai" />
        <button type="button" title="Salin nominal & terbilang" aria-label="Salin nominal dan terbilang"
                class="shrink-0 rounded-md p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700"
                x-on:click="navigator.clipboard?.writeText(@js($teksSalin)).then(() => { tersalin = true; setTimeout(() => tersalin = false, 1500) })">
            <x-ui.icon name="check" class="size-4 text-brand-700" x-show="tersalin" x-cloak />
            <x-ui.icon name="copy" class="size-4" x-show="! tersalin" />
        </button>
    </div>

    @if ($terbilang)
        <p @class(['mt-1 italic leading-snug text-stone-600', 'text-xs' => $ringkas, 'text-sm' => ! $ringkas]) data-testid="total-anggaran-terbilang">“{{ $terbilang }}”</p>
    @else
        <p class="mt-1 text-xs text-stone-400" data-testid="total-anggaran-terbilang">Terbilang disusun otomatis dari total.</p>
    @endif

    @if ($status)
        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium {{ $status[2] }}" data-testid="total-anggaran-status">
            <x-ui.icon :name="$status[1]" class="size-3.5 shrink-0" />
            {{ $status[0] }}
        </p>
    @endif
</div>
