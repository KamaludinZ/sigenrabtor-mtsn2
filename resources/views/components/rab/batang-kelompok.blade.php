@props(['kelompok', 'baris'])

@use('App\Support\Format')

{{--
    Batang komposisi kelompok belanja (bahan/jasa/lain): satu batang 100% per baris (mis. per sumber dana + gabungan),
    segmen berwarna kelompok dengan celah 2px, label aria per segmen, dan tooltip saat hover/fokus (nilai lebih dulu, kunci
    warna berupa garis). $kelompok = list {kunci, label}; $baris = list {label, total, nilai: kunci → rupiah}. Dipakai
    rekap RAB per kegiatan dan Monitoring Anggaran.
--}}
@php
    $warna = ['bahan' => 'bg-kelompok-bahan', 'jasa' => 'bg-kelompok-jasa', 'lain' => 'bg-kelompok-lain'];
@endphp

<div {{ $attributes->class(['relative space-y-3']) }} x-data="{ tip: null }" x-on:mouseleave="tip = null">
    @foreach ($baris as $b)
        <div class="grid grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[5.5rem_minmax(0,1fr)_7.5rem]">
            <span class="text-sm font-medium text-stone-600">{{ $b['label'] }}</span>
            @if ($b['total'] > 0)
                <div class="flex h-6 gap-0.5" role="img" data-testid="batang-{{ \Illuminate\Support\Str::slug($b['label']) }}"
                     aria-label="{{ $b['label'] }}: {{ collect($kelompok)->map(fn ($k) => $k['label'].' '.Format::rupiah($b['nilai'][$k['kunci']]))->implode(', ') }}">
                    @foreach ($kelompok as $k)
                        @php $nilai = $b['nilai'][$k['kunci']]; @endphp
                        @if ($nilai > 0)
                            <div tabindex="0" style="width: {{ round($nilai / $b['total'] * 100, 2) }}%"
                                 class="h-full min-w-1 cursor-default {{ $warna[$k['kunci']] }} last:rounded-r focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                                 aria-label="{{ $k['label'] }} {{ Format::rupiah($nilai) }}"
                                 x-on:mouseenter="tip = { kunci: @js($k['kunci']), label: @js($b['label'].' · '.$k['label']), nilai: @js(Format::rupiah($nilai)), persen: @js(Format::angka(round($nilai / $b['total'] * 100, 1)).'%'), x: $el.offsetLeft + $el.offsetWidth / 2, y: $el.offsetTop }"
                                 x-on:focus="tip = { kunci: @js($k['kunci']), label: @js($b['label'].' · '.$k['label']), nilai: @js(Format::rupiah($nilai)), persen: @js(Format::angka(round($nilai / $b['total'] * 100, 1)).'%'), x: $el.offsetLeft + $el.offsetWidth / 2, y: $el.offsetTop }"
                                 x-on:blur="tip = null"></div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flex h-6 items-center rounded border border-dashed border-stone-300 px-2 text-xs text-stone-400">Belum ada item</div>
            @endif
            <x-ui.uang :nilai="$b['total']" class="col-start-2 text-right text-sm font-semibold text-stone-900 sm:col-start-auto" />
        </div>
    @endforeach

    {{-- Tooltip: nilai di depan, label menyusul; kunci warna berupa garis pendek --}}
    <div x-cloak x-show="tip" x-transition.opacity.duration.100ms role="tooltip"
         class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-stone-900 px-3 py-2 text-xs text-white shadow-lg"
         :style="tip && `left: ${tip.x}px; top: ${tip.y - 8}px`">
        <p class="flex items-center gap-2">
            <span class="h-0.5 w-3 rounded" :style="tip && `background: var(--color-kelompok-${tip.kunci})`"></span>
            <strong class="text-sm" x-text="tip?.nilai"></strong>
            <span class="text-stone-300" x-text="tip?.persen"></span>
        </p>
        <p class="mt-0.5 text-stone-300" x-text="tip?.label"></p>
    </div>
</div>
