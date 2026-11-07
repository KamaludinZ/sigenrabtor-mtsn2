@use('App\Support\Master\JenisKode')

@php
    $ta = $this->tahunDilihat();
    $tahunAktif = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $tersedia = $this->tersedia();
    $jumlah = $this->jumlah;
    $semuaBaris = $jenis === 'rencana_aksi' ? $this->rencanaAksi : $this->baris;
    $baris = $this->barisTampil();
    $pakai = $this->pemakaian;
    $info = JenisKode::DAFTAR[$jenis];
    $relasi = in_array($jenis, ['sk', 'iksk'], true) ? $this->relasiSk() : null;
    $komponen = $jenis === 'edm' ? $this->komponenEdm() : [];
    $dipakaiTotal = count(array_filter($semuaBaris, fn ($b) => ($pakai[(string) $b[$info['kunci']]] ?? 0) > 0));
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Master Kode"
                         deskripsi="Kode SK, IKSK, RKTM, EDM, 5 Budaya Mutu, dan Rencana Aksi bertag per tahun anggaran — diunggah dari Excel lalu dipilih leading di Kode & Klasifikasi, tidak diketik bebas.">
        <x-slot:aksi>
            {{-- Template Excel: kosong atau berisi master tahun yang dilihat (unggah ulang tanpa perubahan = semua "tetap"). --}}
            <div class="relative" x-data="{ buka: false }" x-on:keydown.escape="buka = false; $refs.tombol.focus()" x-on:click.outside="buka = false">
                <button type="button" x-ref="tombol" x-on:click="buka = ! buka" class="btn-ghost" x-bind:aria-expanded="buka" aria-haspopup="menu" data-testid="unduh-template-master">
                    <x-ui.icon name="download" class="size-4" /> Unduh template <x-ui.icon name="chevron-down" class="size-3.5" />
                </button>
                <div x-show="buka" x-cloak x-transition.opacity role="menu" class="absolute right-0 z-20 mt-1 w-72 rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-stone-200" data-testid="menu-template-master">
                    <a href="{{ route('master.template', ['ta' => $ta]) }}" role="menuitem" class="flex items-start gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-stone-50" data-testid="template-kosong" x-on:click="buka = false">
                        <x-ui.icon name="sheet" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                        <span><span class="block font-medium text-stone-900">Template kosong</span><span class="block text-xs text-stone-500">Sheet Perkin (SK, IKSK, Rencana Aksi), SNP, EDM, 5 Budaya Mutu + petunjuk</span></span>
                    </a>
                    @if (($sumberTemplate = \App\Support\Master\SalinMasterKode::sumber($ta)) !== null && $sumberTemplate < $ta)
                        <a href="{{ route('master.template', ['ta' => $ta, 'isi' => 1, 'dari' => $sumberTemplate]) }}" role="menuitem" class="flex items-start gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-stone-50" data-testid="template-dari-sebelumnya" x-on:click="buka = false">
                            <x-ui.icon name="copy" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                            <span><span class="block font-medium text-stone-900">Berisi master TA {{ $sumberTemplate }} (titik awal)</span><span class="block text-xs text-stone-500">Kode berubah tiap tahun: sunting untuk TA {{ $ta }} lalu unggah</span></span>
                        </a>
                    @endif
                    @if ($tersedia)
                        <a href="{{ route('master.template', ['ta' => $ta, 'isi' => 1]) }}" role="menuitem" class="flex items-start gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-stone-50" data-testid="template-berisi" x-on:click="buka = false">
                            <x-ui.icon name="database" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                            <span><span class="block font-medium text-stone-900">Berisi master TA {{ $ta }}</span><span class="block text-xs text-stone-500">Untuk memperbarui master: ubah isinya lalu unggah kembali</span></span>
                        </a>
                        <a href="{{ route('master.template', ['ta' => $ta, 'isi' => 1, 'cakupan' => $jenis]) }}" role="menuitem" class="flex items-start gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-stone-50" data-testid="template-jenis" x-on:click="buka = false">
                            <x-ui.icon name="file-text" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                            <span><span class="block font-medium text-stone-900">Hanya {{ $info['label'] }} TA {{ $ta }}</span><span class="block text-xs text-stone-500">Satu sheet, berisi master jenis ini</span></span>
                        </a>
                    @endif
                </div>
            </div>
            @if ($this->tahunTerkunci())
                <button type="button" class="btn-primary" disabled title="Tahun terkunci — master hanya dilihat" wire:key="unggah-terkunci"><x-ui.icon name="lock" class="size-4" /> Unggah Excel</button>
            @else
                <button type="button" wire:click="bukaUnggah" class="btn-primary" aria-expanded="{{ $unggahTerbuka ? 'true' : 'false' }}" aria-controls="panel-unggah-master" wire:key="unggah-buka"><x-ui.icon name="upload" class="size-4" /> Unggah Excel</button>
            @endif
        </x-slot:aksi>
    </x-pengaturan.kepala>

    {{-- Pemilih tahun: master disimpan per tahun anggaran; dokumen & pilihan kode memakai tahun aktif. --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3" data-testid="pemilih-tahun-master">
        <div class="inline-flex flex-wrap rounded-lg bg-stone-100 p-1" role="group" aria-label="Tahun anggaran">
            @foreach ($this->daftarTahun as $t)
                @php
                    $dipilih = $t['tahun'] === $ta;
                @endphp
                <button type="button" wire:click="$set('tahun', '{{ $t['is_active'] ? '' : $t['tahun'] }}')" aria-pressed="{{ $dipilih ? 'true' : 'false' }}" data-tahun="{{ $t['tahun'] }}"
                        @disabled($unggahTerbuka && ! $dipilih) @if ($unggahTerbuka) title="Tutup panel unggah dulu untuk melihat tahun lain" @endif
                        @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50', 'bg-white text-stone-900 shadow-xs' => $dipilih, 'text-stone-500 hover:text-stone-800' => ! $dipilih])>
                    TA {{ $t['tahun'] }}
                    @if ($t['is_active'])
                        <span class="rounded bg-brand-50 px-1 text-[10px] font-semibold text-brand-800">aktif</span>
                    @elseif ($t['is_locked'])
                        <x-ui.icon name="lock" class="size-3.5 text-stone-400" />
                    @endif
                    @unless ($t['tersedia'])
                        <span class="size-1.5 rounded-full bg-gold-500" title="Master belum diunggah"></span><span class="sr-only">(master belum diunggah)</span>
                    @endunless
                </button>
            @endforeach
        </div>
        <div class="text-right text-xs text-stone-500">
            <p>Pilihan kode di Data Kegiatan memakai master TA {{ $tahunAktif }}.</p>
            @if ($unggahan = $this->unggahanTerakhir())
                <p class="mt-0.5" data-testid="unggahan-terakhir">Terakhir diunggah {{ \Illuminate\Support\Carbon::parse($unggahan['waktu'])->locale('id')->translatedFormat('j M Y, H.i') }} oleh {{ $unggahan['pengguna'] }} — {{ $unggahan['nama_berkas'] }}</p>
            @endif
        </div>
    </div>

    @if ($unggahTerbuka)
        <div class="mt-5" id="panel-unggah-master">
            <livewire:master.unggah-master :tahun="$ta" wire:key="unggah-master-{{ $ta }}" />
        </div>
    @endif

    @if ($this->tahunTerkunci())
        <p class="mt-4 flex items-start gap-2 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700 ring-1 ring-stone-200" data-testid="master-terkunci">
            <x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0" /> Tahun Anggaran {{ $ta }} terkunci — master kodenya hanya bisa dilihat.
        </p>
    @endif

    @if (! $tersedia)
        {{-- Belum ada master: langkah unggah. --}}
        <section class="mt-6 rounded-2xl bg-white px-5 py-10 text-center shadow-xs ring-1 ring-stone-200 sm:px-6" data-testid="master-kode-kosong">
            <x-ui.icon name="database" class="mx-auto size-10 text-stone-300" />
            <h2 class="mt-3 text-base font-semibold text-stone-900">Master kode TA {{ $ta }} belum diunggah</h2>
            <p class="mx-auto mt-1 max-w-xl text-sm text-stone-500">Selama belum ada, leading belum bisa memilih kode RKTM, SK, IKSK, EDM, dan 5 Budaya Mutu untuk kegiatan tahun ini.</p>
            @if (! $this->tahunTerkunci() && ! $unggahTerbuka)
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button type="button" wire:click="bukaUnggah" class="btn-primary"><x-ui.icon name="upload" class="size-4" /> Unggah master TA {{ $ta }}</button>
                    @if ($sumberSalin = $this->sumberSalin())
                        <button type="button" wire:click="salinMaster({{ $sumberSalin }})" class="btn-secondary" wire:loading.attr="disabled" wire:target="salinMaster" data-testid="salin-master"><x-ui.icon name="copy" class="size-4" /> Salin dari TA {{ $sumberSalin }}</button>
                    @endif
                </div>
            @endif
            <ol class="mx-auto mt-5 grid max-w-2xl gap-3 text-left text-sm sm:grid-cols-3">
                @foreach (['Unduh template Excel master kode', 'Isi sheet Perkin (SK, IKSK, Rencana Aksi), SNP, EDM, dan 5 Budaya Mutu', 'Unggah, periksa pratinjau, lalu simpan'] as $i => $langkah)
                    <li class="flex items-start gap-2 rounded-lg bg-stone-50 px-3 py-2.5 ring-1 ring-stone-200">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">{{ $i + 1 }}</span>
                        @if ($i === 0)
                            <a href="{{ route('master.template', ['ta' => $ta]) }}" class="font-medium text-brand-800 underline-offset-2 hover:underline" data-testid="langkah-unduh-template">{{ $langkah }}</a>
                        @else
                            <span class="text-stone-700">{{ $langkah }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @else
        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-jenis-master" data-testid="master-kode">
            {{-- Tab per jenis kode dengan jumlah baris. --}}
            <nav class="flex gap-1 overflow-x-auto border-b border-stone-200 px-3 sm:px-4" role="tablist" aria-label="Jenis master kode">
                @foreach (JenisKode::DAFTAR as $kunci => $j)
                    <button type="button" role="tab" wire:click="$set('jenis', '{{ $kunci }}')" aria-selected="{{ $kunci === $jenis ? 'true' : 'false' }}" data-jenis="{{ $kunci }}"
                            @class(['-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm', 'border-brand-700 font-semibold text-brand-800' => $kunci === $jenis, 'border-transparent font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800' => $kunci !== $jenis])>
                        {{ $j['label'] }}
                        <span @class(['rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums', 'bg-brand-50 text-brand-800' => $kunci === $jenis, 'bg-stone-100 text-stone-600' => $kunci !== $jenis])>{{ $jumlah[$kunci] }}</span>
                    </button>
                @endforeach
            </nav>

            <div role="tabpanel" aria-labelledby="judul-jenis-master" wire:loading.class="opacity-60" wire:target="jenis,tahun">
                <header class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-jenis-master" class="text-base font-semibold text-stone-900">{{ $info['judul'] }}</h2>
                        <p class="mt-0.5 text-sm text-stone-500">{{ $info['asal'] }}</p>
                    </div>
                    <p class="shrink-0 text-xs text-stone-500" data-testid="ringkasan-jenis">{{ count($semuaBaris) }} {{ $jenis === 'rencana_aksi' ? 'rencana aksi' : 'kode' }} · {{ $dipakaiTotal }} dipakai kegiatan TA {{ $ta }}</p>
                </header>

                @if ($semuaBaris !== [])
                    <div class="flex flex-col gap-3 border-t border-stone-100 px-5 pt-4 sm:px-6 lg:flex-row lg:items-start lg:justify-between">
                        <x-ui.kotak-cari model="cari" :nilai="$cari" wire:model.live.debounce.300ms="cari" label="Cari di {{ $info['label'] }}" placeholder="Cari kode atau uraian…"
                                         :jumlah="count($baris)" :total="count($semuaBaris)" :satuan="$jenis === 'rencana_aksi' ? 'rencana aksi' : 'kode'" testid="cari-master" class="w-full lg:max-w-md" />
                        @if ($jenis === 'rencana_aksi')
                            @php
                                $jumlahSaring = $this->jumlahSaringTag();
                            @endphp
                            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Saring tag rencana aksi" data-testid="saring-tag">
                                @foreach (\App\Livewire\Master\Daftar::SARING_TAG as $kunci => $label)
                                    <button type="button" wire:click="$set('saringTag', '{{ $kunci }}')" aria-pressed="{{ $saringTag === $kunci ? 'true' : 'false' }}" data-saring="{{ $kunci }}"
                                            @class(['rounded-full px-2.5 py-1 text-xs font-semibold ring-1', 'bg-stone-900 text-white ring-stone-900' => $saringTag === $kunci, 'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $saringTag !== $kunci])>
                                        {{ $label }} <span class="tabular-nums">{{ $jumlahSaring[$kunci] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($semuaBaris === [])
                    <p class="border-t border-stone-100 px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="jenis-kosong">
                        Belum ada {{ $jenis === 'rencana_aksi' ? 'rencana aksi' : 'kode '.$info['label'] }} di master TA {{ $ta }}.
                        @if ($jenis === 'rencana_aksi')
                            <span class="mt-1 block text-xs">Unggah sheet Perkin berisi kolom Rencana Aksi (template master kode) — SK, IKSK, kode RKTM, unit, anggaran, dan bulannya langsung menjadi tag.</span>
                        @endif
                    </p>
                @elseif ($baris === [])
                    <p class="px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="cari-master-kosong">Tidak ada yang cocok dengan pencarian/saringan ini.</p>
                @elseif ($jenis === 'rencana_aksi')
                    @include('livewire.master.partials.rencana-aksi')
                @else
                    {{-- Layar lebar: tabel. --}}
                    <div class="hidden overflow-x-auto border-t border-stone-100 md:block">
                        <table class="w-full text-sm" data-testid="tabel-master">
                            <thead class="bg-stone-50 text-left text-xs font-semibold text-stone-500">
                                <tr>
                                    <th scope="col" class="px-5 py-2.5 whitespace-nowrap sm:px-6">{{ $jenis === 'budaya' ? 'No.' : 'Kode' }}</th>
                                    @switch($jenis)
                                        @case('sk')
                                            <th scope="col" class="px-3 py-2.5">Uraian sasaran kegiatan</th>
                                            <th scope="col" class="px-3 py-2.5 text-right whitespace-nowrap">IKSK</th>
                                            @break
                                        @case('iksk')
                                            <th scope="col" class="px-3 py-2.5 whitespace-nowrap">SK</th>
                                            <th scope="col" class="px-3 py-2.5">Uraian indikator</th>
                                            @break
                                        @case('rktm')
                                            <th scope="col" class="px-3 py-2.5">Standar (SNP)</th>
                                            <th scope="col" class="px-3 py-2.5">Kegiatan</th>
                                            <th scope="col" class="px-3 py-2.5">Sub kegiatan</th>
                                            @break
                                        @case('edm')
                                            <th scope="col" class="px-3 py-2.5 whitespace-nowrap">Komponen</th>
                                            <th scope="col" class="px-3 py-2.5">Uraian indikator</th>
                                            @break
                                        @case('budaya')
                                            <th scope="col" class="px-3 py-2.5">Budaya mutu</th>
                                            @break
                                    @endswitch
                                    <th scope="col" class="px-5 py-2.5 text-right whitespace-nowrap sm:px-6">Dipakai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($baris as $b)
                                    @php
                                        $kode = (string) $b[$info['kunci']];
                                        $n = $pakai[$kode] ?? 0;
                                    @endphp
                                    <tr class="align-top" data-kode="{{ $kode }}" wire:key="kode-{{ $jenis }}-{{ $kode }}">
                                        <td class="px-5 py-2.5 font-semibold whitespace-nowrap text-stone-900 tabular-nums sm:px-6">{{ $kode }}</td>
                                        @switch($jenis)
                                            @case('sk')
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['uraian'] }}</td>
                                                <td class="px-3 py-2.5 text-right text-stone-600 tabular-nums">{{ $relasi['iksk_per_sk'][$kode] ?? 0 }}</td>
                                                @break
                                            @case('iksk')
                                                <td class="px-3 py-2.5 whitespace-nowrap text-stone-600" title="{{ $relasi['uraian_sk'][$b['kode_sk']] ?? 'SK tidak ditemukan di master' }}">{{ $b['kode_sk'] }}</td>
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['uraian'] }}</td>
                                                @break
                                            @case('rktm')
                                                <td class="px-3 py-2.5 text-stone-600">{{ $b['snp'] }}</td>
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['kegiatan'] }}</td>
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['sub_kegiatan'] }}</td>
                                                @break
                                            @case('edm')
                                                <td class="px-3 py-2.5 whitespace-nowrap text-stone-600">{{ $b['komponen'] }}{{ isset($komponen[$b['komponen']]) ? ' · '.$komponen[$b['komponen']] : '' }}</td>
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['uraian'] }}</td>
                                                @break
                                            @case('budaya')
                                                <td class="px-3 py-2.5 text-stone-700">{{ $b['nama'] }}</td>
                                                @break
                                        @endswitch
                                        <td @class(['px-5 py-2.5 text-right whitespace-nowrap tabular-nums sm:px-6', 'font-semibold text-brand-800' => $n > 0, 'text-stone-400' => $n === 0])>{{ $n > 0 ? $n.' kegiatan' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Layar sempit: kartu per kode. --}}
                    <ul class="divide-y divide-stone-100 border-t border-stone-100 md:hidden" data-testid="kartu-master">
                        @foreach ($baris as $b)
                            @php
                                $kode = (string) $b[$info['kunci']];
                                $n = $pakai[$kode] ?? 0;
                                $uraian = match ($jenis) {
                                    'rktm' => $b['sub_kegiatan'],
                                    'budaya' => $b['nama'],
                                    default => $b['uraian'],
                                };
                                $keterangan = match ($jenis) {
                                    'sk' => ($relasi['iksk_per_sk'][$kode] ?? 0).' IKSK',
                                    'iksk' => $b['kode_sk'],
                                    'rktm' => $b['snp'].' · '.$b['kegiatan'],
                                    'edm' => $b['komponen'].(isset($komponen[$b['komponen']]) ? ' · '.$komponen[$b['komponen']] : ''),
                                    default => null,
                                };
                            @endphp
                            <li class="px-5 py-3" data-kode="{{ $kode }}" wire:key="kartu-{{ $jenis }}-{{ $kode }}">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="font-semibold text-stone-900 tabular-nums">{{ $kode }}</span>
                                    <span @class(['shrink-0 text-xs tabular-nums', 'font-semibold text-brand-800' => $n > 0, 'text-stone-400' => $n === 0])>{{ $n > 0 ? $n.' kegiatan' : 'Belum dipakai' }}</span>
                                </div>
                                <p class="mt-0.5 text-sm text-stone-700">{{ $uraian }}</p>
                                @if ($keterangan)
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $keterangan }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif
</div>
