@props(['tempat', 'tahun', 'tautan' => ''])

{{--
    Tahun di dokumen (TahunDokumen::dari): header/judul yang disusun sistem selalu tahun aktif; isian yang menyebut tahun
    lain ditandai (mungkin sisa dokumen tahun lalu). Tiap tempat menautkan jangkar #cetak-{kunci}.
--}}
<div {{ $attributes->class(['mt-4 scroll-mt-40 border-t border-stone-100 pt-3']) }} id="tahun-cetak" data-testid="tahun-cetak">
    @php
        $beda = collect($tempat)->where('sinkron', false)->count();
    @endphp
    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tahun di dokumen</p>
        <span @class(['text-[11px] font-semibold', 'text-gold-700' => $beda > 0, 'text-brand-700' => $beda === 0]) data-testid="tahun-cetak-status">{{ $beda > 0 ? $beda.' perlu dicek' : 'Sinkron TA '.$tahun }}</span>
    </div>

    <ul class="mt-2 space-y-1.5 text-xs">
        @foreach ($tempat as $t)
            <li class="flex gap-1.5" data-tahun-tempat="{{ $t['kunci'] }}" data-jenis="{{ $t['jenis'] }}" data-sinkron="{{ $t['sinkron'] ? 'ya' : 'tidak' }}">
                <x-ui.icon :name="$t['sinkron'] ? 'check-circle' : 'alert'" class="mt-px size-3.5 shrink-0 {{ $t['sinkron'] ? 'text-brand-600' : 'text-gold-600' }}" />
                <div class="min-w-0">
                    <p class="text-stone-700">
                        <a href="{{ $tautan }}#cetak-{{ $t['kunci'] }}" class="hover:text-brand-800 hover:underline" data-lompat-cetak><span class="font-semibold">{{ $t['dokumen'] }}</span> · {{ $t['bagian'] }}</a>
                    </p>
                    <p class="mt-0.5 line-clamp-2 text-stone-500" data-teks-cetak>“{{ $t['teks'] }}”</p>
                    @unless ($t['sinkron'])
                        <p class="text-gold-700">Menyebut tahun {{ implode(', ', array_diff($t['tahun'], [$tahun])) }} — dokumen dicetak untuk TA {{ $tahun }}.</p>
                    @endunless
                </div>
            </li>
        @endforeach
    </ul>
</div>
