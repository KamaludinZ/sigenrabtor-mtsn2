@use('App\Support\Dokumen\KaidahDokumen')

@php
    $kak = $this->kak;
    $rab = $this->rab;
    $cover = $this->cover;
    $kaidah = $this->kaidah;
    $ringkasan = KaidahDokumen::ringkasan($kaidah);
    $sumberAktif = $kak->sumber;
    $perhatian = $ringkasan['gagal'] + $ringkasan['peringatan'];
    $gayaStatus = [
        'lulus' => ['check-circle', 'text-brand-700'],
        'peringatan' => ['alert', 'text-gold-600'],
        'gagal' => ['alert', 'text-red-600'],
        'info' => ['info', 'text-sky-700'],
    ];
    $dokumen = ['cover' => 'Cover', 'kak' => 'KAK/TOR', 'rab' => 'RAB'];
    $tahunDokumen = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $kepalaTahun = fn (string $d) => \App\Support\Dokumen\TahunDokumen::untukDokumen($this->tahunCetak, $d);
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan' }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">Pratinjau Dokumen</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Pratinjau Dokumen</h1>
                <x-ui.status-badge :status="$this->status()->value" />
            </div>
            <p class="mt-1 text-sm text-stone-500">Cover, KAK/TOR, dan RAB seperti hasil cetak — disusun otomatis dari data kegiatan.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <button type="button" class="btn-secondary" disabled><x-ui.icon name="download" class="size-4" /> Unduh Word <x-ui.segera /></button>
            <button type="button" class="btn-primary" disabled><x-ui.icon name="printer" class="size-4" /> Cetak PDF <x-ui.segera /></button>
        </div>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="pratinjau" :kegiatan-id="$kegiatanId" />
    </div>

    {{-- Bilah dokumen: sumber dana, lompat ke dokumen, keadaan cetak. --}}
    <div class="sticky top-16 z-20 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 bg-stone-50/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 lg:mx-0 lg:rounded-xl lg:border lg:px-3" data-testid="bilah-pratinjau">
        <div class="flex flex-wrap items-center gap-2">
            @if (count($kak->daftarSumber) > 1)
                <div class="inline-flex rounded-lg bg-white p-0.5 ring-1 ring-stone-200" role="group" aria-label="Sumber dana">
                    @foreach ($kak->daftarSumber as $s)
                        <button type="button" wire:click="$set('sumber', '{{ $s }}')" aria-pressed="{{ $sumberAktif === $s ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1 text-sm font-semibold transition', 'bg-brand-700 text-white' => $sumberAktif === $s, 'text-stone-600 hover:text-stone-900' => $sumberAktif !== $s])>{{ $s }}</button>
                    @endforeach
                </div>
            @elseif ($sumberAktif)
                <x-rab.lencana-sumber :sumber="$sumberAktif" class="text-xs" />
            @endif
            <nav class="flex items-center gap-1 text-sm" aria-label="Dokumen">
                @foreach ($dokumen as $id => $label)
                    <a href="#dok-{{ $id }}" class="rounded-md px-2 py-1 font-medium text-stone-600 hover:bg-white hover:text-stone-900">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        @php
            $statusCetak = $this->statusCetak;
            $statusDraf = $statusCetak->set;
        @endphp
        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" data-testid="keadaan-cetak" data-draf="{{ $statusDraf->draf ? 'ya' : 'tidak' }}"
                    @class(['inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold ring-1', 'text-red-700 ring-red-200' => $statusDraf->draf, 'text-brand-800 ring-brand-200' => ! $statusDraf->draf])>
                <x-ui.icon :name="$statusDraf->draf ? 'alert' : 'check-circle'" class="size-3.5" />
                {{ $statusCetak->keadaan() }}
                <x-ui.icon name="chevron-down" class="size-3 opacity-60" />
            </button>
            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-3 text-xs shadow-lg ring-1 ring-stone-200" data-testid="alasan-draf">
                @if ($statusDraf->draf)
                    <p class="text-sm font-semibold text-stone-900">Mengapa tercetak sebagai DRAF?</p>
                    <p class="mt-0.5 text-stone-500">Dokumen tetap bisa dicetak, tetapi bercap DRAF sampai semua hal di bawah selesai.</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($statusCetak->saran() as $alasan)
                            <li class="flex gap-2" data-alasan="{{ $alasan['kunci'] }}">
                                <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-red-600" />
                                <div class="min-w-0">
                                    <p class="font-medium text-stone-800">{{ $alasan['teks'] }}</p>
                                    <a href="{{ $alasan['tautan'] }}" @if (! str_starts_with($alasan['tautan'], '#')) wire:navigate @endif x-on:click="open = false" class="text-brand-700 hover:text-brand-800">{{ $alasan['saran'] }}</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm font-semibold text-stone-900">Siap dicetak</p>
                    <p class="mt-0.5 text-stone-500">Kegiatan sudah disetujui, isian lengkap, dan RAB dalam batas pagu — dokumen tercetak tanpa cap DRAF.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Kaidah dokumen otomatis (PRD §8). Di layar sempit tampil sebelum dokumen. --}}
        <aside class="space-y-4 lg:sticky lg:top-36 lg:col-start-2 lg:row-start-1" aria-labelledby="judul-kaidah" data-testid="panel-kaidah">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 id="judul-kaidah" class="text-sm font-semibold text-stone-900">Kaidah dokumen</h2>
                    <span @class(['text-xs font-semibold', 'text-brand-700' => $perhatian === 0, 'text-gold-700' => $perhatian > 0]) data-testid="ringkasan-kaidah">
                        {{ $perhatian === 0 ? 'Semua terpenuhi' : $perhatian.' perlu perhatian' }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-stone-500">Aturan yang menjamin angka, tahun, kode, dan kelengkapan dokumen {{ $sumberAktif ? 'RAB '.$sumberAktif : '' }} selalu benar.</p>
                <ul class="mt-3 space-y-2.5">
                    @foreach ($kaidah as $k)
                        <li class="flex gap-2 text-xs" data-kaidah="{{ $k['kunci'] }}" data-status="{{ $k['status'] }}">
                            <x-ui.icon :name="$gayaStatus[$k['status']][0]" class="mt-px size-4 shrink-0 {{ $gayaStatus[$k['status']][1] }}" />
                            <div class="min-w-0">
                                <p class="font-semibold text-stone-800"><span class="font-normal text-stone-400">{{ $k['nomor'] }}.</span> {{ $k['judul'] }}</p>
                                <p @class(['mt-0.5', 'text-red-700' => $k['status'] === 'gagal', 'text-stone-600' => $k['status'] !== 'gagal'])>{{ $k['pesan'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Rupiah & terbilang: tiap tempat cetak total RAB, tautan ke letaknya di dokumen. --}}
                @if ($this->angka)
                    <x-dokumen.tempat-cetak :angka="$this->angka" :sumber="$sumberAktif" tautan="" />
                @endif

                {{-- Tanggal Indonesia: tiap tanggal yang tercetak, tautan ke letaknya. --}}
                <x-dokumen.tanggal-cetak :tanggal="$this->tanggal" />

                {{-- Satu tahun untuk semua: tahun di header/judul & isian yang menyebut tahun. --}}
                <x-dokumen.tahun-cetak :tempat="$this->tahunCetak" :tahun="$tahunDokumen" />
            </section>

            <x-dokumen.checklist-isian :grup="$this->checklist" />
        </aside>

        {{-- Dokumen berurutan seperti dicetak. --}}
        <div class="min-w-0 space-y-6 rounded-2xl bg-stone-100 p-3 ring-1 ring-stone-200 sm:p-6 lg:col-start-1 lg:row-start-1" wire:loading.class="opacity-60" wire:target="sumber" data-testid="dokumen-pratinjau">
            <section id="dok-cover" class="scroll-mt-36" aria-label="Cover" wire:key="cover-{{ $sumberAktif }}">
                <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">Cover <x-dokumen.kepala-draf :status="$statusDraf" /> <x-dokumen.kepala-tahun :ringkasan="$kepalaTahun('cover')" :tahun="$tahunDokumen" /></p>
                <x-dokumen.cover :gambar="$cover['gambar']" :teks="$cover['teks']" :nilai="$cover['nilai']" :draf="$statusDraf->draf" class="max-w-[210mm]" />
            </section>

            <section id="dok-kak" class="scroll-mt-36" aria-label="KAK/TOR" wire:key="kak-{{ $sumberAktif }}">
                <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">KAK/TOR <x-dokumen.kepala-draf :status="$kak->statusDraf" /> <x-dokumen.kepala-tahun :ringkasan="$kepalaTahun('kak')" :tahun="$tahunDokumen" /></p>
                @if ($kak->peringatanPagu)
                    <x-rab.peringatan-pagu :sumber="$kak->sumber" :hasil="$kak->peringatanPagu->hasil" ringkas class="mx-auto mb-3 max-w-[210mm]" />
                @endif
                <x-dokumen.kak-tor :susunan="$kak" :bagian="range(1, 9)" />
            </section>

            <section id="dok-rab" class="scroll-mt-36" aria-label="RAB" wire:key="rab-{{ $sumberAktif }}">
                <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">RAB{{ $sumberAktif ? ' '.$sumberAktif : '' }} <x-dokumen.kepala-draf :status="$rab->statusDraf" /> <x-dokumen.kepala-tahun :ringkasan="$kepalaTahun('rab')" :tahun="$tahunDokumen" /></p>
                <x-dokumen.rab :susunan="$rab" />
            </section>
        </div>
    </div>
</div>
