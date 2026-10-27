@use('App\Support\Format')

@php
    $cascade = $this->cascade();
@endphp

{{-- Pratinjau impor Perkin: sheet yang dibaca, kolom opsional yang tidak ada, dan susunan SK → IKSK dari baris siap. --}}
<div class="mt-4 space-y-3" data-testid="cascade-perkin">
    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
        <span class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 font-semibold text-stone-700"><x-ui.icon name="sheet" class="size-3.5" /> Sheet {{ $lembar }}</span>
        @if ($kolomHilang !== [])
            <span class="text-gold-700" data-testid="kolom-hilang-perkin">Kolom tidak ditemukan: {{ implode(', ', $kolomHilang) }} — isiannya dilengkapi nanti di Data Kegiatan.</span>
        @endif
    </p>

    <section class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 sm:p-4" aria-labelledby="judul-cascade-perkin">
        <h3 id="judul-cascade-perkin" class="text-sm font-semibold text-stone-900">Susunan cascade yang akan dibuat</h3>
        @if ($cascade === [])
            <p class="mt-1 text-xs text-stone-500">Belum ada baris yang siap diimpor.</p>
        @else
            <ol class="mt-2 space-y-2">
                @foreach ($cascade as $sk)
                    <li class="rounded-lg bg-white px-3 py-2 ring-1 ring-stone-200" data-sk="{{ $sk['kode'] ?? 'tanpa' }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                            <p class="min-w-0 text-sm">
                                @if ($sk['kode'])
                                    <span class="font-mono text-xs font-semibold text-brand-800">{{ $sk['kode'] }}</span>
                                @else
                                    <span class="text-xs font-semibold text-stone-500">Tanpa SK</span>
                                @endif
                                @if ($sk['uraian'])
                                    <span class="text-stone-700">{{ $sk['uraian'] }}</span>
                                @endif
                            </p>
                            <p class="shrink-0 text-xs text-stone-500 tabular-nums">
                                {{ $sk['kegiatan'] }} kegiatan @foreach ($sk['pagu'] as $sumber => $pagu) · {{ $sumber }} {{ Format::rupiah($pagu) }}@endforeach
                            </p>
                        </div>
                        <ul class="mt-1.5 space-y-1 border-l-2 border-stone-100 pl-3 text-xs">
                            @foreach ($sk['iksk'] as $iksk)
                                <li class="flex flex-wrap items-baseline justify-between gap-x-3" data-iksk="{{ $iksk['kode'] ?? 'tanpa' }}">
                                    <span class="min-w-0 text-stone-600">
                                        @if ($iksk['kode'])
                                            <span class="font-mono font-semibold text-stone-800">{{ $iksk['kode'] }}</span>
                                        @else
                                            <span class="font-semibold text-stone-500">Tanpa IKSK</span>
                                        @endif
                                        {{ $iksk['uraian'] }}
                                    </span>
                                    <span class="shrink-0 text-stone-500 tabular-nums">{{ $iksk['kegiatan'] }} kegiatan</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
