@use('App\Support\Dokumen\DokumenKegiatan')
@use('App\Support\Dokumen\DrafDokumen')
@use('App\Support\Dokumen\StatusCetak')
@use('App\Support\Dokumen\IsianSusun')
@use('App\Support\Kegiatan\TeksKhusus')
@use('App\Livewire\Forms\ItemBelanjaForm')
@use('Illuminate\Support\Carbon')

@php
    $kak = $this->kak;
    $rab = $this->rab;
    $susunan = $this->susunan;
    $statusCetak = $this->statusCetak;
    $drafAktif = $this->drafAktif();
    $keadaanDraf = $this->keadaanDraf;
    $drafLain = collect($this->drafSemua)->reject(fn (array $d) => $d['id'] === ($drafAktif['id'] ?? null))->values();
    $sumberAktif = $kak->sumber;
    $isi = $this->isi();
    $adaKak = in_array('kak', $isi, true);
    $adaRab = in_array('rab', $isi, true);
    $adaCover = in_array('cover', $isi, true);
    $statusDraf = $this->statusDraf();
    $pemeriksaan = $this->pemeriksaan;
    $template = $this->template();
    $jangkarPratinjau = ['cover' => '#dok-cover', 'kak' => '#dok-kak', 'rab' => '#dok-rab'][$isi[0]];
    $labelBagianTemplate = ['kak' => 'KAK/TOR', 'rab' => 'RAB'];
    $bisaUbah = $this->dapatDiubah();
    // Tampilan dokumen utuh: daftar bagian melompat ke jangkar di dokumen (#cetak-kak-bagian-n), selain itu ke kartunya.
    $dokumen = $tampilan === 'dokumen';
    $ke = ['cover' => $dokumen ? '#hasil-cover' : '#susun-cover', 'rab' => $dokumen ? '#hasil-rab' : '#susun-rab'];
    $keBagian = fn (int $n) => ($dokumen ? '#cetak-kak-bagian-' : '#susun-bagian-').$n;
    // Jangkar bagian yang berubah sejak draf (kunci DrafDokumen::keadaan): cover, kak-{n}, rab.
    $keBerubah = fn (string $kunci) => str_starts_with($kunci, 'kak-') ? $keBagian((int) substr($kunci, 4)) : $ke[$kunci];
    // Cara menghilangkan cap DRAF (StatusCetak::SARAN) per alasan StatusDraf.
    $bagianKurang = collect($adaKak ? $susunan->bagian : [])->firstWhere('status', 'kurang')['nomor'] ?? null;
    $tautanSaran = [
        'status' => route('kegiatan.edit', ['kegiatan' => $kegiatanId]),
        'kurang' => $bagianKurang ? $keBagian($bagianKurang) : ($adaRab && $susunan->kurangRab !== [] ? $ke['rab'] : null),
        'pagu' => route('kegiatan.rab', array_filter(['kegiatan' => $kegiatanId, 'sumber' => $sumberAktif])),
    ];
    $nadaDraf = [
        'belum' => 'bg-stone-100 text-stone-600 ring-stone-200',
        'sesuai' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'berubah' => 'bg-gold-50 text-gold-700 ring-gold-200',
    ];
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
    (<x-dokumen.kak-tor :bagian="[n]" :cap="false">). Jenis dokumen (<x-dokumen.pilih-jenis>) menentukan bagian yang tampil:
    cover, KAK/TOR, dan/atau RAB. Samping: kemajuan + daftar bagian, berkas cetak, draf dokumen, master template.
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
            <a href="{{ route('kegiatan.pratinjau', ['kegiatan' => $kegiatanId] + ($sumberAktif ? ['sumber' => $sumberAktif] : [])).$jangkarPratinjau }}" wire:navigate class="btn-secondary" data-testid="buka-pratinjau">
                <x-ui.icon name="eye" class="size-4" /> Pratinjau dokumen
            </a>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $nadaDraf[$keadaanDraf['keadaan']] }}" data-testid="status-draf-dokumen" data-keadaan="{{ $keadaanDraf['keadaan'] }}">{{ $keadaanDraf['label'] }}</span>
            @if ($bisaUbah)
                <button type="button" class="btn-primary" wire:click="simpanDraf" wire:loading.attr="disabled" wire:target="simpanDraf" data-testid="simpan-draf-dokumen">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="simpanDraf">{{ $drafAktif ? 'Perbarui draf' : 'Simpan draf' }}</span><span wire:loading wire:target="simpanDraf">Menyimpan…</span>
                </button>
            @endif
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
            @if ($adaKak)
                <span class="text-sm text-stone-600" data-testid="ringkasan-susun"><span class="font-semibold text-stone-900">{{ $susunan->lengkap }}</span> dari {{ $susunan->jumlah() }} bagian lengkap</span>
            @else
                <span class="text-sm text-stone-600" data-testid="ringkasan-susun">RAB · <span class="font-semibold text-stone-900">{{ $rab->anggaran?->jumlahItem() ?? 0 }}</span> baris rincian</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Tampilan" data-testid="tampilan-susun">
                @foreach (\App\Livewire\Kegiatan\SusunDokumen::TAMPILAN as $kunciTampilan => $labelTampilan)
                    <button type="button" wire:click="$set('tampilan', '{{ $kunciTampilan }}')" aria-pressed="{{ $tampilan === $kunciTampilan ? 'true' : 'false' }}" data-tampilan="{{ $kunciTampilan }}"
                            @class(['inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition', 'bg-white text-brand-800 shadow-xs' => $tampilan === $kunciTampilan, 'text-stone-600 hover:text-stone-900' => $tampilan !== $kunciTampilan])>
                        <x-ui.icon :name="$kunciTampilan === 'dokumen' ? 'file-text' : 'layers'" class="size-3.5" /> {{ $labelTampilan }}
                    </button>
                @endforeach
            </div>
            <a href="#berkas-jenis" @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1', 'bg-red-50 text-red-700 ring-red-200 hover:bg-red-100' => $statusDraf->draf, 'bg-brand-50 text-brand-800 ring-brand-200 hover:bg-brand-100' => ! $statusDraf->draf]) data-testid="keadaan-susun" data-draf="{{ $statusDraf->draf ? 'ya' : 'tidak' }}">
                <x-ui.icon :name="$statusDraf->draf ? 'alert' : 'check-circle'" class="size-3.5" />
                {{ $statusDraf->draf ? 'Tercetak sebagai DRAF' : 'Siap dicetak' }}@if ($statusDraf->draf) <span class="font-normal">· {{ count($statusDraf->alasan) }} alasan</span>@endif
            </a>
        </div>
    </div>

    <x-dokumen.pilih-jenis class="mt-4" :pilihan="$this->pilihanJenis" :terpilih="$jenis" />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            {{-- Daftar bagian di layar sempit (di layar lebar ada di panel samping). --}}
            <nav class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 lg:hidden" aria-label="Bagian dokumen">
                @if ($adaCover)
                    <a href="{{ $ke['cover'] }}" class="flex shrink-0 items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200">Cover</a>
                @endif
                @foreach ($adaKak ? $susunan->bagian : [] as $b)
                    <a href="{{ $keBagian($b['nomor']) }}" class="flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200">
                        <span class="size-1.5 rounded-full {{ $nadaStatus[$b['status']]['titik'] }}" aria-hidden="true"></span>{{ $b['nomor'] }}. {{ $b['judul'] }}
                    </a>
                @endforeach
                @if ($adaRab)
                    <a href="{{ $ke['rab'] }}" class="flex shrink-0 items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200">RAB</a>
                @endif
            </nav>

            @if ($dokumen)
                {{-- Dokumen utuh hasil susunan: bagian jenis terpilih berurutan seperti dicetak. --}}
                <div class="space-y-6 rounded-2xl bg-stone-100 p-3 ring-1 ring-stone-200 sm:p-6" data-testid="hasil-susunan" wire:key="hasil-{{ $jenis }}-{{ $sumberAktif }}">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs text-stone-600">
                        <p>Hasil susunan <span class="font-semibold text-stone-800">{{ $pemeriksaan['label_jenis'] }}</span> dari data terbaru, seperti tercetak.</p>
                        <p class="flex flex-wrap items-center gap-3" aria-label="Keterangan penanda">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-gold-100 ring-1 ring-gold-300" aria-hidden="true"></span>Isian belum lengkap</span>
                            @if ($statusDraf->draf)
                                <span class="inline-flex items-center gap-1.5"><span class="font-sans text-[10px] font-bold tracking-widest text-red-700/70" aria-hidden="true">DRAF</span>Cap selama belum siap</span>
                            @endif
                        </p>
                    </div>
                    @if ($adaCover)
                        <section id="hasil-cover" class="scroll-mt-36" aria-label="Cover" data-testid="hasil-cover">
                            <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">Cover <x-dokumen.kepala-draf :status="$statusCetak->set" /></p>
                            <x-dokumen.cover :gambar="$this->cover['gambar']" :teks="$this->cover['teks']" :nilai="$this->cover['nilai']" :draf="$statusCetak->set->draf" :jangkar="false" class="max-w-[210mm]" />
                        </section>
                    @endif
                    @if ($adaKak)
                        <section id="hasil-kak" class="scroll-mt-36" aria-label="KAK/TOR" data-testid="hasil-kak">
                            <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">KAK/TOR <x-dokumen.kepala-draf :status="$kak->statusDraf" /></p>
                            @if ($kak->peringatanPagu)
                                <x-rab.peringatan-pagu :sumber="$kak->sumber" :hasil="$kak->peringatanPagu->hasil" ringkas class="mx-auto mb-3 max-w-[210mm]" />
                            @endif
                            {{-- Set lengkap memakai cover terpisah; KAK/TOR saja menyertakan halaman judulnya. --}}
                            <x-dokumen.kak-tor :susunan="$kak" :bagian="$adaCover ? range(1, 9) : null" />
                        </section>
                    @endif
                    @if ($adaRab)
                        <section id="hasil-rab" class="scroll-mt-36" aria-label="RAB" data-testid="hasil-rab">
                            <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">RAB{{ $sumberAktif ? ' '.$sumberAktif : '' }} <x-dokumen.kepala-draf :status="$rab->statusDraf" /></p>
                            <x-dokumen.rab :susunan="$rab" />
                        </section>
                    @endif
                </div>
            @else

            @if ($adaCover)
                {{-- Cover kegiatan (set lengkap): desain cover tahun aktif berisi data kegiatan. --}}
                <section id="susun-cover" class="scroll-mt-24 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="susun-cover" aria-labelledby="judul-susun-cover" wire:key="susun-cover-{{ $sumberAktif }}">
                    <header class="px-4 py-3 sm:px-5">
                        <h2 id="judul-susun-cover" class="text-sm font-semibold text-stone-900">Cover</h2>
                        <p class="mt-1 text-xs text-stone-500">Desain cover TA {{ $tahun }} diatur admin; kotak teksnya diisi nama kegiatan, sumber dana, dan tahun anggaran.</p>
                    </header>
                    <div class="rounded-b-2xl border-t border-stone-100 bg-stone-50 p-2 sm:p-4">
                        <x-dokumen.cover :gambar="$this->cover['gambar']" :teks="$this->cover['teks']" :nilai="$this->cover['nilai']" :jangkar="false" class="mx-auto max-w-[24rem]" />
                    </div>
                </section>
            @endif

            @foreach ($adaKak ? $susunan->bagian : [] as $b)
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
                                @if (collect(TeksKhusus::untukBagian($b['nomor']))->contains(fn ($k) => TeksKhusus::disesuaikan($k, $this->dataKegiatan)))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-200 ring-inset" data-testid="bagian-disesuaikan"><x-ui.icon name="pencil" class="size-3" /> Teks disesuaikan</span>
                                @endif
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
                        @if ($bisaUbah && IsianSusun::untuk($b['nomor']) !== [] && $isianTerbuka !== $b['nomor'])
                            <button type="button" class="btn-secondary shrink-0 px-2.5 py-1 text-xs" wire:click="bukaIsian({{ $b['nomor'] }})" x-on:click="buka = true" data-testid="lengkapi-{{ $b['nomor'] }}">
                                <x-ui.icon name="pencil" class="size-3.5" /> <span class="hidden sm:inline">Lengkapi di sini</span><span class="sm:hidden">Isi</span>
                            </button>
                        @endif
                        <button type="button" class="btn-ghost -mr-2 shrink-0 px-2" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-controls="isi-susun-{{ $b['nomor'] }}">
                            <x-ui.icon name="chevron-down" class="size-4 transition" x-bind:class="{ 'rotate-180': buka }" />
                            <span class="sr-only">Tampilkan atau sembunyikan bagian {{ $b['nomor'] }}</span>
                        </button>
                    </header>
                    <div id="isi-susun-{{ $b['nomor'] }}" x-show="buka">
                        @if ($isianTerbuka === $b['nomor'])
                            {{-- Isian bagian ini, disimpan lewat UbahDataKegiatan (aturan sama dengan Data Kegiatan). --}}
                            <form wire:submit="simpanIsian" x-on:keydown.escape.prevent="$wire.tutupIsian()" class="mx-4 mb-3 space-y-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 sm:mx-5 sm:p-4" data-testid="isian-bagian-{{ $b['nomor'] }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach (IsianSusun::untuk($b['nomor']) as $kolom)
                                        @php [$label, $jenisInput, $maks, $contoh] = IsianSusun::KOLOM[$kolom]; @endphp
                                        <x-ui.field :label="$label" for="isian-{{ $kolom }}" :error="'isian.'.$kolom" :class="$jenisInput === 'uraian' ? 'sm:col-span-2' : ''">
                                            @if ($jenisInput === 'uraian')
                                                <textarea id="isian-{{ $kolom }}" wire:model="isian.{{ $kolom }}" rows="3" maxlength="{{ $maks }}" placeholder="{{ $contoh }}" class="form-input text-sm" @error('isian.'.$kolom) aria-invalid="true" @enderror></textarea>
                                            @elseif ($jenisInput === 'tanggal')
                                                <input type="date" id="isian-{{ $kolom }}" wire:model="isian.{{ $kolom }}" class="form-input text-sm" @error('isian.'.$kolom) aria-invalid="true" @enderror>
                                            @elseif ($jenisInput === 'angka')
                                                <input type="number" id="isian-{{ $kolom }}" wire:model="isian.{{ $kolom }}" min="1" max="{{ $maks }}" inputmode="numeric" placeholder="{{ $contoh }}" class="form-input text-sm" @error('isian.'.$kolom) aria-invalid="true" @enderror>
                                            @else
                                                <input type="text" id="isian-{{ $kolom }}" wire:model="isian.{{ $kolom }}" maxlength="{{ $maks }}" placeholder="{{ $contoh }}" class="form-input text-sm" @error('isian.'.$kolom) aria-invalid="true" @enderror>
                                            @endif
                                        </x-ui.field>
                                    @endforeach
                                </div>
                                @error('isian')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" class="btn-ghost text-sm" wire:click="tutupIsian">Batal</button>
                                    <button type="submit" class="btn-primary text-sm" wire:loading.attr="disabled" wire:target="simpanIsian" data-testid="simpan-isian">
                                        <span wire:loading.remove wire:target="simpanIsian">Simpan isian</span><span wire:loading wire:target="simpanIsian">Menyimpan…</span>
                                    </button>
                                </div>
                                <p class="text-xs text-stone-500">Tersimpan ke Data Kegiatan; isian lain tetap di <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="font-medium text-brand-700 hover:text-brand-800">halaman Data Kegiatan</a>.</p>
                            </form>
                        @endif
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
                        @if (TeksKhusus::untukBagian($b['nomor']) !== [])
                            {{-- Teks baku yang bisa disesuaikan untuk kegiatan ini (TeksKhusus); angka, tanggal & jabatan tetap dari isian. --}}
                            <div class="mx-4 mb-3 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200 sm:mx-5" data-testid="teks-khusus-{{ $b['nomor'] }}">
                                @foreach (TeksKhusus::untukBagian($b['nomor']) as $kunciTeks)
                                    @php
                                        [, $labelTeks, $jenisTeks] = TeksKhusus::BIDANG[$kunciTeks];
                                        $sesuai = TeksKhusus::disesuaikan($kunciTeks, $this->dataKegiatan);
                                        $baku = TeksKhusus::bakuMentah($kunciTeks);
                                    @endphp
                                    <div class="px-3 py-2.5" data-teks="{{ $kunciTeks }}" data-disesuaikan="{{ $sesuai ? 'ya' : 'tidak' }}" wire:key="teks-{{ $kunciTeks }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="font-semibold text-stone-800">{{ $labelTeks }}</span>
                                                <span @class(['rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset', 'bg-indigo-50 text-indigo-700 ring-indigo-200' => $sesuai, 'bg-stone-100 text-stone-600 ring-stone-200' => ! $sesuai]) data-testid="status-teks">{{ $sesuai ? 'Disesuaikan' : 'Teks baku' }}</span>
                                            </p>
                                            @if ($bisaUbah && $teksTerbuka !== $kunciTeks)
                                                <span class="flex flex-wrap items-center gap-1">
                                                    @if ($sesuai)
                                                        <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="kembalikanTeks('{{ $kunciTeks }}')" data-testid="kembalikan-teks-{{ $kunciTeks }}">Kembalikan ke teks baku</button>
                                                    @endif
                                                    <button type="button" class="btn-secondary px-2.5 py-1 text-xs" wire:click="bukaTeks('{{ $kunciTeks }}')" x-on:click="buka = true" data-testid="sesuaikan-teks-{{ $kunciTeks }}"><x-ui.icon name="pencil" class="size-3.5" /> Sesuaikan</button>
                                                </span>
                                            @endif
                                        </div>
                                        @if ($teksTerbuka === $kunciTeks)
                                            <form wire:submit="simpanTeks" x-on:keydown.escape.prevent="$wire.tutupTeks()" class="mt-2 space-y-2" data-testid="penyunting-teks-{{ $kunciTeks }}">
                                                <label for="teks-{{ $kunciTeks }}" class="sr-only">{{ $labelTeks }}</label>
                                                <textarea id="teks-{{ $kunciTeks }}" wire:model="teksIsian" rows="{{ $jenisTeks === 'daftar' ? 5 : 3 }}" class="form-input text-sm" @error('teksIsian') aria-invalid="true" aria-describedby="teks-{{ $kunciTeks }}-galat" @enderror></textarea>
                                                @error('teksIsian')<p id="teks-{{ $kunciTeks }}-galat" class="form-error" role="alert">{{ $message }}</p>@enderror
                                                <p class="text-xs text-stone-500">{{ $jenisTeks === 'daftar' ? 'Satu butir per baris (maks. '.TeksKhusus::MAKS_BUTIR.' butir). ' : '' }}Tulis <code class="rounded bg-stone-100 px-1">{{ TeksKhusus::PENANDA }}</code> agar diisi nama kegiatan saat dicetak. Sama dengan teks baku = memakai teks baku.</p>
                                                <details class="text-xs text-stone-500">
                                                    <summary class="cursor-pointer font-medium text-stone-600">Lihat teks baku</summary>
                                                    <div class="mt-1 rounded-lg bg-stone-50 p-2 whitespace-pre-line text-stone-700" data-testid="teks-baku-{{ $kunciTeks }}">{{ is_array($baku) ? implode("\n", $baku) : $baku }}</div>
                                                </details>
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <button type="button" class="btn-ghost text-sm" wire:click="tutupTeks">Batal</button>
                                                    <button type="submit" class="btn-primary text-sm" wire:loading.attr="disabled" wire:target="simpanTeks" data-testid="simpan-teks">
                                                        <span wire:loading.remove wire:target="simpanTeks">Simpan teks</span><span wire:loading wire:target="simpanTeks">Menyimpan…</span>
                                                    </button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="rounded-b-2xl border-t border-stone-100 bg-stone-50 p-2 sm:p-4">
                            <x-dokumen.kak-tor :susunan="$kak" :bagian="[$b['nomor']]" :cap="false" />
                        </div>
                    </div>
                </section>
            @endforeach

            {{-- RAB otomatis: kepala dokumen RAB sumber dana ini. --}}
            @if ($adaRab)
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

                    {{-- Rincian belanja (ringkas) + tambah rincian cepat. --}}
                    <div class="border-t border-stone-100 px-4 py-3 sm:px-5">
                        <h3 class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Rincian belanja</h3>
                        @if (($rab->anggaran?->jumlahItem() ?? 0) === 0)
                            <p class="mt-1.5 text-sm text-stone-500">Belum ada rincian — tambahkan minimal satu baris agar RAB tersusun.</p>
                        @else
                            <ol class="mt-1.5 divide-y divide-stone-100 text-sm" data-testid="daftar-rincian-susun">
                                @foreach ($rab->anggaran->hasil->rincian as $i => $item)
                                    <li class="flex items-baseline justify-between gap-3 py-1.5" wire:key="rincian-{{ $item['id'] ?? $i }}">
                                        <span class="min-w-0">
                                            <span class="text-stone-800">{{ $item['uraian'] }}</span>
                                            <span class="block text-xs text-stone-500">{{ \App\Support\Format::angka($item['volume']) }} {{ $item['satuan'] }} × <x-ui.uang :nilai="$item['harga_satuan']" /> · {{ ItemBelanjaForm::LABEL_KATEGORI[$item['kategori']] ?? $item['kategori'] }}</span>
                                        </span>
                                        <span class="shrink-0 font-medium text-stone-900 tabular-nums"><x-ui.uang :nilai="$item['jumlah']" /></span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if ($bisaUbah)
                            <form wire:submit="tambahRincian" class="mt-3 grid gap-2 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 sm:grid-cols-6" data-testid="tambah-rincian-susun">
                                <x-ui.field label="Uraian" for="rincian-uraian" error="rincianBaru.uraian" class="sm:col-span-6">
                                    <input type="text" id="rincian-uraian" wire:model="rincianBaru.uraian" maxlength="255" placeholder="mis. Konsumsi rapat koordinasi" class="form-input text-sm">
                                </x-ui.field>
                                <x-ui.field label="Volume" for="rincian-volume" error="rincianBaru.volume" class="sm:col-span-1">
                                    <input type="text" id="rincian-volume" wire:model="rincianBaru.volume" inputmode="decimal" placeholder="mis. 20" class="form-input text-sm">
                                </x-ui.field>
                                <x-ui.field label="Satuan" for="rincian-satuan" error="rincianBaru.satuan" class="sm:col-span-2">
                                    <input type="text" id="rincian-satuan" wire:model="rincianBaru.satuan" list="satuan-susun" maxlength="30" placeholder="mis. kotak" class="form-input text-sm">
                                    <datalist id="satuan-susun">
                                        @foreach (ItemBelanjaForm::SATUAN as $satuan)
                                            <option value="{{ $satuan }}"></option>
                                        @endforeach
                                    </datalist>
                                </x-ui.field>
                                <x-ui.field label="Harga satuan" for="rincian-harga" error="rincianBaru.harga_satuan" class="sm:col-span-3">
                                    <x-ui.rupiah id="rincian-harga" model="rincianBaru.harga_satuan" class="text-sm" />
                                </x-ui.field>
                                <x-ui.field label="Kelompok belanja" for="rincian-kategori" error="rincianBaru.kategori" class="sm:col-span-3">
                                    <select id="rincian-kategori" wire:model="rincianBaru.kategori" class="form-input text-sm">
                                        <option value="">Tebak dari uraian</option>
                                        @foreach (ItemBelanjaForm::LABEL_KATEGORI as $kunci => $label)
                                            <option value="{{ $kunci }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-ui.field>
                                <div class="flex items-end justify-end sm:col-span-3">
                                    <button type="submit" class="btn-primary w-full justify-center text-sm sm:w-auto" wire:loading.attr="disabled" wire:target="tambahRincian" data-testid="simpan-rincian-susun">
                                        <x-ui.icon name="plus" class="size-4" /> Tambah ke RAB {{ $sumberAktif }}
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif
            </section>
            @endif
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            {{-- Kemajuan & daftar bagian --}}
            @if ($adaKak)
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
                            <a href="{{ $keBagian($b['nomor']) }}" class="flex items-center gap-2 rounded-md px-2 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
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
                    @if ($adaRab)
                    <li>
                        <a href="{{ $ke['rab'] }}" class="flex items-center gap-2 rounded-md px-2 py-1 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                            <span @class(['size-1.5 shrink-0 rounded-full', 'bg-gold-500' => $susunan->kurangRab !== [], 'bg-brand-600' => $susunan->kurangRab === []]) aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 truncate">RAB {{ $sumberAktif }}</span>
                        </a>
                    </li>
                    @endif
                </ol>
                @unless ($dokumen)
                    <div class="mt-3 flex gap-2 border-t border-stone-100 pt-3">
                        <button type="button" class="btn-ghost flex-1 justify-center text-xs" x-on:click="semua = true" data-testid="buka-semua-bagian">Buka semua</button>
                        <button type="button" class="btn-ghost flex-1 justify-center text-xs" x-on:click="semua = false" data-testid="ciutkan-semua-bagian">Ciutkan semua</button>
                    </div>
                @endunless
            </section>
            @endif

            {{-- Berkas cetak jenis terpilih (PemeriksaanCetak). --}}
            <section id="berkas-jenis" class="scroll-mt-24 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 target:ring-2 target:ring-brand-400" aria-labelledby="judul-berkas-jenis" data-testid="berkas-jenis" data-siap="{{ $pemeriksaan['siap'] ? 'ya' : 'tidak' }}">
                <h2 id="judul-berkas-jenis" class="text-sm font-semibold text-stone-900">Berkas {{ $pemeriksaan['label_jenis'] }}</h2>
                <p class="mt-1.5 break-all font-mono text-xs text-stone-700" data-testid="nama-berkas">{{ $pemeriksaan['nama_berkas'] }}</p>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($pemeriksaan['cek'] as $cek)
                        <li class="flex items-start gap-1.5" data-cek="{{ $cek['kunci'] }}" data-lengkap="{{ $cek['lengkap'] ? 'ya' : 'tidak' }}">
                            <x-ui.icon :name="$cek['lengkap'] ? 'check-circle' : 'circle'" :class="'mt-px size-3.5 shrink-0 '.($cek['lengkap'] ? 'text-brand-600' : 'text-stone-300')" />
                            <span @class(['text-stone-700' => $cek['lengkap'], 'text-stone-500' => ! $cek['lengkap']])>{{ $cek['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($statusDraf->draf)
                    {{-- Alasan cap DRAF jenis terpilih (StatusDraf) + cara menghilangkannya. --}}
                    <div class="mt-3 rounded-lg bg-red-50/60 px-3 py-2 ring-1 ring-red-100" data-testid="alasan-draf-susun">
                        <p class="text-xs font-semibold text-red-800">Bercap DRAF karena:</p>
                        <ul class="mt-1 space-y-1.5 text-xs">
                            @foreach ($statusDraf->alasan as $alasan)
                                <li data-alasan="{{ $alasan['kunci'] }}">
                                    <span class="text-stone-800">{{ $alasan['teks'] }}</span>
                                    @if ($tautanSaran[$alasan['kunci']])
                                        <a href="{{ $tautanSaran[$alasan['kunci']] }}" @if (! str_starts_with($tautanSaran[$alasan['kunci']], '#')) wire:navigate @endif class="block font-medium text-brand-700 hover:text-brand-800">{{ StatusCetak::SARAN[$alasan['kunci']] }}</a>
                                    @else
                                        <span class="block text-stone-500">{{ StatusCetak::SARAN[$alasan['kunci']] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">Tetap bisa dicetak — cap DRAF hilang sendiri setelah lengkap & disetujui.</p>
                @else
                    <p class="mt-2 text-xs text-stone-500">Siap dicetak tanpa cap DRAF.</p>
                @endif
            </section>

            {{-- Draf dokumen jenis × sumber yang tampil (DrafDokumen) + draf lain kegiatan ini. --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-draf-dokumen" data-testid="draf-dokumen" data-ada="{{ $this->drafSemua !== [] ? 'ya' : 'tidak' }}" data-keadaan="{{ $keadaanDraf['keadaan'] }}"
                     x-data="{ hapus: false }"" wire:key="draf-{{ $jenis }}-{{ $sumberAktif }}-{{ $drafAktif['disimpan_pada'] ?? 'belum' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="judul-draf-dokumen" class="text-sm font-semibold text-stone-900">Draf dokumen</h2>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $nadaDraf[$keadaanDraf['keadaan']] }}" data-testid="keadaan-draf">{{ $keadaanDraf['label'] }}</span>
                </div>
                <p class="mt-1.5 text-sm text-stone-700">{{ DrafDokumen::nama($jenis, $sumberAktif) }}</p>
                @if ($drafAktif)
                    <p class="mt-0.5 text-xs text-stone-500" data-testid="waktu-draf">Tersimpan {{ $waktu($drafAktif['disimpan_pada']) }} oleh {{ $drafAktif['oleh'] }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs" data-testid="banding-draf">
                        @foreach (['Saat disimpan' => [$drafAktif['lengkap'], $drafAktif['bertanda_draf'], $drafAktif['status']], 'Sekarang' => [$adaKak ? $susunan->lengkap : null, $statusDraf->draf, $this->status()->value]] as $judulBanding => [$lengkapBanding, $capBanding, $statusBanding])
                            <div class="rounded-lg bg-stone-50 px-2.5 py-2 ring-1 ring-stone-100">
                                <dt class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">{{ $judulBanding }}</dt>
                                @if ($lengkapBanding !== null)
                                    <dd class="mt-0.5 text-stone-800"><span class="font-semibold">{{ $lengkapBanding }}</span>/{{ $susunan->jumlah() }} bagian lengkap</dd>
                                @endif
                                <dd @class(['mt-0.5 font-medium', 'text-red-700' => $capBanding, 'text-brand-800' => ! $capBanding])>{{ $capBanding ? 'Bercap DRAF' : 'Siap dicetak' }}</dd>
                                <dd class="mt-0.5 text-stone-500">Status {{ \App\Enums\StatusKegiatan::tryFrom($statusBanding)?->label() ?? $statusBanding }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($keadaanDraf['berubah'] !== [])
                        <div class="mt-3" data-testid="berubah-sejak-draf">
                            <p class="text-xs font-semibold text-gold-800">Berubah sejak draf disimpan:</p>
                            <ul class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($keadaanDraf['berubah'] as $ubah)
                                    <li><a href="{{ $keBerubah($ubah['kunci']) }}" class="inline-flex rounded-md bg-gold-50 px-1.5 py-0.5 text-xs font-medium text-gold-800 ring-1 ring-gold-200 hover:bg-gold-100" data-berubah="{{ $ubah['kunci'] }}">{{ $ubah['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
                <p class="mt-2 text-xs text-stone-500">{{ $keadaanDraf['keterangan'] }}</p>
                @if ($bisaUbah)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-secondary text-xs" wire:click="simpanDraf" wire:loading.attr="disabled" wire:target="simpanDraf" data-testid="simpan-draf-samping">
                            <x-ui.icon name="save" class="size-3.5" /> {{ $drafAktif ? 'Perbarui draf' : 'Simpan draf' }}
                        </button>
                        @if ($drafAktif)
                            <button type="button" class="btn-ghost text-xs text-red-700" x-show="! hapus" x-on:click="hapus = true" data-testid="hapus-draf">Hapus draf</button>
                        @endif
                    </div>
                    @if ($drafAktif)
                        <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs ring-1 ring-red-200" x-show="hapus" x-cloak x-on:keydown.escape.window="hapus = false" role="alertdialog" aria-labelledby="tanya-hapus-draf" data-testid="konfirmasi-hapus-draf">
                            <p id="tanya-hapus-draf" class="text-red-900">Hapus draf {{ DrafDokumen::nama($jenis, $sumberAktif) }}? Isi dokumen dan data kegiatan tidak berubah.</p>
                            <div class="mt-2 flex justify-end gap-2">
                                <button type="button" class="btn-ghost text-xs" x-on:click="hapus = false">Batal</button>
                                <button type="button" class="btn-danger text-xs" wire:click="hapusDraf" x-on:click="hapus = false" data-testid="ya-hapus-draf">Hapus draf</button>
                            </div>
                        </div>
                    @endif
                @endif
                @if ($drafLain->isNotEmpty())
                    <div class="mt-3 border-t border-stone-100 pt-3" data-testid="draf-lain">
                        <p class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">Draf lain</p>
                        <ul class="mt-1.5 space-y-1.5">
                            @foreach ($drafLain as $lain)
                                <li class="flex items-start justify-between gap-2 text-xs" data-draf="{{ $lain['jenis'] }}|{{ $lain['sumber'] }}">
                                    <span class="min-w-0">
                                        <span class="block font-medium text-stone-800">{{ DrafDokumen::nama($lain['jenis'], $lain['sumber']) }}</span>
                                        <span class="block text-stone-500">{{ $waktu($lain['disimpan_pada']) }} · {{ $lain['oleh'] }}</span>
                                    </span>
                                    <a href="{{ DrafDokumen::tautan($lain) }}" wire:navigate class="shrink-0 font-semibold text-brand-700 hover:text-brand-800" data-testid="lanjutkan-draf">Lanjutkan</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            {{-- Master template KAK/TOR --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-template-susun" data-testid="template-susun" data-ada="{{ array_filter($template) !== [] ? 'ya' : 'tidak' }}">
                <h2 id="judul-template-susun" class="text-sm font-semibold text-stone-900">Master template TA {{ $tahun }}</h2>
                <dl class="mt-1.5 space-y-2">
                    @foreach ($template as $bagianTemplate => $t)
                        <div data-template="{{ $bagianTemplate }}">
                            <dt class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">{{ $labelBagianTemplate[$bagianTemplate] }}</dt>
                            @if ($t)
                                <dd class="text-sm text-stone-700">{{ $t['nama'] }}</dd>
                                <dd class="text-xs text-stone-500">{{ DokumenKegiatan::JENIS[$t['jenis']] ?? $t['jenis'] }} · {{ count($t['variabel']) }} variabel · diperbarui {{ $waktu($t['diperbarui_pada']) }}</dd>
                            @else
                                <dd class="text-sm text-stone-600">Belum ada master {{ $labelBagianTemplate[$bagianTemplate] }} — dokumen memakai susunan baku aplikasi.</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
                @if ($admin)
                    <a href="{{ route('pengaturan.template.index', ['tab' => 'kak']) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">Atur master KAK/TOR <x-ui.icon name="chevron-right" class="size-3" /></a>
                @else
                    <p class="mt-2 text-xs text-stone-500">Master template diatur admin.</p>
                @endif
            </section>
        </aside>
    </div>
</div>
