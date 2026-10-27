@props(['kunci'])

{{-- Cap pratinjau dokumen Final: tanda kunci + tanggal dikunci (KunciDokumen); hanya di layar, bukan teks dokumen. --}}
<div {{ $attributes->class(['pointer-events-none absolute top-4 right-4 z-10 -rotate-3 rounded-md border-2 border-brand-800 bg-white/80 px-2.5 py-1 text-center font-sans text-brand-800 select-none']) }}
     data-testid="cap-final" role="img" aria-label="Dokumen final terkunci{{ $kunci->waktu() ? ' sejak '.$kunci->waktu() : '' }}">
    <p class="flex items-center justify-center gap-1 text-[11px] leading-tight font-bold tracking-[0.2em] uppercase">
        <x-ui.icon name="lock" class="size-3" /> Final
    </p>
    <p class="text-[9px] leading-tight font-semibold tracking-wide uppercase">
        {{ $kunci->pada ? 'Terkunci '.\Illuminate\Support\Carbon::parse($kunci->pada)->locale('id')->translatedFormat('j M Y') : 'Terkunci' }}
    </p>
</div>
