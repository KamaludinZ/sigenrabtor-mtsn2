@props(['baris', 'pilihan' => [], 'tahunAktif'])

@use('App\Support\Format')

{{--
    Daftar kegiatan tahun sebelumnya untuk diduplikat (DuplikatKegiatan::sumber): kotak pilih terikat `pilihan` komponen
    induk; tabel di layar lebar, kartu di layar sempit. Kegiatan yang namanya sudah ada di tahun aktif tidak bisa dipilih;
    rincian (tujuan, tempat, peserta, lama) dibuka per baris; kode yang tidak ada di master tahun aktif diberi lencana.
--}}
<div {{ $attributes->class(['min-w-0']) }} data-testid="daftar-tahun-lalu">
    {{-- Layar lebar: tabel --}}
    <table class="hidden w-full text-left text-sm md:table">
        <thead class="border-b border-stone-200 bg-stone-50 text-xs font-semibold text-stone-500">
            <tr>
                <th scope="col" class="w-10 px-4 py-2.5"><span class="sr-only">Pilih</span></th>
                <th scope="col" class="px-3 py-2.5">Kegiatan</th>
                <th scope="col" class="px-3 py-2.5">Sumber dana &amp; RAB</th>
                <th scope="col" class="px-3 py-2.5">Bulan</th>
                <th scope="col" class="px-3 py-2.5">Status</th>
                <th scope="col" class="w-10 px-3 py-2.5"><span class="sr-only">Rincian</span></th>
            </tr>
        </thead>
        @foreach ($baris as $b)
            <tbody x-data="{ buka: false }" class="border-b border-stone-100" wire:key="baris-lalu-{{ $b['id'] }}" data-sumber-duplikat="{{ $b['id'] }}" data-sudah-ada="{{ $b['sudah_ada'] ? 'ya' : 'tidak' }}">
                <tr @class(['align-top', 'bg-brand-50/40' => in_array($b['id'], $pilihan, true), 'opacity-70' => $b['sudah_ada']])>
                    <td class="px-4 py-3">
                        <input type="checkbox" wire:model.live="pilihan" value="{{ $b['id'] }}" id="pilih-lalu-{{ $b['id'] }}" @disabled($b['sudah_ada'])
                               class="size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-600 disabled:opacity-40" aria-label="Pilih {{ $b['nama'] }}">
                    </td>
                    <td class="px-3 py-3">
                        <label for="pilih-lalu-{{ $b['id'] }}" class="block cursor-pointer font-medium text-stone-900">{{ $b['nama'] }}</label>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-stone-500">
                            <span><span class="font-mono">{{ $b['kode_rktm'] ?? 'Tanpa kode RKTM' }}</span> · {{ $b['leading'] ? $b['leading'].' '.($b['nama_leading'] ?? '') : 'Leading belum dipilih' }}</span>
                            <x-kode.lencana-hilang :hilang="$b['kode_hilang']" :tahun="$tahunAktif" />
                        </p>
                        @if ($b['sudah_ada'])
                            <p class="mt-1 text-xs text-gold-700">
                                Sudah ada di TA {{ $tahunAktif }} —
                                <a href="{{ route('kegiatan.edit', ['kegiatan' => $b['sudah_ada']]) }}" wire:navigate class="font-semibold underline">buka kegiatan</a>
                            </p>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-xs">
                        @forelse ($b['sumber'] as $s)
                            <p class="flex items-center gap-1.5 py-0.5">
                                <x-rab.lencana-sumber :sumber="$s['sumber']" class="text-[10px]" />
                                <span class="text-stone-700 tabular-nums">{{ Format::rupiah($s['total']) }}</span>
                                <span class="text-stone-400">· {{ $s['item'] }} item</span>
                            </p>
                        @empty
                            <span class="text-stone-400">Tanpa sumber dana</span>
                        @endforelse
                    </td>
                    <td class="px-3 py-3 text-xs text-stone-600">{{ $b['bulan'] ?? '—' }}</td>
                    <td class="px-3 py-3"><x-ui.status-badge :status="$b['status']" /></td>
                    <td class="px-3 py-3">
                        <button type="button" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" class="rounded-md p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-800" aria-label="Rincian {{ $b['nama'] }}">
                            <x-ui.icon name="chevron-down" class="size-4 transition" x-bind:class="{ 'rotate-180': buka }" />
                        </button>
                    </td>
                </tr>
                <tr x-cloak x-show="buka" class="bg-stone-50/60">
                    <td></td>
                    <td colspan="5" class="px-3 pb-3 text-xs">
                        @include('components.kegiatan.partials.rincian-tahun-lalu', ['b' => $b])
                    </td>
                </tr>
            </tbody>
        @endforeach
    </table>

    {{-- Layar sempit: kartu --}}
    <ul class="divide-y divide-stone-100 md:hidden">
        @foreach ($baris as $b)
            <li x-data="{ buka: false }" wire:key="kartu-lalu-{{ $b['id'] }}" @class(['px-5 py-3', 'bg-brand-50/40' => in_array($b['id'], $pilihan, true)])>
                <div class="flex items-start gap-3">
                    <input type="checkbox" wire:model.live="pilihan" value="{{ $b['id'] }}" @disabled($b['sudah_ada'])
                           class="mt-1 size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-600 disabled:opacity-40" aria-label="Pilih {{ $b['nama'] }}">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-stone-900">{{ $b['nama'] }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $b['leading'] ?? '—' }} · {{ Format::rupiah($b['total']) }} · {{ $b['bulan'] ?? 'Bulan —' }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @foreach ($b['sumber'] as $s)
                                <x-rab.lencana-sumber :sumber="$s['sumber']" class="text-[10px]" />
                            @endforeach
                            <x-ui.status-badge :status="$b['status']" />
                            <x-kode.lencana-hilang :hilang="$b['kode_hilang']" :tahun="$tahunAktif" />
                        </div>
                        @if ($b['sudah_ada'])
                            <p class="mt-1 text-xs text-gold-700">Sudah ada di TA {{ $tahunAktif }}</p>
                        @endif
                        <button type="button" x-on:click="buka = ! buka" class="mt-1 text-xs font-semibold text-brand-700" x-text="buka ? 'Sembunyikan rincian' : 'Lihat rincian'">Lihat rincian</button>
                        <div x-cloak x-show="buka" class="mt-2 text-xs">
                            @include('components.kegiatan.partials.rincian-tahun-lalu', ['b' => $b])
                        </div>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
