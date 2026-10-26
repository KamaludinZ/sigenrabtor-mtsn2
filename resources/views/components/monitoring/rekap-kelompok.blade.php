@props(['ringkasan'])

@use('App\Support\Format')
@use('App\Support\Rab\RekapKelompok')

{{--
    Rekap per kelompok belanja (Monitoring Anggaran, PRD §11 "Rekap per Kelompok"): belanja bahan, jasa, dan lain-lain seluruh
    kegiatan tersaring dari RingkasanAnggaran::perKelompok() — legenda berlabel nilai & persen, batang komposisi per sumber
    dana (+ gabungan), tabel per sumber dana, dan total per kode akun. Warna kelompok sama dengan rekap RAB per kegiatan.
--}}
@php
    $rekap = $ringkasan->perKelompok();
    $duaSumber = count($rekap['sumber']) > 1;
    $warna = ['bahan' => 'bg-kelompok-bahan', 'jasa' => 'bg-kelompok-jasa', 'lain' => 'bg-kelompok-lain'];
@endphp

<section {{ $attributes->class(['min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200']) }} id="monitoring-kelompok" aria-labelledby="judul-monitoring-kelompok" data-testid="bagian-monitoring-kelompok">
    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
        <h2 id="judul-monitoring-kelompok" class="text-base font-semibold text-stone-900">Rekap per kelompok belanja</h2>
        <p class="mt-0.5 text-sm text-stone-500">Belanja bahan, jasa, dan lain-lain seluruh kegiatan tersaring.</p>
        <p class="mt-2 flex flex-wrap items-baseline gap-x-2">
            <x-ui.uang :nilai="$rekap['total']" class="text-lg font-bold text-stone-900" data-testid="total-kelompok" />
            <span class="text-xs text-stone-500">dari {{ $rekap['jumlahItem'] }} item belanja</span>
        </p>
    </header>

    @if ($rekap['total'] === 0)
        <p class="px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="kelompok-kosong">Belum ada rincian belanja pada kegiatan tersaring.</p>
    @else
        <div class="px-5 py-4 sm:px-6">
            {{-- Legenda (selalu ada) sekaligus label langsung: nilai & persen gabungan --}}
            <ul class="flex flex-wrap gap-x-5 gap-y-1.5" aria-label="Legenda kelompok belanja">
                @foreach ($rekap['kelompok'] as $k)
                    <li class="flex items-center gap-1.5 text-sm" data-kelompok-legenda="{{ $k['kunci'] }}">
                        <span class="size-3 rounded-sm {{ $warna[$k['kunci']] }}" aria-hidden="true"></span>
                        <span class="text-stone-700">{{ $k['label'] }}</span>
                        <span class="font-semibold text-stone-900">{{ Format::angka($k['persen']) }}%</span>
                    </li>
                @endforeach
            </ul>
            <x-rab.batang-kelompok :kelompok="$rekap['kelompok']" :baris="RekapKelompok::barisBatang($rekap)" class="mt-3" />
        </div>

        <div class="overflow-x-auto border-t border-stone-100">
            <table class="w-full text-sm" data-testid="tabel-monitoring-kelompok">
                <caption class="sr-only">Total belanja per kelompok{{ $duaSumber ? ' dan sumber dana' : '' }}</caption>
                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                    <tr>
                        <th scope="col" class="px-5 py-2 text-left sm:px-6">Kelompok</th>
                        @foreach ($rekap['sumber'] as $s)
                            <th scope="col" @class(['px-3 py-2 text-right', 'hidden sm:table-cell' => $duaSumber])><x-rab.lencana-sumber :sumber="$s" class="text-[10px]" /></th>
                        @endforeach
                        @if ($duaSumber)
                            <th scope="col" class="px-3 py-2 text-right">Total</th>
                        @endif
                        <th scope="col" class="px-5 py-2 text-right sm:px-6">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($rekap['kelompok'] as $k)
                        <tr class="align-top" data-kelompok="{{ $k['kunci'] }}">
                            <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">
                                <span class="flex items-center gap-2 font-medium whitespace-nowrap text-stone-800"><span class="size-2.5 shrink-0 rounded-sm {{ $warna[$k['kunci']] }}" aria-hidden="true"></span>{{ $k['label'] }}</span>
                                <span class="block pl-4.5 text-xs whitespace-nowrap text-stone-500">{{ $k['jumlahItem'] }} item · {{ $k['kegiatan'] }} kegiatan</span>
                            </th>
                            @foreach ($rekap['sumber'] as $s)
                                <td @class(['px-3 py-2.5 text-right whitespace-nowrap text-stone-700 tabular-nums', 'hidden sm:table-cell' => $duaSumber])>{{ Format::rupiah($k['perSumber'][$s]) }}</td>
                            @endforeach
                            @if ($duaSumber)
                                <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
                                    <span class="font-semibold text-stone-900">{{ Format::rupiah($k['total']) }}</span>
                                    {{-- Layar sempit: rincian per sumber dana di bawah total (kolomnya disembunyikan) --}}
                                    @foreach ($rekap['sumber'] as $s)
                                        <span class="block text-xs text-stone-500 sm:hidden">{{ $s }} {{ Format::rupiah($k['perSumber'][$s]) }}</span>
                                    @endforeach
                                </td>
                            @endif
                            <td class="px-5 py-2.5 text-right text-stone-600 tabular-nums sm:px-6">{{ Format::angka($k['persen']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-stone-200 bg-stone-50 font-semibold">
                    <tr>
                        <th scope="row" class="px-5 py-2.5 text-left text-stone-800 sm:px-6">Jumlah</th>
                        @foreach ($rekap['sumber'] as $s)
                            <td @class(['px-3 py-2.5 text-right whitespace-nowrap tabular-nums', 'hidden sm:table-cell' => $duaSumber])>{{ Format::rupiah($rekap['totalPerSumber'][$s]) }}</td>
                        @endforeach
                        @if ($duaSumber)
                            <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">{{ Format::rupiah($rekap['total']) }}</td>
                        @endif
                        <td class="px-5 py-2.5 text-right tabular-nums sm:px-6">100</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Per kode akun --}}
        <div class="border-t border-stone-100 px-5 py-4 sm:px-6" data-testid="kode-akun-monitoring">
            <h3 class="text-xs font-semibold text-stone-500">Per kode akun</h3>
            <dl class="mt-2 divide-y divide-stone-100 text-sm">
                @foreach ($rekap['kode'] as $baris)
                    <div class="flex items-baseline justify-between gap-3 py-1.5" data-kode-akun="{{ $baris['kode'] }}">
                        <dt class="min-w-0 text-stone-700">
                            @if ($baris['kode'] !== '')
                                <span class="font-mono text-xs text-stone-400">{{ $baris['kode'] }}</span>
                            @endif
                            {{ $baris['label'] }}
                        </dt>
                        <dd class="shrink-0 font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($baris['total']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</section>
