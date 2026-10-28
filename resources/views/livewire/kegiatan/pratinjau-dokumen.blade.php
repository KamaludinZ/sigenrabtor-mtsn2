@use('App\Support\Dokumen\KaidahDokumen')
@use('App\Support\Dokumen\PenampilDokumen')

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
    $dokumen = PenampilDokumen::LEMBAR;
    $isi = $this->isiDokumen;
    $kurangIsi = array_sum(array_column($isi, 'kurang'));
    $bisaUbah = $this->dapatDiubah();
    $sunting = $mode === 'sunting' && $bisaUbah ? ['terbuka' => $suntingTerbuka, 'kegiatan_id' => $kegiatanId] : null;
    $penanda = ['kegiatan_id' => $kegiatanId, 'pengguna' => \App\Support\Stub\StubData::currentUser(), 'isi' => $bisaUbah];
    $ringkasanKosong = \App\Support\Dokumen\PenandaKosong::ringkasan($this->penandaKosong);
    $tahunDokumen = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $kepalaTahun = fn (string $d) => \App\Support\Dokumen\TahunDokumen::untukDokumen($this->tahunCetak, $d);
@endphp

{{--
    Pratinjau Dokumen (PRD F5 "Tampilan Seperti Cetak"): penampil Alpine `penampilDokumen` (resources/js/penampil-dokumen.js)
    menampilkan cover, KAK/TOR, dan RAB sebagai lembar A4 (`data-lembar`) berperbesaran dengan perkiraan batas halaman,
    penunjuk halaman, dan daftar isi berjangkar (PenampilDokumen::isi); panel kaidah & checklist di samping.
--}}
<div class="mx-auto max-w-7xl" x-data="penampilDokumen(@js(array_map('strval', array_keys(PenampilDokumen::PERBESARAN))), @js(PenampilDokumen::BAWAAN), @js(array_map(fn ($b) => ['kunci' => $b['kunci'], 'jangkar' => $b['jangkar']], $isi)))">
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

    {{-- Bilah dokumen: sumber dana, lompat ke dokumen, mode, keadaan cetak. --}}
    <div class="-mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 bg-stone-50 px-4 py-2 sm:-mx-6 sm:px-6 lg:mx-0 lg:rounded-xl lg:border lg:px-3" data-testid="bilah-pratinjau">
        <div class="flex flex-wrap items-center gap-2">
            @if (count($kak->daftarSumber) > 1)
                <div class="inline-flex rounded-lg bg-white p-0.5 ring-1 ring-stone-200" role="group" aria-label="Sumber dana">
                    @foreach ($kak->daftarSumber as $s)
                        <button type="button" wire:click="$set('sumber', '{{ $s }}')" x-jaga-sunting aria-pressed="{{ $sumberAktif === $s ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1 text-sm font-semibold transition', 'bg-brand-700 text-white' => $sumberAktif === $s, 'text-stone-600 hover:text-stone-900' => $sumberAktif !== $s])>{{ $s }}</button>
                    @endforeach
                </div>
            @elseif ($sumberAktif)
                <x-rab.lencana-sumber :sumber="$sumberAktif" class="text-xs" />
            @endif
            <nav class="flex items-center gap-1 text-sm" aria-label="Dokumen">
                @foreach ($dokumen as $id => $label)
                    <a href="#dok-{{ $id }}" class="rounded-md px-2 py-1 font-medium text-stone-600 hover:bg-white hover:text-stone-900" data-dokumen="{{ $id }}">{{ $label }}<span class="ml-1 text-[11px] font-normal text-stone-400" aria-hidden="true" x-cloak x-show="cetak" x-text="(halaman['{{ $id }}'] ?? 1) + ' hlm'" data-testid="halaman-{{ $id }}"></span></a>
                @endforeach
            </nav>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg bg-white p-0.5 ring-1 ring-stone-200" role="group" aria-label="Mode pratinjau" data-testid="mode-pratinjau" data-mode="{{ $mode }}">
                @foreach (\App\Livewire\Kegiatan\PratinjauDokumen::MODE as $kunciMode => $labelMode)
                    @php
                        $bisaMode = $kunciMode === 'baca' || $bisaUbah;
                    @endphp
                    <button type="button" wire:click="$set('mode', '{{ $kunciMode }}')" x-jaga-sunting aria-pressed="{{ $mode === $kunciMode ? 'true' : 'false' }}" data-pilihan-mode="{{ $kunciMode }}"
                            @disabled(! $bisaMode) @unless ($bisaMode) title="{{ $this->alasanTerkunci() }}" @endunless
                            @class(['inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-semibold transition', 'bg-brand-700 text-white' => $mode === $kunciMode, 'text-stone-600 hover:text-stone-900' => $mode !== $kunciMode && $bisaMode, 'cursor-not-allowed text-stone-400' => ! $bisaMode])>
                        <x-ui.icon :name="$kunciMode === 'baca' ? 'eye' : 'pencil'" class="size-3.5" /> {{ $labelMode }}
                    </button>
                @endforeach
            </div>
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
        <aside class="space-y-4 lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1" aria-labelledby="judul-kaidah" data-testid="panel-kaidah">
            <x-dokumen.ringkasan-kelengkapan :kelengkapan="$this->kelengkapan" penampil />
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

        {{-- Dokumen berurutan seperti dicetak: lembar A4 berperbesaran dengan perkiraan batas halaman. --}}
        <div class="min-w-0 rounded-2xl bg-stone-200/70 ring-1 ring-stone-200 lg:col-start-1 lg:row-start-1" wire:loading.class="opacity-60" wire:target="sumber" data-testid="dokumen-pratinjau">
            {{-- Kendali penampil (lengket di atas dokumen): daftar isi, penunjuk halaman, perbesaran. --}}
            <div class="sticky top-16 z-20 flex flex-wrap items-center gap-1.5 rounded-t-2xl border-b border-stone-300/70 bg-stone-200/95 px-3 py-2 backdrop-blur sm:px-4" data-testid="kendali-penampil">
                <div class="relative" x-on:click.outside="isiTerbuka = false"" x-on:keydown.escape="isiTerbuka = false">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-stone-700 ring-1 ring-stone-200 hover:text-stone-900" x-on:click="isiTerbuka = ! isiTerbuka" x-bind:aria-expanded="isiTerbuka" aria-controls="daftar-isi-pratinjau" data-testid="buka-daftar-isi">
                        <x-ui.icon name="menu" class="size-3.5" /> Daftar isi
                        @if ($kurangIsi > 0)
                            <span class="rounded-full bg-gold-100 px-1.5 text-[10px] font-bold text-gold-800" title="Isian yang belum lengkap">{{ $kurangIsi }}</span>
                        @endif
                    </button>
                    <nav id="daftar-isi-pratinjau" x-cloak x-show="isiTerbuka" x-transition.origin.top.left aria-label="Daftar isi" data-testid="daftar-isi-pratinjau"
                         class="absolute left-0 z-30 mt-2 max-h-[60vh] w-72 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl bg-white p-2 text-sm shadow-lg ring-1 ring-stone-200">
                        <ol class="space-y-0.5">
                            @foreach ($isi as $butir)
                                @if ($butir['kunci'] === 'kak-1')
                                    <li class="px-2 pt-2 pb-0.5 text-[11px] font-semibold tracking-wide text-stone-500 uppercase" aria-hidden="true">KAK/TOR</li>
                                @endif
                                <li>
                                    <a href="#{{ $butir['jangkar'] }}" x-on:click="isiTerbuka = false" data-isi="{{ $butir['kunci'] }}" data-kurang="{{ $butir['kurang'] }}"
                                       x-bind:aria-current="bagianAktif === '{{ $butir['kunci'] }}' ? 'location' : null"
                                       x-bind:class="{ 'bg-brand-50 text-brand-800': bagianAktif === '{{ $butir['kunci'] }}', 'text-stone-700 hover:bg-stone-50': bagianAktif !== '{{ $butir['kunci'] }}' }"
                                       @class(['flex items-center gap-2 rounded-md py-1.5 pr-2', 'pl-5' => $butir['lembar'] === 'kak', 'pl-2 font-medium' => $butir['lembar'] !== 'kak'])>
                                        <span class="min-w-0 flex-1 truncate">{{ $butir['label'] }}</span>
                                        @if ($butir['kurang'] > 0)
                                            <span class="shrink-0 text-[11px] font-semibold text-gold-700">{{ $butir['kurang'] }} kurang</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>
                <span class="rounded-lg bg-white px-2.5 py-1 text-xs text-stone-600 tabular-nums ring-1 ring-stone-200" x-cloak x-show="cetak" aria-live="polite" data-testid="penunjuk-halaman">
                    Halaman <span class="font-semibold text-stone-900" x-text="halamanAktif" data-testid="halaman-aktif"></span> dari <span x-text="totalHalaman" data-testid="halaman-total"></span>
                </span>
                {{-- Isian kosong: jelajahi penanda satu per satu (PenandaKosong, urut lembar). --}}
                @if ($ringkasanKosong['jumlah'] > 0)
                    <div class="inline-flex items-center rounded-lg bg-white text-xs ring-1 ring-stone-200" role="group" aria-label="Isian kosong" data-testid="navigasi-kosong" data-jumlah="{{ $ringkasanKosong['jumlah'] }}" data-wajib="{{ $ringkasanKosong['wajib'] }}">
                        <button type="button" x-on:click="keKosong(-1)" class="rounded-l-lg px-1.5 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900" data-testid="kosong-sebelumnya">
                            <x-ui.icon name="chevron-right" class="size-3.5 rotate-180" /><span class="sr-only">Isian kosong sebelumnya</span>
                        </button>
                        <span class="px-1 text-stone-600 tabular-nums" aria-live="polite">
                            <span class="font-semibold text-gold-800" x-text="kosongKe ? kosongKe + '/' : ''"></span>{{ $ringkasanKosong['jumlah'] }} kosong @if ($ringkasanKosong['wajib'] > 0)<span class="font-semibold text-red-700">· {{ $ringkasanKosong['wajib'] }} wajib</span>@endif
                        </span>
                        <button type="button" x-on:click="keKosong(1)" class="rounded-r-lg px-1.5 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900" data-testid="kosong-berikutnya">
                            <x-ui.icon name="chevron-right" class="size-3.5" /><span class="sr-only">Isian kosong berikutnya</span>
                        </button>
                    </div>
                @else
                    <span class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-brand-800 ring-1 ring-stone-200" data-testid="navigasi-kosong" data-jumlah="0"><x-ui.icon name="check-circle" class="size-3.5" /> Semua terisi</span>
                @endif
                <div class="relative" x-data="{ buka: false }" x-on:click.outside="buka = false" x-on:keydown.escape="buka = false">
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-stone-700 tabular-nums ring-1 ring-stone-200 hover:text-stone-900" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-controls="pilihan-perbesaran" data-testid="perbesaran" x-bind:data-perbesaran="perbesaran">
                        <x-ui.icon name="search" class="size-3.5" /><span class="sr-only">Perbesaran: </span><span x-text="cetak ? persen + '%' : 'Lebar layar'">{{ PenampilDokumen::PERBESARAN[PenampilDokumen::BAWAAN] }}</span>
                        <x-ui.icon name="chevron-down" class="size-3 opacity-60" />
                    </button>
                    <div id="pilihan-perbesaran" x-cloak x-show="buka" x-transition.origin.top.right role="group" aria-label="Perbesaran" data-testid="pilihan-perbesaran"
                         class="absolute right-0 z-30 mt-2 w-44 rounded-xl bg-white p-1.5 text-sm shadow-lg ring-1 ring-stone-200">
                        @foreach (PenampilDokumen::PERBESARAN as $kunciPerbesaran => $labelPerbesaran)
                            <button type="button" x-on:click="perbesaran = '{{ $kunciPerbesaran }}'; buka = false" x-bind:aria-pressed="perbesaran === '{{ $kunciPerbesaran }}' ? 'true' : 'false'" data-pilihan-perbesaran="{{ $kunciPerbesaran }}"
                                    x-bind:class="{ 'bg-brand-50 font-semibold text-brand-800': perbesaran === '{{ $kunciPerbesaran }}', 'text-stone-700 hover:bg-stone-50': perbesaran !== '{{ $kunciPerbesaran }}' }"
                                    class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left">
                                {{ $labelPerbesaran }}
                                @if ($kunciPerbesaran === 'pas')
                                    <span class="text-[11px] font-normal text-stone-400">selebar ruang</span>
                                @elseif ($kunciPerbesaran === 'layar')
                                    <span class="text-[11px] font-normal text-stone-400">tanpa lembar</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="space-y-6 overflow-x-auto p-3 sm:p-6" x-ref="ruang" data-testid="ruang-lembar">
                @if ($sunting !== null)
                    <div class="flex items-start gap-2 rounded-xl bg-sky-50 px-3 py-2 text-sm text-sky-900 ring-1 ring-sky-200" data-testid="petunjuk-sunting">
                        <x-ui.icon name="pencil" class="mt-0.5 size-4 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p>Mode sunting — pilih <span class="font-semibold">Sunting</span> pada teks berbingkai biru di KAK/TOR untuk memperbaiki kalimatnya; perubahan langsung tersimpan di data kegiatan. Angka, tanggal, kode, dan jabatan tetap disusun dari isiannya.</p>
                            @if ($terakhirDisimpan)
                                <p class="mt-1 text-xs text-sky-800" data-testid="terakhir-disimpan">Terakhir disimpan: {{ $terakhirDisimpan['label'] }} · pukul {{ $terakhirDisimpan['pada'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
                @foreach (['cover' => $statusDraf, 'kak' => $kak->statusDraf, 'rab' => $rab->statusDraf] as $lembar => $drafLembar)
                    <section id="dok-{{ $lembar }}" class="scroll-mt-36" aria-label="{{ $dokumen[$lembar] }}{{ $lembar === 'rab' && $sumberAktif ? ' '.$sumberAktif : '' }}" wire:key="{{ $lembar }}-{{ $sumberAktif }}">
                        <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">
                            {{ $dokumen[$lembar] }}{{ $lembar === 'rab' && $sumberAktif ? ' '.$sumberAktif : '' }}
                            <x-dokumen.kepala-draf :status="$drafLembar" /> <x-dokumen.kepala-tahun :ringkasan="$kepalaTahun($lembar)" :tahun="$tahunDokumen" />
                            <span class="font-normal tracking-normal text-stone-400 normal-case" x-cloak x-show="cetak" x-text="(halaman['{{ $lembar }}'] ?? 1) + ' halaman A4' + ((halaman['{{ $lembar }}'] ?? 1) > 1 ? ' · garis putus-putus = perkiraan batas halaman' : '')"></span>
                        </p>
                        @if ($lembar === 'kak' && $kak->peringatanPagu)
                            <x-rab.peringatan-pagu :sumber="$kak->sumber" :hasil="$kak->peringatanPagu->hasil" ringkas class="mx-auto mb-3 max-w-[210mm]" />
                        @endif
                        <div class="relative mx-auto shadow-md" x-bind:class="{ 'lembar-cetak': cetak, 'max-w-[210mm]': ! cetak }" x-bind:style="gayaLembar()" data-lembar="{{ $lembar }}">
                            @switch($lembar)
                                @case('cover')
                                    <x-dokumen.cover :gambar="$cover['gambar']" :teks="$cover['teks']" :nilai="$cover['nilai']" :draf="$statusDraf->draf" />
                                    @break
                                @case('kak')
                                    <x-dokumen.kak-tor :susunan="$kak" :bagian="range(1, 9)" :sunting="$sunting" :penanda="$penanda" />
                                    @break
                                @default
                                    <x-dokumen.rab :susunan="$rab" />
                            @endswitch
                            {{-- Perkiraan batas halaman A4 (dihitung klien dari tinggi lembar). --}}
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true" wire:ignore x-show="cetak">
                                <template x-for="g in garis('{{ $lembar }}')" :key="g.ke">
                                    <div class="absolute inset-x-0 border-t-2 border-dashed border-sky-400/70" x-bind:style="{ top: g.atas }" data-batas-halaman>
                                        <span class="absolute left-2 -translate-y-1/2 rounded-full bg-sky-600 px-2 py-0.5 font-sans text-[10px] font-semibold text-white" x-text="'Halaman ' + g.ke"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>
