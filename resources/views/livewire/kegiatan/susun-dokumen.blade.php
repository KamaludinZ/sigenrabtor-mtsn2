@use('App\Support\Dokumen\DokumenKegiatan')
@use('Illuminate\Support\Carbon')

@php
    $kak = $this->kak;
    $rab = $this->rab;
    $susunan = $this->susunan;
    $statusCetak = $this->statusCetak;
    $draf = $this->draf();
    $sumberAktif = $kak->sumber;
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $admin = \App\Support\Stub\StubData::currentUser()['role'] === 'admin';
    $nadaStatus = [
        'lengkap' => ['label' => 'Lengkap', 'ikon' => 'check-circle', 'kelas' => 'bg-brand-50 text-brand-800 ring-brand-200', 'titik' => 'bg-brand-600'],
        'kurang' => ['label' => 'Perlu dilengkapi', 'ikon' => 'alert', 'kelas' => 'bg-gold-50 text-gold-700 ring-gold-200', 'titik' => 'bg-gold-500'],
        'periksa' => ['label' => 'Perlu diperiksa', 'ikon' => 'info', 'kelas' => 'bg-sky-50 text-sky-800 ring-sky-200', 'titik' => 'bg-sky-600'],
    ];
    $waktu = fn (string $iso) => Carbon::parse($iso)->locale('id')->translatedFormat('j F Y, H.i');
@endphp

