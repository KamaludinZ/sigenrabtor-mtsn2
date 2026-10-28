@use('App\Enums\StatusKegiatan')
@use('App\Support\Format')

@php
    $ringkasan = $this->ringkasan;
    $perSumber = $ringkasan->perSumber();
    $perStatus = $ringkasan->perStatus();
    // Admin: semua leading (termasuk yang belum punya kegiatan); akun leading: leading-nya saja.
    $perLeading = \App\Support\Monitoring\RingkasanDashboard::perLeading($ringkasan, $this->cakupan);
    $maksLeading = max([1, ...array_column($perLeading, 'jumlah')]);
    $pengguna = \App\Support\Stub\StubData::currentUser();
@endphp

<div class="mx-auto max-w-7xl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Dashboard</h1>
            <p class="mt-1 text-sm text-stone-500" data-testid="cakupan-dashboard">Tahun Anggaran {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }} · {{ $this->namaCakupan() }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('kegiatan.index') }}" wire:navigate class="btn-secondary"><x-ui.icon name="clipboard" class="size-4" /> Data Kegiatan</a>
            @if ($pengguna['role'] === 'admin')
                <a href="{{ route('monitoring.index') }}" wire:navigate class="btn-secondary"><x-ui.icon name="chart" class="size-4" /> Monitoring Anggaran</a>
            @endif
        </div>
    </div>

    <x-monitoring.ringkasan :ringkasan="$ringkasan" class="mt-6" />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        {{-- Pemakaian pagu per sumber dana --}}
        <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 sm:p-6" aria-labelledby="judul-pemakaian-pagu" data-testid="pemakaian-pagu">
            <h2 id="judul-pemakaian-pagu" class="text-base font-semibold text-stone-900">Pemakaian pagu DIPA &amp; Komite</h2>
            <p class="mt-0.5 text-sm text-stone-500">Total RAB dibandingkan dengan pagu tiap sumber dana dari Cascade Perkin.</p>
            <div class="mt-5 space-y-5">
                @foreach ($perSumber as $sumber => $s)
                    <x-monitoring.meter-pagu :sumber="$sumber" :pagu="$s['pagu']" :total="$s['total']" :tanpa-pagu="$s['tanpa_pagu']" />
                @endforeach
            </div>
        </section>

        {{-- Status dokumen --}}
        <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 sm:p-6" aria-labelledby="judul-status-dokumen" data-testid="status-dokumen">
            <h2 id="judul-status-dokumen" class="text-base font-semibold text-stone-900">Status dokumen</h2>
            <ul class="mt-3 divide-y divide-stone-100">
                @foreach (StatusKegiatan::cases() as $status)
                    <li>
                        <a href="{{ route('kegiatan.index', ['status' => $status->value]) }}" wire:navigate data-status-dokumen="{{ $status->value }}"
                           class="flex items-center justify-between gap-3 py-2.5 hover:text-brand-700">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-ui.status-badge :status="$status->value" />
                                <span class="truncate text-xs text-stone-500">{{ \Illuminate\Support\Str::before($status->deskripsi(), '.') }}</span>
                            </span>
                            <span @class(['shrink-0 text-sm font-semibold tabular-nums', 'text-stone-900' => $perStatus[$status->value] > 0, 'text-stone-300' => $perStatus[$status->value] === 0])>{{ $perStatus[$status->value] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- Kegiatan per leading --}}
    <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-per-leading" data-testid="kegiatan-per-leading">
        <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
            <h2 id="judul-per-leading" class="text-base font-semibold text-stone-900">Kegiatan per leading</h2>
            <p class="mt-0.5 text-sm text-stone-500">Jumlah kegiatan, status dokumennya, serta pagu dan total RAB tiap leading.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <caption class="sr-only">Kegiatan per leading, Tahun Anggaran {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }}</caption>
                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                    <tr>
                        <th scope="col" class="min-w-52 px-5 py-2 text-left sm:px-6">Leading</th>
                        <th scope="col" class="w-44 px-3 py-2 text-left">Kegiatan</th>
                        @foreach (StatusKegiatan::cases() as $status)
                            <th scope="col" class="w-16 px-2 py-2 text-right">{{ $status->label() }}</th>
                        @endforeach
                        <th scope="col" class="px-3 py-2 text-right">Pagu</th>
                        <th scope="col" class="px-5 py-2 text-right sm:px-6">Total RAB</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($perLeading as $baris)
                        <tr data-leading="{{ $baris['kode'] ?? 'tanpa' }}" wire:key="leading-{{ $baris['kode'] ?? 'tanpa' }}">
                            <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">
                                @if ($baris['kode'])
                                    <a href="{{ route('kegiatan.index', ['leading' => $baris['kode']]) }}" wire:navigate class="hover:text-brand-700">
                                        <span class="font-mono text-xs text-stone-400">{{ $baris['kode'] }}</span>
                                        <span class="font-medium text-stone-800">{{ $baris['nama_unit'] }}</span>
                                    </a>
                                @else
                                    <span class="text-stone-500 italic">{{ $baris['nama_unit'] }}</span>
                                @endif
                            </th>
                            <td class="px-3 py-2.5">
                                {{-- Batang jumlah satu hue (magnitudo); angkanya tetap tertulis di sampingnya. --}}
                                <span class="flex items-center gap-2">
                                    <span class="h-2 flex-1 rounded-full bg-brand-50" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-brand-600" style="width: {{ round($baris['jumlah'] / $maksLeading * 100) }}%"></span>
                                    </span>
                                    <span class="w-6 text-right font-semibold text-stone-800 tabular-nums">{{ $baris['jumlah'] }}</span>
                                </span>
                            </td>
                            @foreach (StatusKegiatan::cases() as $status)
                                <td @class(['px-2 py-2.5 text-right tabular-nums', 'text-stone-700' => $baris['perStatus'][$status->value] > 0, 'text-stone-300' => $baris['perStatus'][$status->value] === 0])>{{ $baris['perStatus'][$status->value] }}</td>
                            @endforeach
                            <td class="px-3 py-2.5 text-right text-stone-700 tabular-nums">{{ $baris['pagu'] > 0 ? Format::rupiah($baris['pagu']) : '—' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums sm:px-6">
                                <span @class(['text-stone-800', 'font-semibold text-red-700' => $baris['melebihi'] > 0])>{{ $baris['total'] > 0 ? Format::rupiah($baris['total']) : '—' }}</span>
                                @if ($baris['melebihi'] > 0)
                                    <span class="block text-[11px] font-medium text-red-700"><x-ui.icon name="alert" class="inline size-3" /> {{ $baris['melebihi'] }} melebihi pagu</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
