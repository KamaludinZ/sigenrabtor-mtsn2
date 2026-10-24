@use('App\Support\Format')

@php
    $rekap = $this->rekap;
    $duaSumber = count($rekap['sumber']) > 1;
    // Baris batang komposisi: satu per sumber dana, plus gabungan bila dibiayai dua sumber.
    $barisBatang = [];
    foreach ($rekap['sumber'] as $namaSumber) {
        $barisBatang[] = ['label' => $namaSumber, 'total' => $rekap['totalPerSumber'][$namaSumber], 'nilai' => array_column(array_map(fn ($k) => ['k' => $k['kunci'], 'v' => $k['perSumber'][$namaSumber]], $rekap['kelompok']), 'v', 'k')];
    }
    if ($duaSumber) {
        $barisBatang[] = ['label' => 'Gabungan', 'total' => $rekap['total'], 'nilai' => array_column($rekap['kelompok'], 'total', 'kunci')];
    }
    $warna = ['bahan' => 'bg-kelompok-bahan', 'jasa' => 'bg-kelompok-jasa', 'lain' => 'bg-kelompok-lain'];
@endphp

<div class="mt-6 space-y-6" data-testid="rekap-kelompok">
    {{-- Angka utama --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:col-span-2 lg:col-span-1">
            <p class="text-xs text-stone-500">Total anggaran kegiatan</p>
            <x-ui.uang :nilai="$rekap['total']" class="mt-1 block text-3xl font-bold text-stone-900" data-testid="rekap-total" />
            <p class="mt-1 text-xs text-stone-500">{{ $rekap['jumlahItem'] }} item · {{ count($rekap['sumber']) }} sumber dana</p>
        </div>
        @foreach ($rekap['hasil'] as $namaSumber => $hasilSumber)
            <x-rab.total-anggaran :nilai="$hasilSumber->total" :sumber="$namaSumber" :pagu="$hasilSumber->pagu" ringkas
                                  class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" />
        @endforeach
    </div>

    {{-- Komposisi per kelompok belanja --}}
    <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 sm:p-6" aria-labelledby="judul-komposisi">
        <h2 id="judul-komposisi" class="text-base font-semibold text-stone-900">Komposisi per kelompok belanja</h2>
        <p class="text-sm text-stone-500">Bagian tiap kelompok terhadap total RAB{{ $duaSumber ? ' per sumber dana' : '' }}.</p>

        {{-- Legenda (selalu ada) sekaligus label langsung: nilai & persentase gabungan --}}
        <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2" aria-label="Legenda kelompok belanja">
            @foreach ($rekap['kelompok'] as $k)
                <li class="flex items-center gap-2 text-sm">
                    <span class="size-3 rounded-sm {{ $warna[$k['kunci']] }}" aria-hidden="true"></span>
                    <span class="text-stone-700">{{ $k['label'] }}</span>
                    <x-ui.uang :nilai="$k['total']" class="font-semibold text-stone-900" />
                    <span class="text-stone-500">({{ Format::angka($k['persen']) }}%)</span>
                </li>
            @endforeach
        </ul>

        <div class="relative mt-4 space-y-3" x-data="{ tip: null }" x-on:mouseleave="tip = null">
            @foreach ($barisBatang as $baris)
                <div class="grid grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[5.5rem_minmax(0,1fr)_7.5rem]">
                    <span class="text-sm font-medium text-stone-600">{{ $baris['label'] }}</span>
                    @if ($baris['total'] > 0)
                        <div class="flex h-6 gap-0.5" role="img" data-testid="batang-{{ \Illuminate\Support\Str::slug($baris['label']) }}"
                             aria-label="{{ $baris['label'] }}: {{ collect($rekap['kelompok'])->map(fn ($k) => $k['label'].' '.Format::rupiah($baris['nilai'][$k['kunci']]))->implode(', ') }}">
                            @foreach ($rekap['kelompok'] as $k)
                                @php $nilai = $baris['nilai'][$k['kunci']]; @endphp
                                @if ($nilai > 0)
                                    <div tabindex="0" style="width: {{ round($nilai / $baris['total'] * 100, 2) }}%"
                                         class="h-full min-w-1 cursor-default {{ $warna[$k['kunci']] }} last:rounded-r focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                                         aria-label="{{ $k['label'] }} {{ Format::rupiah($nilai) }}"
                                         x-on:mouseenter="tip = { kunci: @js($k['kunci']), label: @js($baris['label'].' · '.$k['label']), nilai: @js(Format::rupiah($nilai)), persen: @js(Format::angka(round($nilai / $baris['total'] * 100, 1)).'%'), x: $el.offsetLeft + $el.offsetWidth / 2, y: $el.offsetTop }"
                                         x-on:focus="tip = { kunci: @js($k['kunci']), label: @js($baris['label'].' · '.$k['label']), nilai: @js(Format::rupiah($nilai)), persen: @js(Format::angka(round($nilai / $baris['total'] * 100, 1)).'%'), x: $el.offsetLeft + $el.offsetWidth / 2, y: $el.offsetTop }"
                                         x-on:blur="tip = null"></div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="flex h-6 items-center rounded border border-dashed border-stone-300 px-2 text-xs text-stone-400">Belum ada item</div>
                    @endif
                    <x-ui.uang :nilai="$baris['total']" class="col-start-2 text-right text-sm font-semibold text-stone-900 sm:col-start-auto" />
                </div>
            @endforeach

            {{-- Tooltip: nilai di depan, label menyusul; kunci warna berupa garis pendek --}}
            <div x-cloak x-show="tip" x-transition.opacity.duration.100ms role="tooltip"
                 class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-stone-900 px-3 py-2 text-xs text-white shadow-lg"
                 :style="tip && `left: ${tip.x}px; top: ${tip.y - 8}px`">
                <p class="flex items-center gap-2">
                    <span class="h-0.5 w-3 rounded" :style="tip && `background: var(--color-kelompok-${tip.kunci})`"></span>
                    <strong class="text-sm" x-text="tip?.nilai"></strong>
                    <span class="text-stone-300" x-text="tip?.persen"></span>
                </p>
                <p class="mt-0.5 text-stone-300" x-text="tip?.label"></p>
            </div>
        </div>
    </section>

    {{-- Tabel rekap per kelompok (versi tabel dari batang di atas) --}}
    <section class="overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-stone-200">
        <h2 class="border-b border-stone-100 px-5 py-4 text-base font-semibold text-stone-900 sm:px-6">Rekap per kelompok belanja</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" data-testid="tabel-rekap-kelompok">
                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 text-left sm:px-6">Kelompok</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Item</th>
                        @foreach ($rekap['sumber'] as $namaSumber)
                            <th scope="col" class="px-3 py-2.5 text-right">{{ $namaSumber }} (Rp)</th>
                        @endforeach
                        @if ($duaSumber)<th scope="col" class="px-3 py-2.5 text-right">Total (Rp)</th>@endif
                        <th scope="col" class="px-5 py-2.5 text-right sm:px-6">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($rekap['kelompok'] as $k)
                        <tr>
                            <th scope="row" class="px-5 py-2.5 text-left font-medium text-stone-800 sm:px-6">
                                <span class="mr-2 inline-block size-2.5 rounded-sm {{ $warna[$k['kunci']] }}" aria-hidden="true"></span>{{ $k['label'] }}
                            </th>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $k['jumlahItem'] }}</td>
                            @foreach ($rekap['sumber'] as $namaSumber)
                                <td class="px-3 py-2.5 text-right"><x-ui.uang :nilai="$k['perSumber'][$namaSumber]" polos /></td>
                            @endforeach
                            @if ($duaSumber)<td class="px-3 py-2.5 text-right font-semibold"><x-ui.uang :nilai="$k['total']" polos /></td>@endif
                            <td class="px-5 py-2.5 text-right text-stone-600 tabular-nums sm:px-6">{{ Format::angka($k['persen']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-stone-200 bg-stone-50 font-semibold">
                    <tr>
                        <th scope="row" class="px-5 py-3 text-left text-xs tracking-wide text-stone-600 uppercase sm:px-6">Total</th>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $rekap['jumlahItem'] }}</td>
                        @foreach ($rekap['sumber'] as $namaSumber)
                            <td class="px-3 py-3 text-right"><x-ui.uang :nilai="$rekap['totalPerSumber'][$namaSumber]" polos /></td>
                        @endforeach
                        @if ($duaSumber)<td class="px-3 py-3 text-right"><x-ui.uang :nilai="$rekap['total']" polos /></td>@endif
                        <td class="px-5 py-3 text-right tabular-nums sm:px-6">{{ $rekap['total'] > 0 ? '100' : '0' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Kartu terpisah per kelompok --}}
    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($rekap['kelompok'] as $k)
            <section class="flex flex-col rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="kartu-kelompok-{{ $k['kunci'] }}">
                <header class="border-b border-stone-100 px-5 py-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-stone-800">
                        <span class="size-3 rounded-sm {{ $warna[$k['kunci']] }}" aria-hidden="true"></span>{{ $k['label'] }}
                        <span class="ml-auto text-xs font-normal text-stone-500">{{ $k['jumlahItem'] }} item</span>
                    </p>
                    <x-ui.uang :nilai="$k['total']" class="mt-1 block text-xl font-bold text-stone-900" />
                    <p class="text-xs text-stone-500">{{ Format::angka($k['persen']) }}% dari total anggaran</p>
                </header>
                @if ($k['item'] === [])
                    <p class="flex-1 px-5 py-6 text-center text-xs text-stone-400">Belum ada item {{ mb_strtolower($k['label']) }}.</p>
                @else
                    <ol class="flex-1 divide-y divide-stone-100">
                        @foreach (array_slice($k['item'], 0, 5) as $baris)
                            <li class="flex items-start justify-between gap-3 px-5 py-2.5 text-sm">
                                <span class="min-w-0 text-stone-700">
                                    {{ $baris['uraian'] }}
                                    @if ($duaSumber)<span class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-500">{{ $baris['sumber'] }}</span>@endif
                                </span>
                                <x-ui.uang :nilai="$baris['jumlah']" class="shrink-0 font-medium text-stone-900" />
                            </li>
                        @endforeach
                    </ol>
                    @if ($k['jumlahItem'] > 5)
                        <button type="button" wire:click="lihatKelompokDiRincian"
                                class="border-t border-stone-100 px-5 py-2.5 text-left text-xs font-semibold text-brand-700 hover:bg-stone-50">
                            +{{ $k['jumlahItem'] - 5 }} item lainnya — lihat di rincian
                        </button>
                    @endif
                @endif
            </section>
        @endforeach
    </div>

    {{-- Rekap per kode akun --}}
    @if ($rekap['kode'] !== [])
        <section class="overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-stone-200">
            <h2 class="border-b border-stone-100 px-5 py-4 text-base font-semibold text-stone-900 sm:px-6">Rekap per kode akun</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-testid="tabel-rekap-kode">
                    <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-left sm:px-6">Kode akun</th>
                            @foreach ($rekap['sumber'] as $namaSumber)
                                <th scope="col" class="px-3 py-2.5 text-right">{{ $namaSumber }} (Rp)</th>
                            @endforeach
                            @if ($duaSumber)<th scope="col" class="px-5 py-2.5 text-right sm:px-6">Total (Rp)</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($rekap['kode'] as $baris)
                            <tr>
                                <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">
                                    @if ($baris['kode'] !== '')<span class="font-mono text-xs text-stone-500">{{ $baris['kode'] }}</span> · @endif
                                    <span class="text-stone-800">{{ $baris['label'] }}</span>
                                </th>
                                @foreach ($rekap['sumber'] as $namaSumber)
                                    <td class="px-3 py-2.5 text-right"><x-ui.uang :nilai="$baris['perSumber'][$namaSumber]" polos /></td>
                                @endforeach
                                @if ($duaSumber)<td class="px-5 py-2.5 text-right font-semibold sm:px-6"><x-ui.uang :nilai="$baris['total']" polos /></td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
