@use('App\Support\Format')

@php
    $jumlahSiap = $this->jumlahSiap();
@endphp

{{--
    Tabel pratinjau impor Perkin (pengganti tabel bawaan view bertahap, di dalam x-data `saring`): kode SK & IKSK beserta
    asalnya (sel gabung di atas / induk IKSK) dan kode yang dikosongkan karena tidak ada di master, leading, pagu per
    sumber dana dengan baris jumlah baris siap, bulan, status + alasannya; cari nama di klien. Kartu di layar sempit.
--}}
<div x-data="{ cari: '' }" class="mt-3" data-testid="tabel-perkin">
    <label class="relative block sm:max-w-xs">
        <span class="sr-only">Cari nama kegiatan</span>
        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
        <input type="search" x-model="cari" placeholder="Cari nama kegiatan…" class="form-input py-1.5 pl-9" autocomplete="off" data-testid="cari-perkin">
    </label>

    {{-- Layar lebar: tabel --}}
    <div class="mt-3 hidden max-h-[32rem] overflow-auto rounded-xl ring-1 ring-stone-200 md:block" data-testid="pratinjau-impor">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 z-10 bg-stone-50 text-xs font-semibold text-stone-500">
                <tr>
                    <th scope="col" class="px-3 py-2 text-right">Baris</th>
                    <th scope="col" class="px-3 py-2">Kegiatan &amp; leading</th>
                    <th scope="col" class="px-3 py-2">SK</th>
                    <th scope="col" class="px-3 py-2">IKSK</th>
                    <th scope="col" class="px-3 py-2 text-right">Pagu DIPA</th>
                    <th scope="col" class="px-3 py-2 text-right">Pagu Komite</th>
                    <th scope="col" class="px-3 py-2">Bulan</th>
                    <th scope="col" class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($baris as $b)
                    @php $p = $b['perkin']; @endphp
                    <tr data-status="{{ $b['status'] }}" data-baris-perkin="{{ $b['baris'] }}" data-cari="{{ mb_strtolower($b['tampil']['nama']) }}"
                        x-show="(saring === 'semua' || saring === '{{ $b['status'] }}') && $el.dataset.cari.includes(cari.trim().toLowerCase())" class="align-top">
                        <td class="px-3 py-2 text-right text-stone-400 tabular-nums">{{ $b['baris'] }}</td>
                        <td class="min-w-[14rem] px-3 py-2">
                            <span class="font-medium text-stone-800">{{ $b['tampil']['nama'] }}</span>
                            <span class="block text-xs text-stone-500">{{ $p['leading'] ? $p['leading'].' · '.($p['leading_nama'] ?? '') : 'Leading belum dipilih' }}</span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap" data-kolom="sk">@include('livewire.kegiatan.partials.kode-perkin', ['jenis' => 'sk'])</td>
                        <td class="px-3 py-2 whitespace-nowrap" data-kolom="iksk">@include('livewire.kegiatan.partials.kode-perkin', ['jenis' => 'iksk'])</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap text-stone-700 tabular-nums">{{ isset($p['pagu']['DIPA']) ? Format::rupiah($p['pagu']['DIPA']) : '—' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap text-stone-700 tabular-nums">{{ isset($p['pagu']['Komite']) ? Format::rupiah($p['pagu']['Komite']) : '—' }}</td>
                        <td class="min-w-[8.5rem] px-3 py-2 text-xs text-stone-600">{{ $p['bulan'] ?? '—' }}</td>
                        <td class="min-w-[13rem] px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $lencana[$b['status']][1] }}">{{ $lencana[$b['status']][0] }}</span>
                            @foreach ($b['pesan'] as $pesan)
                                <p @class(['mt-1 text-xs', 'text-red-700' => $b['status'] === 'gagal', 'text-stone-500' => $b['status'] !== 'gagal'])>{{ $pesan }}</p>
                            @endforeach
                            @foreach ($b['peringatan'] as $pesan)
                                <p class="mt-1 text-xs text-gold-700">{{ $pesan }}</p>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="sticky bottom-0 bg-stone-50 text-xs font-semibold text-stone-700">
                <tr data-testid="jumlah-perkin">
                    <td></td>
                    <th scope="row" colspan="3" class="px-3 py-2 text-left">Jumlah baris siap ({{ $jumlahSiap['kegiatan'] }} kegiatan)</th>
                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ Format::rupiah($jumlahSiap['pagu']['DIPA'] ?? 0) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ Format::rupiah($jumlahSiap['pagu']['Komite'] ?? 0) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Layar sempit: kartu --}}
    <ul class="mt-3 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200 md:hidden">
        @foreach ($baris as $b)
            @php $p = $b['perkin']; @endphp
            <li class="px-3 py-2.5 text-sm" data-status="{{ $b['status'] }}" data-cari="{{ mb_strtolower($b['tampil']['nama']) }}"
                x-show="(saring === 'semua' || saring === '{{ $b['status'] }}') && $el.dataset.cari.includes(cari.trim().toLowerCase())">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 font-medium text-stone-800"><span class="mr-1 text-xs font-normal text-stone-400 tabular-nums">{{ $b['baris'] }}</span>{{ $b['tampil']['nama'] }}</p>
                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $lencana[$b['status']][1] }}">{{ $lencana[$b['status']][0] }}</span>
                </div>
                <dl class="mt-1 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-0.5 text-xs text-stone-600">
                    <dt class="text-stone-400">SK · IKSK</dt>
                    <dd class="flex flex-wrap gap-x-2">
                        <span>@include('livewire.kegiatan.partials.kode-perkin', ['jenis' => 'sk'])</span>
                        <span>@include('livewire.kegiatan.partials.kode-perkin', ['jenis' => 'iksk'])</span>
                    </dd>
                    <dt class="text-stone-400">Leading</dt>
                    <dd>{{ $p['leading'] ? $p['leading'].' · '.($p['leading_nama'] ?? '') : '—' }}</dd>
                    <dt class="text-stone-400">Pagu</dt>
                    <dd class="tabular-nums">{{ $b['tampil']['pagu'] }}</dd>
                    <dt class="text-stone-400">Bulan</dt>
                    <dd>{{ $p['bulan'] ?? '—' }}</dd>
                </dl>
                @foreach ($b['pesan'] as $pesan)
                    <p @class(['mt-1 text-xs', 'text-red-700' => $b['status'] === 'gagal', 'text-stone-500' => $b['status'] !== 'gagal'])>{{ $pesan }}</p>
                @endforeach
                @foreach ($b['peringatan'] as $pesan)
                    <p class="mt-1 text-xs text-gold-700">{{ $pesan }}</p>
                @endforeach
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-xs text-stone-500 md:hidden" data-testid="jumlah-perkin-ringkas">
        {{ $jumlahSiap['kegiatan'] }} kegiatan siap · DIPA {{ Format::rupiah($jumlahSiap['pagu']['DIPA'] ?? 0) }} · Komite {{ Format::rupiah($jumlahSiap['pagu']['Komite'] ?? 0) }}
    </p>
</div>
