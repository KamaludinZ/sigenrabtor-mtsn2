@props(['ringkasan', 'sumber' => null])

@use('App\Enums\SumberDana')
@use('App\Support\Format')

{{--
    Kartu ringkasan anggaran (KPI row) dari App\Support\Monitoring\RingkasanAnggaran — dipakai Dashboard dan Monitoring
    Anggaran: kegiatan per status, total pagu & total RAB per sumber dana, dan sisa/kelebihan pagu. $sumber membatasi
    rincian ke satu sumber dana.
--}}
@php
    $perSumber = $ringkasan->perSumber();
    $total = $ringkasan->total();
    $perStatus = $ringkasan->perStatus();
    $sumberTampil = $sumber === null ? SumberDana::urutan() : [$sumber];
@endphp

<section {{ $attributes->class(['grid gap-4 sm:grid-cols-2 xl:grid-cols-4']) }} aria-label="Ringkasan anggaran" data-testid="ringkasan-monitoring">
    <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-kegiatan">
        <h2 class="text-xs font-semibold text-stone-500">Kegiatan</h2>
        <p class="mt-1 text-2xl font-bold text-stone-900">{{ Format::angka($ringkasan->jumlahKegiatan()) }}</p>
        <ul class="mt-3 flex flex-wrap gap-1.5" aria-label="Kegiatan per status">
            @foreach ($perStatus as $kunciStatus => $jumlahStatus)
                @if ($jumlahStatus > 0)
                    <li class="inline-flex items-center gap-1" data-status-jumlah="{{ $kunciStatus }}">
                        <x-ui.status-badge :status="$kunciStatus" /> <span class="text-xs font-semibold text-stone-600 tabular-nums">{{ $jumlahStatus }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-pagu">
        <h2 class="text-xs font-semibold text-stone-500">Total pagu</h2>
        <p class="mt-1 text-2xl font-bold text-stone-900"><x-ui.uang :nilai="$total['pagu']" /></p>
        <dl class="mt-3 space-y-1 text-xs">
            @foreach ($sumberTampil as $s)
                <div class="flex justify-between gap-2"><dt><x-rab.lencana-sumber :sumber="$s" /></dt><dd class="font-semibold text-stone-700 tabular-nums">{{ Format::rupiah($perSumber[$s]['pagu']) }}</dd></div>
            @endforeach
        </dl>
        @if ($total['tanpa_pagu'] > 0)
            <p class="mt-2 text-[11px] font-medium text-gold-700">{{ $total['tanpa_pagu'] }} RAB belum berpagu</p>
        @endif
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-rab">
        <h2 class="text-xs font-semibold text-stone-500">Total RAB</h2>
        <p class="mt-1 text-2xl font-bold text-stone-900"><x-ui.uang :nilai="$total['total']" /></p>
        <dl class="mt-3 space-y-1 text-xs">
            @foreach ($sumberTampil as $s)
                <div class="flex justify-between gap-2"><dt><x-rab.lencana-sumber :sumber="$s" /></dt><dd class="font-semibold text-stone-700 tabular-nums">{{ Format::rupiah($perSumber[$s]['total']) }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-2 text-[11px] text-stone-500">{{ $total['rab'] }} RAB dari {{ $ringkasan->jumlahKegiatan() }} kegiatan</p>
    </article>
    <article @class(['rounded-2xl p-5 shadow-xs ring-1', 'bg-red-50 ring-red-200' => $total['melebihi'] > 0, 'bg-white ring-stone-200' => $total['melebihi'] === 0]) data-testid="kartu-selisih">
        <h2 class="text-xs font-semibold text-stone-500">{{ $total['selisih'] >= 0 ? 'Sisa pagu' : 'Melebihi pagu' }}</h2>
        <p @class(['mt-1 text-2xl font-bold', 'text-stone-900' => $total['selisih'] >= 0, 'text-red-700' => $total['selisih'] < 0])><x-ui.uang :nilai="abs($total['selisih'])" /></p>
        <p class="mt-3 text-xs text-stone-600">Pagu dikurangi total RAB seluruh kegiatan.</p>
        @if ($total['melebihi'] > 0)
            <p class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-red-700" data-testid="jumlah-melebihi"><x-ui.icon name="alert" class="size-3.5" /> {{ $total['melebihi'] }} RAB melebihi pagunya</p>
        @endif
    </article>
</section>
