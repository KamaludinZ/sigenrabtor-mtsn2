@props(['gambar' => null, 'teks' => [], 'nilai' => [], 'garis' => false])

@use('App\Support\Dokumen\TeksCover')

{{--
    Cover KAK/TOR A4 potret: gambar desain (bila ada) memenuhi halaman, kotak teks diletakkan dengan persen halaman dan
    ukuran huruf berskala lebar halaman (satuan cqw), sehingga pratinjau sama di layar lebar maupun sempit.
    $teks harus sudah lewat TeksCover::rapikan()/bawaan(); $garis menampilkan batas kotak untuk menata posisi.
--}}
<div {{ $attributes->class(['@container relative mx-auto aspect-[210/297] w-full overflow-hidden bg-white shadow-sm ring-1 ring-stone-200']) }}
     data-testid="pratinjau-cover" data-gambar="{{ $gambar ? 'ya' : 'tidak' }}">
    @if ($gambar)
        <img src="{{ $gambar }}" alt="Desain cover" class="absolute inset-0 size-full object-cover" data-testid="gambar-cover">
    @else
        <div class="absolute inset-[4%] border-2 border-double border-stone-300" aria-hidden="true"></div>
    @endif

    @foreach ($teks as $kotak)
        <div @class(['absolute font-serif leading-tight break-words whitespace-pre-line', 'rounded-[2px] outline-1 outline-offset-1 outline-dashed outline-brand-500/80 bg-brand-50/30' => $garis])
             style="left: {{ $kotak['x'] }}%; top: {{ $kotak['y'] }}%; width: {{ $kotak['width'] }}%; text-align: {{ $kotak['align'] }}; font-weight: {{ $kotak['weight'] === 'bold' ? 700 : 400 }}; color: {{ $kotak['color'] }}; font-size: calc({{ $kotak['font_size'] }} * 100cqw / {{ TeksCover::LEBAR_PT }})"
             data-field="{{ $kotak['field'] }}" title="{{ TeksCover::FIELD[$kotak['field']] }}">{{ $nilai[$kotak['field']] ?? '' }}</div>
    @endforeach
</div>
