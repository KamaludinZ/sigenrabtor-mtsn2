{{-- Satu kode SK/IKSK di tabel pratinjau Perkin: kode + asalnya, atau kode di luar master yang dikosongkan (dicoret). --}}
@php
    $kode = $p[$jenis];
    $kosong = collect($p['dikosongkan'])->first(fn ($k) => str_starts_with($k, $jenis === 'sk' ? 'SK' : 'IKSK'));
    $asal = ['atas' => 'dari sel gabung di atas', 'induk' => 'dari induk IKSK'][$p[$jenis.'_asal']] ?? null;
@endphp
@if ($kode)
    <span class="font-mono text-xs font-semibold text-stone-800" title="{{ $p[$jenis.'_uraian'] }}">{{ $kode }}</span>
    @if ($asal)
        <span class="block text-[11px] text-stone-400">{{ $asal }}</span>
    @endif
@elseif ($kosong)
    <span class="font-mono text-xs text-gold-700 line-through" title="Tidak ada di master — dikosongkan">{{ $kosong }}</span>
@else
    <span class="text-stone-300">—</span>
@endif
