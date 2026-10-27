{{-- Cap air "DRAF" di kertas dokumen (StatusDraf): tetap bisa dicetak, tetapi jelas belum final. --}}
<div {{ $attributes->class(['pointer-events-none absolute inset-0 flex justify-center overflow-hidden select-none']) }} aria-hidden="true" data-testid="cap-draf">
    <span class="-rotate-30 text-[clamp(3rem,14cqw,7rem)] font-bold tracking-[0.2em] text-stone-900/[0.06]">DRAF</span>
</div>
