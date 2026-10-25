@props([
    'mekanisme',
    'sumber',
])

@use('App\Support\Format')

@php
    /** @var \App\Support\Rab\MekanismePembayaran $mekanisme */
    $baris = $mekanisme->barisTabel();
    $nada = ['pas' => 'text-brand-700', 'kurang' => 'text-gold-700', 'lebih' => 'text-red-600', 'sekaligus' => 'text-stone-500'][$mekanisme->status()];
    $persen = fn (float $p) => str_replace('.', ',', (string) $p).'%';
@endphp

{{--
    Tabel termin baca-saja satu RAB: Termin, Tanggal, Nominal, %, Kumulatif, Terbilang, lalu Jumlah termin vs total RAB.
    Kartu per termin bila wadahnya sempit. Dipakai halaman Termin (terkunci) dan Semua RAB.
--}}
<div {{ $attributes->class(['@container min-w-0']) }} data-testid="tabel-termin-{{ mb_strtolower($sumber) }}">
    @if ($baris === [])
        <p class="rounded-lg border border-dashed border-stone-300 px-4 py-4 text-center text-sm text-stone-500">
            RAB {{ $sumber }} dibayar sekaligus sebesar {{ Format::rupiah($mekanisme->total) }} — tanpa termin.
        </p>
    @else
        <table class="hidden w-full text-left text-sm @xl:table">
            <caption class="sr-only">Termin pembayaran RAB {{ $sumber }}</caption>
            <thead class="border-b border-stone-200 text-xs font-semibold text-stone-500">
                <tr>
                    <th scope="col" class="px-3 py-2">Termin</th>
                    <th scope="col" class="px-3 py-2">Tanggal</th>
                    <th scope="col" class="px-3 py-2 text-right">Nominal</th>
                    <th scope="col" class="px-3 py-2 text-right">%</th>
                    <th scope="col" class="px-3 py-2 text-right">Kumulatif</th>
                    <th scope="col" class="px-3 py-2">Terbilang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($baris as $b)
                    <tr data-termin="{{ $b['nomor'] }}">
                        <th scope="row" class="px-3 py-2 font-semibold text-stone-700">{{ $b['nomor'] }}</th>
                        <td class="px-3 py-2 whitespace-nowrap text-stone-700">{{ $b['tanggal']?->locale('id')->translatedFormat('j F Y') ?? 'Tanggal belum diisi' }}</td>
                        <td class="px-3 py-2 text-right font-medium text-stone-900 tabular-nums whitespace-nowrap">{{ Format::rupiah($b['nominal']) }}</td>
                        <td class="px-3 py-2 text-right text-stone-500 tabular-nums">{{ $persen($b['persen']) }}</td>
                        <td class="px-3 py-2 text-right text-stone-500 tabular-nums whitespace-nowrap">{{ Format::rupiah($b['kumulatif']) }}</td>
                        <td class="px-3 py-2 text-xs text-stone-500 italic">{{ $b['terbilang'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-stone-200">
                <tr>
                    <th scope="row" colspan="2" class="px-3 py-2 text-right text-xs font-semibold text-stone-600 uppercase">Jumlah termin</th>
                    <td class="px-3 py-2 text-right font-bold text-stone-900 tabular-nums whitespace-nowrap">{{ Format::rupiah($mekanisme->jumlah()) }}</td>
                    <td colspan="3" class="px-3 py-2 text-xs font-medium {{ $nada }}">{{ $mekanisme->teksStatus() }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- Wadah sempit: kartu per termin --}}
        <ol class="divide-y divide-stone-100 rounded-lg bg-white ring-1 ring-stone-200 @xl:hidden">
            @foreach ($baris as $b)
                <li class="flex items-start justify-between gap-3 px-3 py-2.5 text-sm" data-termin-kartu="{{ $b['nomor'] }}">
                    <span class="min-w-0">
                        <span class="block font-semibold text-stone-800">Termin {{ $b['nomor'] }}</span>
                        <span class="block text-xs text-stone-500">{{ $b['tanggal']?->locale('id')->translatedFormat('j F Y') ?? 'Tanggal belum diisi' }} · {{ $persen($b['persen']) }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block font-medium text-stone-900 tabular-nums">{{ Format::rupiah($b['nominal']) }}</span>
                        <span class="block text-[11px] text-stone-400 tabular-nums">s.d. {{ Format::rupiah($b['kumulatif']) }}</span>
                    </span>
                </li>
            @endforeach
            <li class="flex items-baseline justify-between gap-3 bg-stone-50 px-3 py-2">
                <span class="text-xs font-medium {{ $nada }}">{{ $mekanisme->teksStatus() }}</span>
                <span class="font-bold text-stone-900 tabular-nums">{{ Format::rupiah($mekanisme->jumlah()) }}</span>
            </li>
        </ol>
    @endif
</div>
