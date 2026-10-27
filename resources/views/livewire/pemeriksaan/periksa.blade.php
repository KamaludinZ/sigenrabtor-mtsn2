@use('App\Enums\StatusKegiatan')
@use('App\Livewire\Pemeriksaan\Periksa')
@use('App\Support\Format')
@use('App\Support\Rab\MekanismePembayaran')
@use('Illuminate\Support\Carbon')

@php
    $r = $this->ringkasan;
    $kak = $this->kak;
    $rab = $this->rab;
    $susunan = $dokumen === 'rab' ? $rab : $kak;
    $sumberAktif = $kak->sumber;
    $antrean = $this->antrean();
    $waktu = fn (string $pada) => Carbon::parse($pada)->locale('id')->translatedFormat('j M Y, H.i');
    $status = $r['status'];
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('pemeriksaan.index', $status === StatusKegiatan::Diperiksa ? [] : ['status' => $status->value]) }}" wire:navigate class="hover:text-stone-800">Pemeriksaan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="truncate text-stone-700">{{ \App\Support\Pemeriksaan\AntreanPemeriksaan::TAB[$status->value] }}</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">{{ $r['nama'] }}</h1>
                <x-ui.status-badge :status="$status->value" />
            </div>
            <p class="mt-1 text-sm text-stone-500">
                {{ $r['leading'] ? $r['leading'].' '.($r['nama_leading'] ?? '') : 'Leading belum dipilih' }} · {{ $r['pembuat'] ?? 'Pembuat tidak tercatat' }}
                @if ($r['diajukan'])
                    · <span data-testid="waktu-diajukan">Diajukan {{ $waktu($r['diajukan']['pada']) }} oleh {{ $r['diajukan']['oleh'] }}</span>
                @endif
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($antrean)
                <div class="flex items-center gap-1 rounded-lg bg-white px-1 py-1 text-xs ring-1 ring-stone-200" data-testid="posisi-antrean">
                    @if ($antrean['sebelum'])
                        <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $antrean['sebelum']]) }}" wire:navigate class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800" aria-label="Pengajuan sebelumnya" title="Pengajuan sebelumnya">
                            <x-ui.icon name="chevron-right" class="size-4 rotate-180" />
                        </a>
                    @else
                        <span class="p-1.5 text-stone-300"><x-ui.icon name="chevron-right" class="size-4 rotate-180" /></span>
                    @endif
                    <span class="px-1 font-medium text-stone-600 tabular-nums">Antrean {{ $antrean['posisi'] }} dari {{ $antrean['jumlah'] }}</span>
                    @if ($antrean['sesudah'])
                        <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $antrean['sesudah']]) }}" wire:navigate class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800" aria-label="Pengajuan berikutnya" title="Pengajuan berikutnya">
                            <x-ui.icon name="chevron-right" class="size-4" />
                        </a>
                    @else
                        <span class="p-1.5 text-stone-300"><x-ui.icon name="chevron-right" class="size-4" /></span>
                    @endif
                </div>
            @endif
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="btn-ghost text-sm">
                <x-ui.icon name="clipboard" class="size-4" /> Buka Data Kegiatan
            </a>
        </div>
    </div>

    <div class="mt-6 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] lg:grid-rows-[auto_auto_auto_1fr]">
        {{-- Ringkasan: kelengkapan tiap dokumen (juga navigasi) & anggaran. Di layar sempit tampil sebelum dokumen. --}}
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 lg:col-start-2 lg:row-start-2" aria-labelledby="judul-kelengkapan" data-testid="kelengkapan-dokumen">
            <h2 id="judul-kelengkapan" class="text-sm font-semibold text-stone-900">Dokumen yang diperiksa</h2>
            @if ($this->kelengkapan === [])
                <p class="mt-2 text-sm text-stone-500">Kegiatan belum memilih sumber dana, jadi KAK/TOR dan RAB belum bisa disusun.</p>
            @else
                <ul class="mt-2 space-y-2">
                    @foreach ($this->kelengkapan as $k)
                        <li class="rounded-xl bg-stone-50 p-2 ring-1 ring-stone-100" data-kelengkapan="{{ $k['sumber'] }}">
                            <x-rab.lencana-sumber :sumber="$k['sumber']" class="text-[10px]" />
                            <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                                @foreach (Periksa::DOKUMEN as $jenis => $label)
                                    @php
                                        $aktif = $dokumen === $jenis && $sumberAktif === $k['sumber'];
                                    @endphp
                                    <button type="button" wire:click="tampilkan('{{ $jenis }}', '{{ $k['sumber'] }}')" aria-pressed="{{ $aktif ? 'true' : 'false' }}"
                                            data-dokumen="{{ $jenis }}-{{ mb_strtolower($k['sumber']) }}"
                                            @class(['rounded-lg px-2 py-1.5 text-left text-xs ring-1 transition', 'bg-white ring-2 ring-brand-600' => $aktif, 'bg-white/60 ring-stone-200 hover:bg-white hover:ring-stone-300' => ! $aktif])>
                                        <span class="block font-semibold text-stone-800">{{ $label }}</span>
                                        <span @class(['mt-0.5 inline-flex items-center gap-1 font-medium', 'text-brand-700' => $k[$jenis] === 0, 'text-gold-700' => $k[$jenis] > 0])>
                                            <x-ui.icon :name="$k[$jenis] === 0 ? 'check-circle' : 'alert'" class="size-3.5" />
                                            {{ $k[$jenis] === 0 ? 'Lengkap' : $k[$jenis].' kurang' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->anggaran !== [])
                <h3 class="mt-4 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Anggaran</h3>
                <dl class="mt-1.5 divide-y divide-stone-100 text-xs" data-testid="ringkasan-anggaran">
                    @foreach ($this->anggaran as $a)
                        @php
                            $termin = MekanismePembayaran::dari($a);
                        @endphp
                        <div class="py-2" data-sumber="{{ $a->sumber }}">
                            <dt class="flex items-center justify-between gap-2">
                                <x-rab.lencana-sumber :sumber="$a->sumber" awalan="RAB" class="text-[10px]" />
                                <span class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($a->total()) }}</span>
                            </dt>
                            <dd class="mt-1 space-y-0.5 text-stone-500">
                                <p class="flex justify-between gap-2"><span>Pagu</span><span class="tabular-nums">{{ $a->pagu() === null ? '—' : Format::rupiah($a->pagu()) }}</span></p>
                                <p><x-rab.status-pagu :status="$a->keteranganPagu()" /></p>
                                <p @class(['flex items-center gap-1', 'text-red-600' => in_array($termin->status(), ['kurang', 'lebih'], true)]) data-termin="{{ $termin->status() }}">
                                    <x-ui.icon name="coins" class="size-3.5 shrink-0" /> {{ $termin->teksStatus() }}
                                </p>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- Dokumen --}}
        <section class="min-w-0 lg:col-start-1 lg:row-span-4 lg:row-start-1" aria-label="Pratinjau dokumen" data-testid="pratinjau-pemeriksaan">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-2xl bg-white px-4 py-3 ring-1 ring-stone-200">
                <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Jenis dokumen">
                    @foreach (Periksa::DOKUMEN as $jenis => $label)
                        <button type="button" wire:click="$set('dokumen', '{{ $jenis }}')" aria-pressed="{{ $dokumen === $jenis ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1 text-sm font-semibold transition', 'bg-white text-stone-900 shadow-xs' => $dokumen === $jenis, 'text-stone-500 hover:text-stone-800' => $dokumen !== $jenis])>{{ $label }}</button>
                    @endforeach
                </div>
                @if (count($kak->daftarSumber) > 1)
                    <div class="flex items-center gap-1.5" role="group" aria-label="Sumber dana">
                        @foreach ($kak->daftarSumber as $s)
                            <button type="button" wire:click="$set('sumber', '{{ $s }}')" aria-pressed="{{ $sumberAktif === $s ? 'true' : 'false' }}"
                                    @class(['rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 transition', 'bg-brand-50 text-brand-800 ring-brand-300' => $sumberAktif === $s, 'bg-white text-stone-600 ring-stone-200 hover:ring-stone-300' => $sumberAktif !== $s])>{{ $s }}</button>
                        @endforeach
                    </div>
                @elseif ($sumberAktif)
                    <x-rab.lencana-sumber :sumber="$sumberAktif" class="text-xs" />
                @endif
            </div>

            @if ($susunan->kurang)
                <div class="flex flex-wrap items-center gap-1.5 bg-gold-50 px-4 py-2 text-xs text-stone-700 ring-1 ring-gold-200" data-testid="dokumen-kurang">
                    <x-ui.icon name="alert" class="size-4 shrink-0 text-gold-600" />
                    <span class="font-semibold">{{ count($susunan->kurang) }} isian belum lengkap:</span>
                    @foreach ($susunan->kurang as $apa)
                        <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-gold-200">{{ $apa }}</span>
                    @endforeach
                </div>
            @endif

            <div class="rounded-b-2xl bg-stone-100 p-3 ring-1 ring-stone-200 sm:p-6" wire:loading.class="opacity-60" wire:target="dokumen,sumber">
                @if ($susunan->peringatanPagu)
                    <x-rab.peringatan-pagu :sumber="$susunan->sumber" :hasil="$susunan->peringatanPagu->hasil" ringkas class="mx-auto mb-3 max-w-[210mm]" />
                @endif
                @if ($dokumen === 'rab')
                    <x-dokumen.rab :susunan="$rab" wire:key="rab-{{ $sumberAktif }}" />
                @else
                    <x-dokumen.kak-tor :susunan="$kak" wire:key="kak-{{ $sumberAktif }}" />
                @endif
            </div>
        </section>

        {{-- Keputusan pemeriksaan --}}
        @include('livewire.pemeriksaan.partials.panel-keputusan')

        {{-- Riwayat pemeriksaan per putaran --}}
        @include('livewire.pemeriksaan.partials.riwayat-putaran', ['putaran' => $this->putaran])
    </div>
</div>
