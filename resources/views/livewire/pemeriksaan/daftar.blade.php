@use('App\Support\Format')
@use('App\Support\MasterKode')
@use('App\Support\Pemeriksaan\AntreanPemeriksaan')

@php
    $jumlah = $this->jumlah();
    $baris = $this->baris();
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $adaSaringan = $cari !== '' || $leading !== '';
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Pemeriksaan</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $tahun }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Alur mutu sebelum dokumen ditandatangani: periksa kegiatan yang diajukan leading, setujui atau kembalikan dengan catatan, lalu pantau arsip final yang mengunci data.</p>
        </div>
        <div class="inline-flex shrink-0 self-start rounded-lg bg-stone-100 p-0.5 sm:self-auto" role="group" aria-label="Tampilan" data-testid="tampilan-pemeriksaan">
            @foreach (['antrean' => ['Antrean', 'clipboard'], 'riwayat' => ['Riwayat', 'history']] as $kunciTampilan => [$labelTampilan, $ikonTampilan])
                <button type="button" wire:click="$set('tampilan', '{{ $kunciTampilan }}')" aria-pressed="{{ $tampilan === $kunciTampilan ? 'true' : 'false' }}"
                        @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition', 'bg-white text-stone-900 shadow-xs' => $tampilan === $kunciTampilan, 'text-stone-500 hover:text-stone-800' => $tampilan !== $kunciTampilan])>
                    <x-ui.icon :name="$ikonTampilan" class="size-4" /> {{ $labelTampilan }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($tampilan === 'riwayat')
        @include('livewire.pemeriksaan.partials.log-riwayat')
    @else
        {{-- Ringkasan per status sekaligus tab. --}}
        <nav class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Status pemeriksaan" data-testid="tab-pemeriksaan">
            @foreach (AntreanPemeriksaan::TAB as $kunci => $label)
                <button type="button" wire:click="$set('tab', '{{ $kunci }}')" aria-pressed="{{ $tab === $kunci ? 'true' : 'false' }}" data-tab="{{ $kunci }}"
                        @class(['rounded-2xl p-3 text-left ring-1 transition sm:p-4', 'bg-white shadow-xs ring-2 ring-brand-600' => $tab === $kunci, 'bg-white/70 ring-stone-200 hover:bg-white hover:ring-stone-300' => $tab !== $kunci, 'col-span-2 sm:col-span-1' => $loop->first])>
                    <span class="block text-xs font-semibold text-stone-500">{{ $label }}</span>
                    <span @class(['mt-1 block text-xl font-bold tabular-nums sm:text-2xl', 'text-sky-800' => $kunci === 'diperiksa' && $jumlah[$kunci] > 0, 'text-stone-900' => ! ($kunci === 'diperiksa' && $jumlah[$kunci] > 0)])>{{ $jumlah[$kunci] }}</span>
                </button>
            @endforeach
        </nav>

        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tab-pemeriksaan" data-testid="daftar-pemeriksaan">
            <header class="space-y-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 id="judul-tab-pemeriksaan" class="text-base font-semibold text-stone-900">{{ AntreanPemeriksaan::TAB[$tab] }}</h2>
                    <p class="mt-0.5 text-sm text-stone-500">{{ AntreanPemeriksaan::KETERANGAN[$tab] }}{{ $tab === 'diperiksa' ? ' Pengajuan terlama di atas.' : '' }}</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem]">
                    <label>
                        <span class="sr-only">Cari kegiatan</span>
                        <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan atau pembuat…" class="form-input py-1.5" autocomplete="off">
                    </label>
                    <label>
                        <span class="sr-only">Saring leading</span>
                        <select wire:model.live="leading" class="form-input py-1.5" aria-label="Leading">
                            <option value="">Semua leading</option>
                            @foreach (MasterKode::semuaLeading() as $l)
                                <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}{{ $l['is_active'] ? '' : ' (nonaktif)' }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </header>

            @if ($baris === [])
                <div class="px-5 py-12 text-center sm:px-6" data-testid="pemeriksaan-kosong">
                    <x-ui.icon name="check-badge" class="mx-auto size-10 text-stone-300" />
                    @if ($adaSaringan)
                        <p class="mt-3 text-sm font-medium text-stone-700">Tidak ada kegiatan yang cocok.</p>
                        <button type="button" wire:click="hapusSaringan" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                    @elseif ($tab === 'diperiksa')
                        <p class="mt-3 text-sm font-medium text-stone-700">Tidak ada pengajuan yang menunggu diperiksa.</p>
                        <p class="mt-1 text-sm text-stone-500">Leading mengajukan dari Data Kegiatan › <em>Ajukan pemeriksaan</em> setelah isian wajib lengkap.</p>
                    @else
                        <p class="mt-3 text-sm font-medium text-stone-700">Belum ada kegiatan berstatus {{ mb_strtolower(AntreanPemeriksaan::TAB[$tab]) }}.</p>
                    @endif
                </div>
            @else
                <ul class="divide-y divide-stone-100" wire:loading.class="opacity-60" wire:target="tab,cari,leading">
                    @foreach ($baris as $b)
                        @php
                            $waktu = $tab === 'diperiksa' ? $b['diajukan'] : $b['terakhir'];
                        @endphp
                        <li class="grid gap-x-6 gap-y-3 px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_16rem_9rem]" data-kegiatan="{{ $b['id'] }}" wire:key="periksa-{{ $b['id'] }}">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $b['id']]) }}" wire:navigate class="font-semibold text-stone-900 hover:text-brand-800">{{ $b['nama'] }}</a>
                                    <x-ui.status-badge :status="$b['status']->value" />
                                </div>
                                <p class="mt-0.5 text-xs text-stone-500">
                                    {{ $b['leading'] ? $b['leading'].' '.($b['nama_leading'] ?? '') : 'Leading belum dipilih' }} · {{ $b['pembuat'] ?? 'Pembuat tidak tercatat' }}
                                </p>
                                <p class="mt-1 text-xs text-stone-600" data-waktu-status>
                                    @if ($waktu)
                                        {{ $tab === 'diperiksa' ? 'Diajukan' : \App\Enums\StatusKegiatan::labelRiwayat($waktu['aksi']) }} {{ Format::tanggal($waktu['pada']) }} oleh {{ $waktu['oleh'] }}
                                    @else
                                        Belum ada riwayat status.
                                    @endif
                                </p>
                            </div>
                            <div class="min-w-0 text-xs">
                                @forelse ($b['anggaran'] as $a)
                                    <p class="flex items-start justify-between gap-2 py-0.5" data-sumber="{{ $a['sumber'] }}">
                                        <x-rab.lencana-sumber :sumber="$a['sumber']" awalan="RAB" class="text-[10px]" />
                                        <span class="text-right">
                                            <span class="block font-semibold text-stone-800 tabular-nums">{{ Format::rupiah($a['total']) }}</span>
                                            <x-rab.status-pagu :status="$a['pagu']" />
                                        </span>
                                    </p>
                                @empty
                                    <p class="text-stone-500">Sumber dana belum dipilih.</p>
                                @endforelse
                            </div>
                            <div class="flex flex-row items-center justify-between gap-2 lg:flex-col lg:items-end">
                                <span @class(['inline-flex items-center gap-1 text-xs font-medium', 'text-brand-700' => $b['kurang'] === 0, 'text-gold-700' => $b['kurang'] > 0]) data-kurang="{{ $b['kurang'] }}">
                                    <x-ui.icon :name="$b['kurang'] === 0 ? 'check-circle' : 'alert'" class="size-3.5" />
                                    {{ $b['kurang'] === 0 ? 'Dokumen lengkap' : $b['kurang'].' isian kurang' }}
                                </span>
                                @if ($tab === 'diperiksa')
                                    <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $b['id']]) }}" wire:navigate class="btn-primary px-3 py-1.5 text-xs">Periksa <x-ui.icon name="chevron-right" class="size-3.5" /></a>
                                @else
                                    <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $b['id']]) }}" wire:navigate class="btn-ghost px-2.5 py-1 text-xs">Lihat dokumen <x-ui.icon name="chevron-right" class="size-3.5" /></a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
