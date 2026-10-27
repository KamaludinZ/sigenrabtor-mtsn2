@props(['operator'])

@use('App\Support\Format')
@use('App\Support\MasterKode')

{{--
    Rekap per operator (Monitoring Anggaran, PRD §11): jumlah kegiatan & anggaran per pembuat kegiatan dari
    RingkasanAnggaran::perOperator() — urut total RAB terbesar; batang satu hue = total RAB operator terhadap operator
    terbesar (magnitudo, angkanya tetap tertulis). Status kegiatan tampil sebagai lencana + jumlah, RAB melebihi pagu merah
    + ikon. Pembuat yang tidak tercatat dikumpulkan di baris terakhir.
--}}
@php
    $maks = max([1, ...array_column($operator, 'total')]);
    $namaLeading = array_column(MasterKode::semuaLeading(), 'nama_unit', 'kode');
    $jumlahKegiatan = array_sum(array_column($operator, 'jumlah'));
    $jumlahTotal = array_sum(array_column($operator, 'total'));
    $peran = ['admin' => 'Admin', 'leading' => 'Leading'];
@endphp

<section {{ $attributes->class(['min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200']) }} id="monitoring-operator" aria-labelledby="judul-monitoring-operator" data-testid="bagian-monitoring-operator">
    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
        <h2 id="judul-monitoring-operator" class="text-base font-semibold text-stone-900">Rekap per operator</h2>
        <p class="mt-0.5 text-sm text-stone-500">Jumlah kegiatan dan anggaran per pembuat kegiatan.</p>
    </header>

    @if ($operator === [])
        <p class="px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="operator-kosong">Belum ada kegiatan pada saringan ini.</p>
    @else
        <ul class="divide-y divide-stone-100">
            @foreach ($operator as $o)
                <li class="px-5 py-3.5 sm:px-6" data-operator="{{ $o['user_id'] ?? 'tanpa' }}" wire:key="operator-{{ $o['user_id'] ?? 'tanpa' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span @class(['font-medium text-stone-900' => $o['user_id'] !== null, 'text-stone-500 italic' => $o['user_id'] === null])>{{ $o['nama'] }}</span>
                                @if ($o['peran'])
                                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-600 uppercase">{{ $peran[$o['peran']] ?? $o['peran'] }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-stone-500">
                                {{-- Jabatan disembunyikan bila sama dengan nama leading kegiatannya (akun leading). --}}
                                @if ($o['jabatan'] && ! in_array($o['jabatan'], array_map(fn ($k) => $namaLeading[$k] ?? '', $o['leading']), true))
                                    {{ $o['jabatan'] }} ·
                                @endif
                                @if ($o['leading'] === [])
                                    Leading belum dipilih
                                @else
                                    untuk {{ implode(', ', array_map(fn ($k) => $k.' '.($namaLeading[$k] ?? ''), $o['leading'])) }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($o['total']) }}</p>
                            <p class="text-xs text-stone-500 tabular-nums">pagu {{ $o['pagu'] > 0 ? Format::rupiah($o['pagu']) : '—' }}</p>
                        </div>
                    </div>
                    <span class="mt-2 block h-1.5 rounded-full bg-brand-50" aria-hidden="true">
                        <span class="block h-full rounded-full bg-brand-600" style="width: {{ round($o['total'] / $maks * 100, 1) }}%"></span>
                    </span>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-600">
                        <span class="font-semibold text-stone-800 tabular-nums">{{ $o['jumlah'] }} kegiatan</span>
                        <ul class="flex flex-wrap gap-1.5" aria-label="Kegiatan {{ $o['nama'] }} per status">
                            @foreach ($o['perStatus'] as $status => $jumlah)
                                @if ($jumlah > 0)
                                    <li class="inline-flex items-center gap-1" data-status-operator="{{ $status }}"><x-ui.status-badge :status="$status" class="text-[10px]" /> <span class="tabular-nums">{{ $jumlah }}</span></li>
                                @endif
                            @endforeach
                        </ul>
                        <span class="tabular-nums">{{ $o['rab'] }} RAB</span>
                        @if ($o['melebihi'] > 0)
                            <span class="inline-flex items-center gap-1 font-medium text-red-700"><x-ui.icon name="alert" class="size-3.5" /> {{ $o['melebihi'] }} melebihi pagu</span>
                        @endif
                        @if ($o['tanpa_pagu'] > 0)
                            <span class="font-medium text-gold-700">{{ $o['tanpa_pagu'] }} belum berpagu</span>
                        @endif
                    </div>
                    @if ($o['terakhir'])
                        <p class="mt-1 text-[11px] text-stone-400">Terakhir mengubah {{ Format::tanggal($o['terakhir']) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
        <footer class="flex flex-wrap items-baseline justify-between gap-2 border-t-2 border-stone-200 bg-stone-50 px-5 py-3 text-sm sm:px-6" data-testid="jumlah-operator">
            <span class="font-semibold text-stone-800">Jumlah {{ count($operator) }} operator · {{ $jumlahKegiatan }} kegiatan</span>
            <span class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($jumlahTotal) }}</span>
        </footer>
    @endif
</section>
