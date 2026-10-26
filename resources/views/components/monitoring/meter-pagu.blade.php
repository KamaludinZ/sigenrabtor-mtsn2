@props(['sumber', 'pagu', 'total', 'tanpaPagu' => 0, 'kepala' => true])

@use('App\Support\Format')
@use('App\Support\Rab\StatusPagu')
@use('App\Support\Rab\TabelPagu')

{{--
    Meter pemakaian pagu satu sumber dana: isi = total RAB terhadap pagu (persen dibulatkan menjauhi 100 seperti
    TabelPagu), jalur = langkah lebih muda hue yang sama. Melebihi pagu memakai warna bahaya + ikon + teks StatusPagu
    (warna tidak pernah satu-satunya penanda). Pagu 0 = belum diisi. :kepala="false" menyembunyikan baris lencana,
    persen & nominal bila kartu pemakainya sudah menampilkannya.
--}}
@php
    $status = StatusPagu::hitung($total, $pagu > 0 ? $pagu : null);
    $persen = TabelPagu::persen($total, $pagu > 0 ? $pagu : null);
    $lebar = $persen === null ? 0 : min(100, $persen);
    $bahaya = $status->melebihi();
@endphp

<div {{ $attributes->class(['min-w-0']) }} data-testid="meter-pagu-{{ mb_strtolower($sumber) }}" data-status="{{ $status->status }}">
    @if ($kepala)
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
            <p class="flex items-center gap-2 text-sm font-medium text-stone-800">
                <x-rab.lencana-sumber :sumber="$sumber" />
                <span class="tabular-nums">{{ $persen === null ? '—' : str_replace('.', ',', (string) $persen).'%' }}</span>
            </p>
            <p class="text-xs text-stone-500 tabular-nums">{{ Format::rupiah($total) }} dari {{ $pagu > 0 ? Format::rupiah($pagu) : 'pagu belum diisi' }}</p>
        </div>
    @endif
    <div class="h-2.5 overflow-hidden rounded-full {{ $bahaya ? 'bg-red-100' : 'bg-brand-100' }}" role="meter" aria-valuemin="0" aria-valuemax="100"
         aria-valuenow="{{ $persen ?? 0 }}" aria-label="Pemakaian pagu {{ $sumber }}">
        <div class="h-full rounded-full {{ $bahaya ? 'bg-red-600' : 'bg-brand-600' }}" style="width: {{ $lebar }}%"></div>
    </div>
    <p @class(['mt-1.5 flex items-center gap-1 text-xs font-medium', 'text-red-700' => $bahaya, 'text-brand-700' => $status->nada() === 'aman', 'text-stone-500' => $status->nada() === 'netral'])>
        <x-ui.icon :name="$status->ikon()" class="size-3.5 shrink-0" /> {{ $status->teks() }}
        @if ($tanpaPagu > 0)
            <span class="font-normal text-gold-700">· {{ $tanpaPagu }} RAB belum berpagu</span>
        @endif
    </p>
</div>