{{--
    Susun KAK/TOR (PRD F4): sembilan bagian baku disusun otomatis dari data kegiatan, pengaturan tahun aktif, dan RAB.
    Tiap kartu bagian: status, asal isinya (bertaut ke tempat mengisi), isian yang kurang, dan cuplikan hasil susunan
    (<x-dokumen.kak-tor :bagian="[n]" :cap="false">). Samping: kemajuan + daftar bagian, draf dokumen, master template.
--}}
<div class="mx-auto max-w-7xl" x-data="{ semua: true }">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan' }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">Susun KAK/TOR</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Susun KAK/TOR</h1>
                <x-ui.status-badge :status="$this->status()->value" />
            </div>
            <p class="mt-1 text-sm text-stone-500">Sembilan bagian KAK/TOR dan RAB disusun otomatis dari data kegiatan, pengaturan TA {{ $tahun }}, dan RAB — lengkapi isian yang ditandai, hasilnya langsung ikut.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <a href="{{ route('kegiatan.pratinjau', ['kegiatan' => $kegiatanId] + ($sumberAktif ? ['sumber' => $sumberAktif] : [])) }}" wire:navigate class="btn-secondary" data-testid="buka-pratinjau">
                <x-ui.icon name="eye" class="size-4" /> Pratinjau dokumen
            </a>
            <button type="button" class="btn-primary" disabled data-testid="simpan-draf-dokumen"><x-ui.icon name="save" class="size-4" /> Simpan draf <x-ui.segera /></button>
        </div>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="dokumen" :kegiatan-id="$kegiatanId" />
    </div>

    {{-- Bilah susunan: sumber dana, kemajuan, keadaan cetak. --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-3 py-2.5 ring-1 ring-stone-200" data-testid="bilah-susun">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            @if (count($kak->daftarSumber) > 1)
                <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Sumber dana">
                    @foreach ($kak->daftarSumber as $s)
                        <button type="button" wire:click="$set('sumber', '{{ $s }}')" aria-pressed="{{ $sumberAktif === $s ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1 text-sm font-semibold transition', 'bg-white text-brand-800 shadow-xs' => $sumberAktif === $s, 'text-stone-600 hover:text-stone-900' => $sumberAktif !== $s])>{{ $s }}</button>
                    @endforeach
                </div>
            @elseif ($sumberAktif)
                <x-rab.lencana-sumber :sumber="$sumberAktif" class="text-xs" />
            @else
                <span class="text-xs font-medium text-stone-500">Belum ada sumber dana</span>
            @endif
            <span class="text-sm text-stone-600" data-testid="ringkasan-susun"><span class="font-semibold text-stone-900">{{ $susunan->lengkap }}</span> dari {{ $susunan->jumlah() }} bagian lengkap</span>
        </div>
        <span @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1', 'bg-red-50 text-red-700 ring-red-200' => $statusCetak->set->draf, 'bg-brand-50 text-brand-800 ring-brand-200' => ! $statusCetak->set->draf]) data-testid="keadaan-susun" data-draf="{{ $statusCetak->set->draf ? 'ya' : 'tidak' }}">
            <x-ui.icon :name="$statusCetak->set->draf ? 'alert' : 'check-circle'" class="size-3.5" />
            {{ $statusCetak->keadaan() }}
        </span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            {{-- Daftar bagian di layar sempit (di layar lebar ada di panel samping). --}}
            <nav class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 lg:hidden" aria-label="Bagian KAK/TOR">
                @foreach ($susunan->bagian as $b)
                    <a href="#susun-bagian-{{ $b['nomor'] }}" class="flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200">
                        <span class="size-1.5 rounded-full {{ $nadaStatus[$b['status']]['titik'] }}" aria-hidden="true"></span>{{ $b['nomor'] }}. {{ $b['judul'] }}
                    </a>
                @endforeach
                <a href="#susun-rab" class="flex shrink-0 items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200">RAB</a>
            </nav>

            @foreach ($susunan->bagian as $b)
                @php $nada = $nadaStatus[$b['status']]; @endphp
                <section id="susun-bagian-{{ $b['nomor'] }}" class="scroll-mt-24 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200"
                         x-data="{ buka: true }" x-effect="buka = semua"
                         data-testid="bagian-susun" data-bagian="{{ $b['nomor'] }}" data-status="{{ $b['status'] }}" aria-labelledby="judul-susun-{{ $b['nomor'] }}"
                         wire:key="susun-{{ $b['nomor'] }}-{{ $sumberAktif }}">
                    <header class="flex items-start gap-3 px-4 py-3 sm:px-5">
                        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-stone-100 text-sm font-bold text-stone-700" aria-hidden="true">{{ $b['nomor'] }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 id="judul-susun-{{ $b['nomor'] }}" class="text-sm font-semibold text-stone-900">{{ $b['judul'] }}</h2>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $nada['kelas'] }}" data-testid="status-bagian">
                                    <x-ui.icon :name="$nada['ikon']" class="size-3" /> {{ $nada['label'] }}@if ($b['kurang'] !== [] || $b['periksa'] !== []) · {{ count($b['kurang']) ?: count($b['periksa']) }}@endif
                                </span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-stone-500" data-testid="asal-bagian">
                                <span>Diisi dari</span>
                                @foreach ($b['asal'] as $asal)
                                    @if ($asal['tautan'])
                                        <a href="{{ $asal['tautan'] }}" @if (! $asal['admin']) wire:navigate @endif class="inline-flex items-center rounded-md bg-stone-100 px-1.5 py-0.5 font-medium text-stone-700 hover:bg-brand-50 hover:text-brand-800" data-asal="{{ $asal['kunci'] }}">{{ $asal['label'] }}</a>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-md bg-stone-100 px-1.5 py-0.5 font-medium text-stone-500" data-asal="{{ $asal['kunci'] }}" @if ($asal['admin']) title="Diatur admin" @endif>
                                            @if ($asal['admin'])<x-ui.icon name="lock" class="size-3" />@endif{{ $asal['label'] }}
                                        </span>
                                    @endif
                                @endforeach
                            </p>
                        </div>
                        <button type="button" class="btn-ghost -mr-2 shrink-0 px-2" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-controls="isi-susun-{{ $b['nomor'] }}">
                            <x-ui.icon name="chevron-down" class="size-4 transition" x-bind:class="{ 'rotate-180': buka }" />
                            <span class="sr-only">Tampilkan atau sembunyikan bagian {{ $b['nomor'] }}</span>
                        </button>
                    </header>
                    <div id="isi-susun-{{ $b['nomor'] }}" x-show="buka">
                        @if ($b['kurang'] !== [])
                            <div class="mx-4 mb-3 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800 ring-1 ring-gold-200 sm:mx-5" data-testid="kurang-bagian">
                                <p class="font-semibold">Belum lengkap — tertandai kuning di dokumen:</p>
                                <ul class="mt-1 ml-4 list-disc space-y-0.5">
                                    @foreach ($b['kurang'] as $apa)
                                        <li>{{ \Illuminate\Support\Str::ucfirst($apa) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($b['periksa'] !== [])
                            <div class="mx-4 mb-3 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900 ring-1 ring-sky-200 sm:mx-5" data-testid="periksa-bagian">
                                <p class="font-semibold">Periksa sebelum dicetak:</p>
                                <ul class="mt-1 ml-4 list-disc space-y-0.5">
                                    @foreach ($b['periksa'] as $apa)
                                        <li>{{ $apa }}.</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="rounded-b-2xl border-t border-stone-100 bg-stone-50 p-2 sm:p-4">
                            <x-dokumen.kak-tor :susunan="$kak" :bagian="[$b['nomor']]" :cap="false" />
                        </div>
                    </div>
                </section>
            @endforeach

            {{-- RAB otomatis: kepala dokumen RAB sumber dana ini. --}}
            <section id="susun-rab" class="scroll-mt-24 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="susun-rab" aria-labelledby="judul-susun-rab">
                <header class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="judul-susun-rab" class="text-sm font-semibold text-stone-900">RAB otomatis</h2>
                            @if ($sumberAktif)
                                <x-rab.lencana-sumber :sumber="$sumberAktif" awalan="RAB" class="text-[11px]" />
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-stone-500">Kepala RAB diisi dari kode & klasifikasi dan total rincian; rincian disusun di halaman RAB.</p>
                    </div>
                    <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId] + ($sumberAktif ? ['sumber' => $sumberAktif] : [])) }}" wire:navigate class="btn-secondary shrink-0 text-xs"><x-ui.icon name="coins" class="size-3.5" /> Buka RAB</a>
                </header>
                @if ($sumberAktif === null)
                    <p class="mx-4 mb-4 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800 ring-1 ring-gold-200 sm:mx-5">Pilih sumber dana di Data Kegiatan › Sumber Dana agar RAB dapat disusun.</p>
                @else
                    <dl class="divide-y divide-stone-100 border-t border-stone-100 text-sm">
                        @foreach ($susunan->kepalaRab as $baris)
                            <div class="grid gap-1 px-4 py-2 sm:grid-cols-[11rem_minmax(0,1fr)] sm:px-5" data-testid="kepala-rab">
                                <dt class="text-xs font-medium text-stone-500">{{ $baris['label'] }}</dt>
                                <dd class="min-w-0 text-stone-800">
                                    @if ($baris['nilai'] !== null)
                                        <span class="break-words">{{ $baris['nilai'] }}</span>
                                        @if ($baris['uraian'])
                                            <span class="block text-xs text-stone-500">{{ $baris['uraian'] }}</span>
                                        @endif
                                    @else
                                        <span class="rounded bg-gold-50 px-1.5 py-0.5 text-xs font-medium text-gold-800 ring-1 ring-gold-200">Belum diisi</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 sm:px-5" data-testid="ringkasan-rincian">
                            <span class="text-xs text-stone-500">{{ $rab->anggaran?->jumlahItem() ?? 0 }} baris rincian</span>
                            <span class="text-sm font-semibold text-stone-900">Total <x-ui.uang :nilai="$rab->anggaran?->total() ?? 0" /></span>
                        </div>
                    </dl>
                @endif
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            {{-- Kemajuan & daftar bagian --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kemajuan-susun" data-testid="kemajuan-susun">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="judul-kemajuan-susun" class="text-sm font-semibold text-stone-900">Kemajuan susunan</h2>
                    <span class="text-xs font-semibold text-stone-600">{{ $susunan->persen() }}%</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $susunan->jumlah() }}" aria-valuenow="{{ $susunan->lengkap }}" aria-label="Bagian lengkap">
                    <div class="h-full rounded-full bg-brand-600" style="width: {{ $susunan->persen() }}%"></div>
                </div>
                <ol class="mt-3 hidden space-y-0.5 text-sm lg:block">
                    @foreach ($susunan->bagian as $b)
                        <li>
                            <a href="#susun-bagian-{{ $b['nomor'] }}" class="flex items-center gap-2 rounded-md px-2 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                                <span class="size-1.5 shrink-0 rounded-full {{ $nadaStatus[$b['status']]['titik'] }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $b['nomor'] }}. {{ $b['judul'] }}</span>
                                @if ($b['kurang'] !== [])
                                    <span class="text-[11px] font-semibold text-gold-700">{{ count($b['kurang']) }}</span>
                                @elseif ($b['periksa'] !== [])
                                    <span class="text-[11px] font-semibold text-sky-700">{{ count($b['periksa']) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="#susun-rab" class="flex items-center gap-2 rounded-md px-2 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                            <span @class(['size-1.5 shrink-0 rounded-full', 'bg-gold-500' => $susunan->kurangRab !== [], 'bg-brand-600' => $susunan->kurangRab === []]) aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 truncate">RAB {{ $sumberAktif }}</span>
                        </a>
                    </li>
                </ol>
                <div class="mt-3 flex gap-2 border-t border-stone-100 pt-3">
                    <button type="button" class="btn-ghost flex-1 justify-center text-xs" x-on:click="semua = true" data-testid="buka-semua-bagian">Buka semua</button>
                    <button type="button" class="btn-ghost flex-1 justify-center text-xs" x-on:click="semua = false" data-testid="ciutkan-semua-bagian">Ciutkan semua</button>
                </div>
            </section>

            {{-- Draf dokumen --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-draf-dokumen" data-testid="draf-dokumen" data-ada="{{ $draf ? 'ya' : 'tidak' }}">
                <h2 id="judul-draf-dokumen" class="text-sm font-semibold text-stone-900">Draf dokumen</h2>
                @if ($draf)
                    <p class="mt-1.5 text-sm text-stone-700">{{ DokumenKegiatan::JENIS[$draf['jenis']] ?? $draf['jenis'] }}@if ($draf['sumber']) · {{ $draf['sumber'] }}@endif</p>
                    <p class="mt-0.5 text-xs text-stone-500">Tersimpan {{ $waktu($draf['disimpan_pada']) }} oleh {{ $draf['oleh'] }}</p>
                @else
                    <p class="mt-1.5 text-sm text-stone-600">Belum ada draf dokumen tersimpan.</p>
                @endif
                <p class="mt-2 text-xs text-stone-500">Isi dokumen selalu mengikuti data terbaru. Simpan draf untuk menandai titik lanjut penyusunan.</p>
            </section>

            {{-- Master template KAK/TOR --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-template-susun" data-testid="template-susun" data-ada="{{ $susunan->template ? 'ya' : 'tidak' }}">
                <h2 id="judul-template-susun" class="text-sm font-semibold text-stone-900">Master template TA {{ $tahun }}</h2>
                @if ($susunan->template)
                    <p class="mt-1.5 text-sm text-stone-700">{{ $susunan->template['nama'] }}</p>
                    <p class="mt-0.5 text-xs text-stone-500">{{ DokumenKegiatan::JENIS[$susunan->template['jenis']] ?? $susunan->template['jenis'] }} · {{ count($susunan->template['variabel']) }} variabel · diperbarui {{ $waktu($susunan->template['diperbarui_pada']) }}</p>
                @else
                    <p class="mt-1.5 text-sm text-stone-600">Belum ada master KAK/TOR — dokumen memakai susunan baku aplikasi.</p>
                @endif
                @if ($admin)
                    <a href="{{ route('pengaturan.template.index', ['tab' => 'kak']) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">Atur master KAK/TOR <x-ui.icon name="chevron-right" class="size-3" /></a>
                @else
                    <p class="mt-2 text-xs text-stone-500">Master template diatur admin.</p>
                @endif
            </section>
        </aside>
    </div>
</div>
