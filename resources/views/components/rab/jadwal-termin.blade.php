@props([
    'jadwal',
    'tahun',
    'aktif' => null,
])

@use('App\Support\Format')
@use('App\Support\Kegiatan\JadwalPelaksanaan')

@php
    /** @var array<string, \App\Support\Rab\MekanismePembayaran> $jadwal Sumber dana → mekanisme pembayarannya. */
    $perBulan = array_map(fn ($m) => $m->perBulan(), $jadwal);
    $totalBulan = [];
    foreach ($perBulan as $bulanSumber) {
        foreach ($bulanSumber as $bulan => $nominal) {
            $totalBulan[$bulan] = ($totalBulan[$bulan] ?? 0) + $nominal;
        }
    }
    $adaTanpaTanggal = isset($totalBulan[0]);
    $adaTermin = collect($jadwal)->contains(fn ($m) => $m->bertermin());
    $kolom = range(1, 12);
@endphp

{{--
    Grid jadwal pembayaran satu kegiatan: baris = sumber dana, kolom = bulan Januari–Desember tahun anggaran, isi = jumlah
    termin yang jatuh di bulan itu. RAB tanpa termin ditandai "sekaligus".
--}}
<section {{ $attributes->class(['rounded-2xl bg-white shadow-xs ring-1 ring-stone-200']) }} data-testid="jadwal-termin">
    <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
        <div>
            <h3 class="text-base font-semibold text-stone-900">Jadwal pembayaran {{ $tahun }}</h3>
            <p class="text-sm text-stone-500">Kapan setiap RAB dibayarkan, per bulan dan per sumber dana.</p>
        </div>
    </header>

    @if (! $adaTermin)
        <p class="px-6 py-6 text-center text-sm text-stone-500">Semua RAB kegiatan ini dibayar sekaligus — belum ada termin.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[56rem] text-sm">
                <caption class="sr-only">Jumlah pembayaran termin per bulan dan sumber dana</caption>
                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                    <tr>
                        <th scope="col" class="sticky left-0 bg-stone-50 px-4 py-2 text-left">Sumber</th>
                        @foreach ($kolom as $bulan)
                            <th scope="col" class="px-2 py-2 text-right" title="{{ JadwalPelaksanaan::NAMA_BULAN[$bulan] }} {{ $tahun }}">{{ mb_substr(JadwalPelaksanaan::NAMA_BULAN[$bulan], 0, 3) }}</th>
                        @endforeach
                        @if ($adaTanpaTanggal)
                            <th scope="col" class="px-2 py-2 text-right">Tanpa tanggal</th>
                        @endif
                        <th scope="col" class="px-4 py-2 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($jadwal as $sumber => $mekanisme)
                        <tr @class(['bg-brand-50/40' => $sumber === $aktif]) data-jadwal-sumber="{{ $sumber }}">
                            <th scope="row" @class(['sticky left-0 px-4 py-2.5 text-left font-normal', 'bg-white' => $sumber !== $aktif, 'bg-brand-50' => $sumber === $aktif])>
                                <x-rab.lencana-sumber :sumber="$sumber" awalan="RAB" class="text-[11px]" />
                            </th>
                            @if ($mekanisme->bertermin())
                                @foreach ($kolom as $bulan)
                                    @php $nominal = $perBulan[$sumber][$bulan] ?? 0; @endphp
                                    <td @class(['px-2 py-2.5 text-right tabular-nums whitespace-nowrap', 'font-semibold text-stone-900' => $nominal, 'text-stone-300' => ! $nominal])
                                        @if ($nominal) data-bulan="{{ $bulan }}" @endif>
                                        {{ $nominal ? Format::angka($nominal) : '·' }}
                                    </td>
                                @endforeach
                                @if ($adaTanpaTanggal)
                                    <td class="px-2 py-2.5 text-right text-gold-700 tabular-nums">{{ isset($perBulan[$sumber][0]) ? Format::angka($perBulan[$sumber][0]) : '·' }}</td>
                                @endif
                            @else
                                <td colspan="{{ 12 + ($adaTanpaTanggal ? 1 : 0) }}" class="px-2 py-2.5 text-center text-xs text-stone-400">Dibayar sekaligus {{ Format::rupiah($mekanisme->total) }}</td>
                            @endif
                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums whitespace-nowrap">{{ Format::angka($mekanisme->bertermin() ? $mekanisme->jumlah() : $mekanisme->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if (count($jadwal) > 1)
                    <tfoot class="border-t-2 border-stone-200 bg-stone-50 text-xs font-semibold text-stone-600">
                        <tr>
                            <th scope="row" class="sticky left-0 bg-stone-50 px-4 py-2 text-left">Semua sumber</th>
                            @foreach ($kolom as $bulan)
                                <td class="px-2 py-2 text-right tabular-nums">{{ isset($totalBulan[$bulan]) ? Format::angka($totalBulan[$bulan]) : '' }}</td>
                            @endforeach
                            @if ($adaTanpaTanggal)
                                <td class="px-2 py-2 text-right tabular-nums">{{ Format::angka($totalBulan[0]) }}</td>
                            @endif
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::angka(collect($jadwal)->sum(fn ($m) => $m->bertermin() ? $m->jumlah() : $m->total)) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="px-5 py-2.5 text-xs text-stone-500 sm:px-6">Angka dalam rupiah. Geser tabel ke samping di layar sempit.</p>
    @endif
</section>
