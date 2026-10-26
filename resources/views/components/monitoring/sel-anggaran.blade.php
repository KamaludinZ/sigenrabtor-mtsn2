@props(['sel', 'garis' => true, 'tebal' => false])

@use('App\Support\Format')

{{--
    Tiga sel tabel (Pagu, Total RAB, Selisih) satu kelompok sumber dana dari RingkasanAnggaran::sel(). Selisih kosong (—)
    bila belum ada RAB atau ada RAB yang pagunya belum diisi; RAB yang melebihi pagunya sendiri selalu disebut walau
    selisih gabungannya masih sisa. Melebihi = merah + ikon + tanda minus (warna bukan satu-satunya penanda).
--}}
@php
    $kosong = $sel['rab'] === 0;
    $lebih = $sel['selisih'] !== null && $sel['selisih'] < 0;
@endphp

<td @class(['px-3 py-2.5 text-right whitespace-nowrap tabular-nums', 'border-l border-stone-100' => $garis, 'text-stone-300' => $kosong, 'text-stone-700' => ! $kosong, 'font-semibold' => $tebal])>
    {{ $kosong ? '—' : Format::rupiah($sel['pagu']) }}
</td>
<td @class(['px-3 py-2.5 text-right whitespace-nowrap tabular-nums', 'text-stone-300' => $kosong, 'text-stone-900' => ! $kosong, 'font-semibold' => ! $kosong])>
    {{ $kosong ? '—' : Format::rupiah($sel['total']) }}
</td>
<td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums" @if ($sel['persen'] !== null) title="Pemakaian pagu {{ Format::angka($sel['persen']) }}%" @endif data-selisih="{{ $sel['selisih'] ?? '' }}">
    @if ($kosong)
        <span class="text-stone-300">—</span>
    @elseif ($sel['selisih'] === null)
        <span class="text-stone-400">—</span>
    @else
        <span @class(['inline-flex items-center gap-1', 'font-semibold text-red-700' => $lebih, 'text-stone-700' => ! $lebih, 'font-semibold' => $tebal])>
            @if ($lebih)
                <x-ui.icon name="alert" class="size-3.5 shrink-0" />
            @endif
            {{ Format::rupiah($sel['selisih']) }}
        </span>
    @endif
    @if ($sel['tanpa_pagu'] > 0)
        <span class="block text-[11px] font-medium text-gold-700">{{ $sel['tanpa_pagu'] }} belum berpagu</span>
    @endif
    @if ($sel['melebihi'] > 0 && ! $lebih)
        <span class="block text-[11px] font-medium text-red-700"><x-ui.icon name="alert" class="inline size-3" /> {{ $sel['melebihi'] }} RAB melebihi</span>
    @elseif ($sel['melebihi'] > 0 && $sel['rab'] > 1)
        <span class="block text-[11px] text-red-700">{{ $sel['melebihi'] }} dari {{ $sel['rab'] }} RAB melebihi</span>
    @endif
</td>
