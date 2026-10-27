@use('App\Livewire\Kegiatan\MatrikJadwal')
@use('App\Support\Format')
@use('App\Support\Kegiatan\JadwalKegiatan')
@use('App\Support\Kegiatan\JadwalPelaksanaan')
@use('App\Support\Kegiatan\KonteksAi')
@use('App\Support\Kegiatan\MatrikTahapan')
@use('Illuminate\Support\Str')

@php
    $susunan = $this->susunanMatrik;
    $susunanJadwal = $this->susunanJadwal;
    $pelaksanaan = $this->pelaksanaan;
    $tahun = $this->tahun();
    $bisaUbah = $this->dapatDiedit();
    $bulanPelaksanaan = $pelaksanaan->bulan;
    $selesai = $pelaksanaan->selesai();
    $batasLaporan = Format::tanggal($dataKegiatan['tanggal_laporan'] ?? null);
    $namaBulan = JadwalPelaksanaan::NAMA_BULAN;
    $kolomPelaksanaan = fn (int $b) => in_array($b, $bulanPelaksanaan, true);
@endphp

<div class="mx-auto max-w-7xl"
     x-data="{ dirty: $wire.entangle('adaPerubahan') }"
     x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
     x-on:livewire:navigate.document="if (dirty && ! confirm('Perubahan matrik & jadwal belum disimpan. Tinggalkan halaman ini?')) $event.preventDefault()"
     @if ($bisaUbah) x-on:keydown.window.ctrl.s.prevent="$wire.simpan()" x-on:keydown.window.meta.s.prevent="$wire.simpan()" @endif>
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="text-stone-700">Matrik &amp; Jadwal</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Matrik &amp; Jadwal Kegiatan</h1>
                <x-ui.status-badge :status="$this->status()->value" />
            </div>
            <p class="mt-1 text-sm text-stone-500">{{ $dataKegiatan['nama_kegiatan'] }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($bisaUbah)
                {{-- Ikut state lokal: sel matrik yang ditandai belum terkirim ke server sampai Simpan. --}}
                <span x-show="dirty" @unless ($adaPerubahan) style="display: none" @endunless class="inline-flex items-center gap-1 text-sm font-medium text-gold-700" data-testid="belum-disimpan">
                    <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
                </span>
                <button type="button" x-show="dirty" @unless ($adaPerubahan) style="display: none" @endunless class="btn-ghost" wire:click="batalkanPerubahan" wire:loading.attr="disabled">Batalkan</button>
            @endif
            <button type="button" class="btn-secondary" wire:click="$set('pratinjauTerbuka', true)" wire:loading.attr="disabled" wire:target="pratinjauTerbuka"
                    title="Bagian 4. Tahapan Kegiatan dan 7. Jadwal Kegiatan seperti tercetak, dari isian saat ini">
                <x-ui.icon name="eye" class="size-4" />
                Pratinjau KAK
            </button>
            @if ($bisaUbah)
                <button type="button" class="btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan" title="Simpan (Ctrl+S)">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
            @endif
        </div>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="matrik" :kegiatan-id="$kegiatanId" />
    </div>

    <x-kegiatan.banner-kunci :kegiatan-id="$kegiatanId" :status="$this->status()->value" subjek="Matrik & jadwal" class="mt-6" />

    {{-- Acuan waktu dari Data Kegiatan --}}
    <div id="acuan" class="mt-6 flex scroll-mt-24 flex-wrap items-center gap-x-6 gap-y-2 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-600 ring-1 ring-stone-200" data-testid="acuan-pelaksanaan">
        <span class="flex items-center gap-2">
            <x-ui.icon name="calendar" class="size-4 shrink-0 text-stone-400" />
            Bulan pelaksanaan
            <strong @class(['font-semibold', 'text-stone-900' => $bulanPelaksanaan !== [], 'text-gold-700' => $bulanPelaksanaan === []])>{{ JadwalPelaksanaan::teksBulan($bulanPelaksanaan, $tahun) ?? 'belum diisi' }}</strong>
        </span>
        @if ($pelaksanaan->mulai && $selesai)
            <span>Tanggal <strong class="font-semibold text-stone-900">{{ JadwalPelaksanaan::teksRentang($pelaksanaan->mulai, $selesai) }}</strong></span>
        @endif
        @if ($batasLaporan)
            <span>Batas laporan <strong class="font-semibold text-stone-900">{{ $batasLaporan }}</strong></span>
        @endif
        <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#pelaksanaan" class="text-xs font-semibold text-brand-700 hover:text-brand-800 sm:ml-auto">Ubah di Data Kegiatan</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="min-w-0 space-y-6">
            {{-- A. Matrik tahapan (KAK bagian 4) --}}
            <section id="matrik" class="scroll-mt-24 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-matrik" data-testid="matrik-tahapan">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-matrik" class="text-base font-semibold text-stone-900">Matrik tahapan</h2>
                        <p class="mt-0.5 text-sm text-stone-500">
                            Bulan pelaksanaan tiap butir tahapan — tercetak di KAK bagian 4. Tahapan Kegiatan.
                            @if ($bisaUbah)
                                <span class="hidden sm:inline">Klik sel, atau tekan lalu seret untuk menandai beberapa bulan sekaligus.</span>
                                <span class="sm:hidden">Ketuk sel untuk menandai bulan.</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-medium text-stone-500" data-testid="ringkasan-matrik" x-text="$ringkasanMatrik($wire.matrik).jumlahTanda + ' tanda bulan'">{{ $susunan->jumlahTanda() }} tanda bulan</p>
                        @if ($bisaUbah)
                            <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="isiDariPelaksanaan" @disabled($bulanPelaksanaan === [])
                                    title="{{ $bulanPelaksanaan === [] ? 'Isi bulan pelaksanaan di Data Kegiatan dulu' : 'Persiapan sebelum, Pelaksanaan pada, Evaluasi & Pelaporan pada bulan batas laporan' }}">
                                <x-ui.icon name="sparkles" class="size-3.5" />
                                Isi dari jadwal pelaksanaan
                            </button>
                            <button type="button" class="btn-ghost px-3 py-1.5 text-xs text-stone-600" wire:click="kosongkanMatrik"
                                    x-show="$ringkasanMatrik($wire.matrik).jumlahTanda > 0" @if ($susunan->jumlahTanda() === 0) style="display: none" @endif>Kosongkan</button>
                        @endif
                    </div>
                </header>

                @if ($matrikSebelum)
                    {{-- Hilang begitu sel diubah tangan (state lokal); kunci per isi otomatis agar banner berikutnya tampil lagi. --}}
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 bg-stone-800 px-5 py-2 text-sm text-white sm:px-6" role="status" data-testid="matrik-berubah"
                         wire:key="matrik-berubah-{{ md5(json_encode($matrikSebelum)) }}" x-data="{ tampil: true }" x-show="tampil" x-on:matrik-diubah-lokal.window="tampil = false">
                        <span>{{ $matrikSebelum['pesan'] }}</span>
                        <button type="button" wire:click="urungkanMatrik" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                    </div>
                @endif

                {{-- Selama aksi server yang menimpa matrik berjalan, sel dikunci agar tanda baru tidak tertimpa responsnya. --}}
                <div class="overflow-x-auto transition-opacity" data-testid="matrik-wadah"
                     x-data="gridMatrik({ dapatDiedit: @js($bisaUbah) })" x-on:pointerup.window="selesai()" x-on:pointercancel.window="selesai()"
                     wire:loading.class="pointer-events-none opacity-60" wire:target="isiDariPelaksanaan, kosongkanMatrik, urungkanMatrik, batalkanPerubahan, simpan">
                    <table class="w-full min-w-[33rem] text-sm select-none sm:min-w-[44rem]">
                        <caption class="sr-only">Matrik tahapan kegiatan per bulan, Tahun Anggaran {{ $tahun }}</caption>
                        <thead>
                            <tr class="bg-stone-50 text-xs font-semibold text-stone-500">
                                <th scope="col" class="sticky left-0 z-10 w-36 bg-stone-50 px-4 py-2 text-left sm:w-auto sm:px-6">Kegiatan</th>
                                @foreach ($namaBulan as $nomor => $nama)
                                    <th scope="col" title="{{ $nama }} {{ $tahun }}" data-bulan="{{ $nomor }}" @if ($kolomPelaksanaan($nomor)) data-pelaksanaan @endif
                                        @class(['w-8 px-0.5 py-2 text-center sm:w-11 sm:px-1', 'bg-brand-50 text-brand-800' => $kolomPelaksanaan($nomor)])>{{ mb_substr($nama, 0, 3) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach (MatrikTahapan::TAHAPAN as $kunci => $judul)
                            <tbody class="border-t border-stone-200" data-tahapan="{{ $kunci }}">
                                <tr>
                                    <th scope="rowgroup" class="sticky left-0 z-10 bg-white px-4 pt-3 pb-1.5 text-left font-semibold text-stone-900 sm:px-6">{{ $loop->iteration }}. {{ $judul }}</th>
                                    @foreach ($namaBulan as $nomor => $nama)
                                        <td @class(['px-1', 'bg-brand-50/60' => $kolomPelaksanaan($nomor)])></td>
                                    @endforeach
                                </tr>
                                @foreach ($matrik[$kunci] ?? [] as $index => $butir)
                                    <tr class="border-t border-stone-100" data-testid="butir-{{ $kunci }}-{{ $index + 1 }}" wire:key="butir-{{ $kunci }}-{{ $index }}">
                                        {{-- Layar sempit: kolom uraian dipersempit agar lebih banyak bulan terlihat tanpa menggeser. --}}
                                        <th scope="row" class="sticky left-0 z-10 w-36 max-w-36 bg-white py-2 pr-2 pl-6 text-left text-xs font-normal text-stone-700 sm:w-auto sm:max-w-64 sm:pr-3 sm:pl-10 sm:text-sm">
                                            {{ $butir['uraian'] ?: 'Uraian belum diisi' }}
                                        </th>
                                        @foreach ($namaBulan as $nomor => $nama)
                                            @php $ditandai = in_array($nomor, $butir['bulan'], true); @endphp
                                            <td data-bulan="{{ $nomor }}" data-ditandai="{{ $ditandai ? 'ya' : 'tidak' }}"
                                                x-bind:data-ditandai="ditandai('{{ $kunci }}', {{ $index }}, {{ $nomor }}) ? 'ya' : 'tidak'"
                                                @class(['p-0.5 text-center sm:p-1', 'bg-brand-50/60' => $kolomPelaksanaan($nomor)])>
                                                <button type="button" aria-pressed="{{ $ditandai ? 'true' : 'false' }}"
                                                        x-bind:aria-pressed="ditandai('{{ $kunci }}', {{ $index }}, {{ $nomor }}) ? 'true' : 'false'"
                                                        aria-label="{{ $judul }}: {{ $butir['uraian'] ?: 'butir '.($index + 1) }} — {{ $nama }}"
                                                        @disabled(! $bisaUbah)
                                                        x-on:pointerdown="mulai($event, '{{ $kunci }}', {{ $index }}, {{ $nomor }})"
                                                        x-on:pointerenter="lewati('{{ $kunci }}', {{ $index }}, {{ $nomor }})"
                                                        x-on:click="klik('{{ $kunci }}', {{ $index }}, {{ $nomor }})"
                                                        class="group grid h-8 w-full place-items-center rounded-md text-white transition hover:bg-stone-100 focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none disabled:cursor-default disabled:hover:bg-transparent aria-pressed:bg-brand-600 aria-pressed:hover:bg-brand-700">
                                                    <x-ui.icon name="check" class="hidden size-4 group-aria-pressed:block" />
                                                </button>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>

                <footer class="flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-brand-50 ring-1 ring-brand-200" aria-hidden="true"></span> Bulan pelaksanaan (Data Kegiatan)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="grid size-3.5 place-items-center rounded-sm bg-brand-600 text-white" aria-hidden="true"><x-ui.icon name="check" class="size-2.5" /></span> Bulan tahapan dilaksanakan</span>
                    <span class="sm:hidden">Geser tabel ke samping untuk melihat semua bulan.</span>
                </footer>
            </section>

            {{-- B. Jadwal kegiatan (KAK bagian 7) --}}
            <section id="jadwal" class="scroll-mt-24 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-jadwal" data-testid="jadwal-kegiatan"
                     x-data x-on:jadwal-ditambah.window="$nextTick(() => { const isian = $el.querySelectorAll('[data-isian-jadwal]'); isian[isian.length - 1]?.focus() })">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-jadwal" class="text-base font-semibold text-stone-900">Jadwal kegiatan</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Urutan kegiatan dan waktu pelaksanaannya — tercetak di KAK bagian 7. Jadwal Kegiatan. Cukup pilih bulan; tahunnya Tahun Anggaran {{ $tahun }}.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-medium text-stone-500" data-testid="ringkasan-jadwal">{{ $jadwal === [] ? 'Belum ada baris' : $susunanJadwal->jumlahTerisi().' dari '.count($susunanJadwal->baris).' baris terisi' }}</p>
                        @if ($bisaUbah)
                            <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="bukaUsulJadwal" data-testid="buka-usul-jadwal"
                                    title="AI mengusulkan 4–6 uraian jadwal beserta bulannya dari data kegiatan; Anda memilih versi yang dipakai">
                                <x-ui.icon name="sparkles" class="size-3.5 text-brand-600" />
                                {{ $usulan === [] ? 'Usulkan dengan AI' : 'Lihat usulan AI' }}
                                @if ($usulan !== [])
                                    <span class="size-1.5 rounded-full bg-brand-600" aria-hidden="true"></span>
                                @endif
                            </button>
                            <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="isiJadwalBaku"
                                    title="Enam uraian baku dengan bulan dari jadwal pelaksanaan (bisa diurungkan)">
                                <x-ui.icon name="note" class="size-3.5" />
                                Isi jadwal baku
                            </button>
                        @endif
                    </div>
                </header>

                @if ($jadwalSebelum)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 bg-stone-800 px-5 py-2 text-sm text-white sm:px-6" role="status" data-testid="jadwal-berubah">
                        <span>{{ $jadwalSebelum['pesan'] }}</span>
                        <button type="button" wire:click="urungkanJadwal" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                    </div>
                @endif

                {{-- Kepala kolom (layar lebar); di layar sempit tiap baris bertumpuk: uraian lalu waktu, tombol aksi di kanan. --}}
                @if ($jadwal !== [])
                    <div @class(['hidden gap-3 bg-stone-50 px-6 py-2 text-xs font-semibold text-stone-500 sm:grid', 'grid-cols-[2rem_minmax(0,1fr)_17rem_6.5rem]' => $bisaUbah, 'grid-cols-[2rem_minmax(0,1fr)_17rem]' => ! $bisaUbah]) aria-hidden="true">
                        <span>No</span><span>Uraian kegiatan</span><span>Waktu pelaksanaan</span>
                        @if ($bisaUbah)
                            <span class="text-right">Urutan</span>
                        @endif
                    </div>
                @endif
                <ol class="divide-y divide-stone-100" aria-label="Baris jadwal kegiatan">
                    @foreach ($jadwal as $index => $baris)
                        @php
                            $nomor = $index + 1;
                            $waktu = $susunanJadwal->waktu($index, $tahun);
                        @endphp
                        <li @class(['grid scroll-mt-24 grid-cols-[1.25rem_minmax(0,1fr)_auto] items-start gap-x-3 gap-y-2 px-5 py-3 sm:px-6', 'sm:grid-cols-[2rem_minmax(0,1fr)_17rem_6.5rem]' => $bisaUbah, 'sm:grid-cols-[2rem_minmax(0,1fr)_17rem]' => ! $bisaUbah])
                            id="baris-jadwal-{{ $nomor }}" data-testid="baris-jadwal-{{ $nomor }}" wire:key="jadwal-{{ $index }}">
                            <span class="row-start-1 pt-2 text-sm text-stone-500 tabular-nums">{{ $nomor }}</span>
                            @if ($bisaUbah)
                                <div class="col-start-2 row-start-1 min-w-0 sm:col-start-auto sm:row-start-auto">
                                    <label for="jadwal-{{ $index }}-uraian" class="sr-only">Uraian kegiatan baris {{ $nomor }}</label>
                                    <input type="text" id="jadwal-{{ $index }}-uraian" wire:model.live.blur="jadwal.{{ $index }}.uraian" maxlength="200" autocomplete="off" data-isian-jadwal
                                           placeholder="mis. {{ \App\Livewire\Kegiatan\MatrikJadwal::JADWAL_BAKU[$index] ?? 'Uraian kegiatan' }}" class="form-input"
                                           @error("jadwal.{$index}.uraian") aria-invalid="true" aria-describedby="jadwal-{{ $index }}-uraian-error" @enderror>
                                    @error("jadwal.{$index}.uraian") <p class="form-error" id="jadwal-{{ $index }}-uraian-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-start-2 row-start-2 min-w-0 sm:col-start-auto sm:row-start-auto">
                                    <div class="flex items-center gap-1.5">
                                        <label for="jadwal-{{ $index }}-mulai" class="sr-only">Bulan mulai baris {{ $nomor }}</label>
                                        <select id="jadwal-{{ $index }}-mulai" wire:model.live="jadwal.{{ $index }}.mulai" class="form-input min-w-0 flex-1 py-1.5">
                                            <option value="">Bulan</option>
                                            @foreach ($namaBulan as $n => $nama)
                                                <option value="{{ $n }}">{{ $nama }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-xs text-stone-400">s.d.</span>
                                        <label for="jadwal-{{ $index }}-sampai" class="sr-only">Bulan selesai baris {{ $nomor }} (opsional)</label>
                                        <select id="jadwal-{{ $index }}-sampai" wire:model.live="jadwal.{{ $index }}.sampai" @disabled($baris['mulai'] === null) class="form-input min-w-0 flex-1 py-1.5">
                                            <option value="">—</option>
                                            @foreach ($namaBulan as $n => $nama)
                                                @if ($baris['mulai'] !== null && $n > $baris['mulai'])
                                                    <option value="{{ $n }}">{{ $nama }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <p @class(['mt-1 text-xs', 'text-stone-600' => $waktu, 'text-stone-400' => ! $waktu]) data-testid="waktu-jadwal-{{ $nomor }}">{{ $waktu ?? 'Waktu belum dipilih' }}</p>
                                    @error("jadwal.{$index}.sampai") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                {{-- Layar sempit: tombol bertumpuk di kanan uraian & waktu; layar lebar: satu baris di kolom Urutan. --}}
                                <div class="col-start-3 row-span-2 row-start-1 flex flex-col items-center gap-0.5 sm:col-start-auto sm:row-span-1 sm:row-start-auto sm:mt-0.5 sm:flex-row sm:justify-end">
                                    <button type="button" wire:click="geserBarisJadwal({{ $index }}, -1)" @disabled($loop->first)
                                            class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                            title="Naikkan" aria-label="Naikkan baris {{ $nomor }}">
                                        <x-ui.icon name="arrow-up" class="size-4" />
                                    </button>
                                    <button type="button" wire:click="geserBarisJadwal({{ $index }}, 1)" @disabled($loop->last)
                                            class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                            title="Turunkan" aria-label="Turunkan baris {{ $nomor }}">
                                        <x-ui.icon name="arrow-down" class="size-4" />
                                    </button>
                                    <button type="button" wire:click="hapusBarisJadwal({{ $index }})"
                                            class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600"
                                            title="Hapus" aria-label="Hapus baris {{ $nomor }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            @else
                                <p @class(['col-start-2 row-start-1 pt-2 text-sm sm:col-start-auto', 'text-stone-800' => $baris['uraian'] !== '', 'text-stone-400 italic' => $baris['uraian'] === ''])>{{ $baris['uraian'] ?: 'Belum diisi' }}</p>
                                <p @class(['col-start-2 row-start-2 text-sm sm:col-start-auto sm:row-start-auto sm:pt-2', 'text-stone-800' => $waktu, 'text-stone-400' => ! $waktu]) data-testid="waktu-jadwal-{{ $nomor }}">{{ $waktu ?? '—' }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($jadwal === [])
                    <div class="px-5 py-8 text-center sm:px-6" data-testid="jadwal-kosong">
                        <x-ui.icon name="calendar" class="mx-auto size-8 text-stone-300" />
                        <p class="mt-2 text-sm font-medium text-stone-700">Belum ada baris jadwal</p>
                        <p class="mt-0.5 text-xs text-stone-500">
                            {{ $bisaUbah ? 'Isi jadwal baku, minta usulan AI, atau tambah baris satu per satu.' : 'Jadwal kegiatan belum diisi.' }}
                        </p>
                    </div>
                @endif

                @if ($bisaUbah)
                    @php
                        $penuh = count($jadwal) >= JadwalKegiatan::MAKS_BARIS;
                    @endphp
                    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
                        <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="tambahBarisJadwal" @disabled($penuh)
                                title="{{ $penuh ? 'Jadwal sudah '.JadwalKegiatan::MAKS_BARIS.' baris' : 'Tambah baris kosong di akhir jadwal' }}">
                            <x-ui.icon name="plus" class="size-3.5" />
                            Tambah baris
                        </button>
                        <p class="text-xs text-stone-500" data-testid="jumlah-baris-jadwal">{{ count($jadwal) }} dari maks. {{ JadwalKegiatan::MAKS_BARIS }} baris · urutan di sini menjadi urutan cetak</p>
                    </footer>
                @endif
            </section>
        </div>

        {{-- Samping: kelengkapan untuk dokumen --}}
        <aside class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tercetak" data-testid="kelengkapan-matrik-jadwal">
                <h2 id="judul-tercetak" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tercetak di KAK</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    {{-- Matrik mengikuti state lokal (dihitung di klien, kembaran MatrikTahapan::lengkap()). --}}
                    <li class="flex items-start gap-2.5" data-testid="kelengkapan-matrik" data-lengkap="{{ $susunan->lengkap() ? 'ya' : 'tidak' }}"
                        x-data="{ get lengkap() { return $ringkasanMatrik($wire.matrik).lengkap } }" x-bind:data-lengkap="lengkap ? 'ya' : 'tidak'">
                        <span x-show="lengkap" @unless ($susunan->lengkap()) style="display: none" @endunless class="mt-0.5 shrink-0">
                            <x-ui.icon name="check-circle" class="size-4 text-brand-600" />
                        </span>
                        <span x-show="! lengkap" @if ($susunan->lengkap()) style="display: none" @endif class="mt-0.5 shrink-0">
                            <x-ui.icon name="circle" class="size-4 text-stone-300" />
                        </span>
                        <div class="min-w-0">
                            <a href="#matrik" class="font-medium text-stone-800 hover:text-brand-700">4. Tahapan Kegiatan</a>
                            <p class="text-xs text-stone-500" x-text="lengkap ? 'Ketiga tahapan sudah bertanda bulan.' : 'Tandai minimal satu bulan di setiap tahapan.'">
                                {{ $susunan->lengkap() ? 'Ketiga tahapan sudah bertanda bulan.' : 'Tandai minimal satu bulan di setiap tahapan.' }}
                            </p>
                        </div>
                    </li>
                    @foreach ([
                        ['kunci' => 'jadwal', 'judul' => '7. Jadwal Kegiatan', 'lengkap' => $susunanJadwal->lengkap(),
                         'teks' => $jadwal === [] ? 'Belum ada baris jadwal.' : $susunanJadwal->jumlahTerisi().' dari '.count($susunanJadwal->baris).' baris berisi uraian & waktu.'],
                    ] as $bagian)
                        <li class="flex items-start gap-2.5" data-testid="kelengkapan-{{ $bagian['kunci'] }}" data-lengkap="{{ $bagian['lengkap'] ? 'ya' : 'tidak' }}">
                            <x-ui.icon :name="$bagian['lengkap'] ? 'check-circle' : 'circle'" class="mt-0.5 size-4 shrink-0 {{ $bagian['lengkap'] ? 'text-brand-600' : 'text-stone-300' }}" />
                            <div class="min-w-0">
                                <a href="#{{ $bagian['kunci'] }}" class="font-medium text-stone-800 hover:text-brand-700">{{ $bagian['judul'] }}</a>
                                <p class="text-xs text-stone-500">{{ $bagian['teks'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            @php
                $konsistensi = $this->konsistensi;
                $temuanAwal = $konsistensi->temuan();
                $peringatanAwal = $konsistensi->jumlahPeringatan();
                $adaIsianAwal = $konsistensi->adaIsian();
            @endphp
            {{--
                Konsistensi bulan: dihitung di klien (panelKonsistensi, kembaran KonsistensiBulan) karena tanda matrik adalah state
                lokal; daftar dari server hanya tampil sebelum Alpine siap. wire:ignore: Alpine yang memperbarui isinya.
            --}}
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-konsistensi" data-testid="konsistensi-bulan" wire:ignore
                     x-data="panelKonsistensi({ pelaksanaan: @js($bulanPelaksanaan), laporan: @js($this->bulanLaporan()) })"
                     data-jumlah="{{ count($temuanAwal) }}" x-bind:data-jumlah="temuan.length">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="judul-konsistensi" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Konsistensi bulan</h2>
                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', 'bg-gold-50 text-gold-700 ring-gold-200' => $peringatanAwal > 0, 'bg-stone-100 text-stone-600 ring-stone-200' => $peringatanAwal === 0 && ($temuanAwal !== [] || ! $adaIsianAwal), 'bg-brand-50 text-brand-800 ring-brand-200' => $temuanAwal === [] && $adaIsianAwal])
                          x-bind:class="jumlahPeringatan > 0 ? 'bg-gold-50 text-gold-700 ring-gold-200' : (temuan.length > 0 || ! adaIsian ? 'bg-stone-100 text-stone-600 ring-stone-200' : 'bg-brand-50 text-brand-800 ring-brand-200')"
                          x-text="temuan.length > 0 ? temuan.length + ' temuan' : (adaIsian ? 'Sesuai' : 'Belum diisi')" data-testid="status-konsistensi">{{ $temuanAwal !== [] ? count($temuanAwal).' temuan' : ($adaIsianAwal ? 'Sesuai' : 'Belum diisi') }}</span>
                </div>

                <ul class="mt-3 space-y-2.5 text-xs" x-show="! siap" aria-label="Temuan konsistensi bulan">
                    @foreach ($temuanAwal as $t)
                        <li class="flex items-start gap-2" data-tingkat="{{ $t['tingkat'] }}">
                            <x-ui.icon :name="$t['tingkat'] === 'peringatan' ? 'alert' : 'info'" class="mt-px size-3.5 shrink-0 {{ $t['tingkat'] === 'peringatan' ? 'text-gold-600' : 'text-stone-400' }}" />
                            <p class="min-w-0 text-stone-700">{{ $t['pesan'] }} <a href="#{{ $t['tautan'] }}" class="font-semibold text-brand-700 hover:text-brand-800">Lihat</a></p>
                        </li>
                    @endforeach
                </ul>
                <ul class="mt-3 space-y-2.5 text-xs" x-show="siap" style="display: none" aria-label="Temuan konsistensi bulan" aria-live="polite">
                    <template x-for="t in temuan" x-bind:key="t.kunci">
                        <li class="flex items-start gap-2" x-bind:data-tingkat="t.tingkat" x-bind:data-kunci="t.kunci">
                            <span x-show="t.tingkat === 'peringatan'" class="mt-px shrink-0"><x-ui.icon name="alert" class="size-3.5 text-gold-600" /></span>
                            <span x-show="t.tingkat !== 'peringatan'" class="mt-px shrink-0"><x-ui.icon name="info" class="size-3.5 text-stone-400" /></span>
                            <p class="min-w-0 text-stone-700"><span x-text="t.pesan"></span> <a x-bind:href="'#' + t.tautan" class="font-semibold text-brand-700 hover:text-brand-800">Lihat</a></p>
                        </li>
                    </template>
                </ul>
                <p class="mt-3 flex items-start gap-2 text-xs text-stone-600" x-show="temuan.length === 0 && adaIsian" @if ($temuanAwal !== [] || ! $adaIsianAwal) style="display: none" @endif>
                    <x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" />
                    Bulan matrik dan jadwal sesuai dengan bulan pelaksanaan dan batas laporan.
                </p>
                <p class="mt-3 text-xs text-stone-500" x-show="temuan.length === 0 && ! adaIsian" @if ($temuanAwal !== [] || $adaIsianAwal) style="display: none" @endif>
                    Belum ada bulan di matrik maupun jadwal untuk diperiksa.
                </p>
                <p class="mt-3 border-t border-stone-100 pt-2.5 text-[11px] text-stone-400">Hanya pengingat — tidak menghalangi Simpan. Dokumen tetap bisa dicetak sebagai DRAF.</p>
            </section>

            <div class="rounded-2xl bg-gold-50 p-4 text-xs text-gold-700 ring-1 ring-gold-200">
                <p class="flex items-center gap-1.5 font-semibold">
                    <x-ui.icon name="info" class="size-4" />
                    Satu tahun untuk semua
                </p>
                <p class="mt-1.5 leading-relaxed">Waktu jadwal cukup memilih bulan; tahunnya selalu Tahun Anggaran {{ $tahun }} sehingga tidak ada tahun lama yang tertinggal di dokumen.</p>
            </div>
        </aside>
    </div>

    {{-- Panel pratinjau KAK bagian 4 & 7 (isian saat ini, termasuk tanda matrik lokal yang terkirim saat panel dibuka). --}}
    @if ($pratinjauTerbuka)
        @php
            $kak = $this->pratinjauKak;
            $kurangKak = array_values(array_filter([
                $susunan->lengkap() ? null : 'Matrik tahapan: tandai minimal satu bulan di setiap tahapan.',
                $susunanJadwal->lengkap() ? null : ($jadwal === [] ? 'Jadwal kegiatan belum punya baris.' : 'Jadwal kegiatan: lengkapi uraian & waktu setiap baris, atau hapus baris kosong.'),
            ]));
        @endphp
        <div class="fixed inset-0 z-50 flex" role="dialog" aria-modal="true" aria-labelledby="judul-pratinjau-matrik" data-testid="panel-pratinjau-matrik"
             x-data="{ init() { document.documentElement.classList.add('overflow-hidden') }, destroy() { document.documentElement.classList.remove('overflow-hidden') } }"
             x-on:keydown.escape.window="$wire.set('pratinjauTerbuka', false)">
            <div class="absolute inset-0 bg-stone-900/40" wire:click="$set('pratinjauTerbuka', false)" aria-hidden="true"></div>
            <div class="relative ml-auto flex h-full w-full max-w-4xl flex-col bg-stone-100 shadow-xl" x-trap="true">
                <header class="flex flex-wrap items-center gap-3 border-b border-stone-200 bg-white px-4 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <h2 id="judul-pratinjau-matrik" class="text-sm font-semibold text-stone-900">Pratinjau KAK/TOR — Tahapan &amp; Jadwal</h2>
                        <p class="text-xs text-stone-500">Bagian 4 dan 7 seperti tercetak, dari isian saat ini{{ $adaPerubahan ? ' (termasuk yang belum disimpan)' : '' }}.</p>
                    </div>
                    <button type="button" class="btn-ghost" wire:click="$set('pratinjauTerbuka', false)">
                        <x-ui.icon name="x" class="size-4" />
                        Tutup
                    </button>
                </header>

                @if ($kurangKak !== [])
                    <div class="space-y-1 border-b border-gold-200 bg-gold-50 px-4 py-2 text-xs text-stone-700 sm:px-6" data-testid="pratinjau-matrik-kurang">
                        @foreach ($kurangKak as $apa)
                            <p class="flex items-start gap-1.5"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-gold-600" /> {{ $apa }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex-1 overflow-y-auto p-3 sm:p-6">
                    <x-dokumen.kak-tor :susunan="$kak" :bagian="[4, 7]" />
                    <p class="mx-auto mt-3 max-w-[210mm] text-center text-xs text-stone-500">
                        Seluruh dokumen (cover, sembilan bagian, pengesahan) bisa dipratinjau dari tombol <em>Pratinjau</em> di
                        <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Data Kegiatan</a>.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Panel usul jadwal AI: permintaan (acuan waktu, jenis kegiatan, instruksi) → 2–3 versi → pakai satu versi (bisa diurungkan) atau tolak. --}}
    @if ($usulTerbuka && $bisaUbah)
        @php
            $jenisKegiatan = KonteksAi::jenisKegiatan();
            $tebakanJenis = KonteksAi::tebakJenis($dataKegiatan['nama_kegiatan'] ?? '');
            $bahanAi = array_values(array_filter(KonteksAi::dariIsian($dataKegiatan), fn ($b) => $b['nilai'] !== null));
            $catatanAi = KonteksAi::redaksi((string) ($dataKegiatan['konteks'] ?? ''));
            $jadwalTerisi = $susunanJadwal->jumlahTerisi();
            $tahapUsul = $usulan === [] ? 1 : 2;
        @endphp
        <div class="fixed inset-0 z-50 flex" role="dialog" aria-modal="true" aria-labelledby="judul-usul-jadwal" data-testid="panel-usul-jadwal"
             x-data="{ init() { document.documentElement.classList.add('overflow-hidden') }, destroy() { document.documentElement.classList.remove('overflow-hidden') } }"
             x-on:keydown.escape.window="$wire.tutupUsulJadwal()">
            <div class="absolute inset-0 bg-stone-900/40" wire:click="tutupUsulJadwal" aria-hidden="true"></div>
            <div class="relative ml-auto flex h-full w-full max-w-xl flex-col bg-white shadow-xl" x-trap="true">
                <header class="flex items-start gap-3 border-b border-stone-200 px-4 py-3 sm:px-6">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-200" aria-hidden="true">
                        <x-ui.icon name="sparkles" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="judul-usul-jadwal" class="text-sm font-semibold text-stone-900">Usulkan jadwal dengan AI</h2>
                        <p class="text-xs text-stone-500">4–6 uraian beserta bulannya untuk KAK bagian 7. Hanya saran — periksa sebelum dipakai.</p>
                    </div>
                    <button type="button" class="btn-ghost px-2.5 py-1.5" wire:click="tutupUsulJadwal">
                        <x-ui.icon name="x" class="size-4" />
                        Tutup
                    </button>
                </header>

                <ol class="flex items-center gap-2 border-b border-stone-100 bg-stone-50 px-4 py-2 text-xs sm:px-6" aria-label="Langkah usulan">
                    @foreach ([1 => 'Permintaan', 2 => 'Pilih versi'] as $tahap => $label)
                        @if ($tahap > 1)
                            <li aria-hidden="true"><x-ui.icon name="chevron-right" class="size-3.5 text-stone-300" /></li>
                        @endif
                        <li @class(['flex items-center gap-1.5', 'font-semibold text-stone-900' => $tahap === $tahapUsul, 'text-stone-400' => $tahap !== $tahapUsul])
                            @if ($tahap === $tahapUsul) aria-current="step" @endif>
                            <span @class(['grid size-4 place-items-center rounded-full text-[10px] font-bold', 'bg-brand-700 text-white' => $tahap <= $tahapUsul, 'bg-stone-200 text-stone-500' => $tahap > $tahapUsul])>{{ $tahap }}</span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ol>

                <div class="relative flex min-h-0 flex-1 flex-col">
                    <div wire:loading.flex wire:target="buatUsulJadwal" class="absolute inset-0 z-10 flex-col items-center justify-center gap-2 bg-white/85 text-sm font-medium text-stone-600" role="status">
                        <x-ui.icon name="loader" class="size-5 animate-spin text-brand-600" />
                        AI sedang menyusun usulan…
                    </div>

                    @if ($usulan === [])
                        {{-- Langkah 1: permintaan --}}
                        <div class="flex-1 space-y-6 overflow-y-auto px-4 py-5 sm:px-6" wire:key="usul-permintaan" data-testid="usul-permintaan">
                            @if ($usulGagal)
                                <div class="rounded-xl bg-red-50 p-3.5 text-red-800 ring-1 ring-red-200" role="alert" data-testid="usul-gagal">
                                    <p class="flex items-center gap-2 text-sm font-semibold">
                                        <x-ui.icon name="alert" class="size-4 shrink-0" />
                                        Usulan AI belum bisa dibuat
                                    </p>
                                    <p class="mt-1 text-xs">{{ $usulGagal }}</p>
                                    <p class="mt-1.5 text-xs text-red-700">Jadwal Anda tidak berubah. Coba lagi sebentar lagi, atau tutup panel lalu pakai <em>Isi jadwal baku</em>.</p>
                                </div>
                            @endif
                            <section class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" aria-labelledby="judul-acuan-usul" data-testid="acuan-usul-jadwal">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 id="judul-acuan-usul" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Acuan waktu</h3>
                                    <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#pelaksanaan" wire:navigate class="text-xs font-semibold text-brand-700 hover:text-brand-800">Ubah di Data Kegiatan</a>
                                </div>
                                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <dt class="text-xs text-stone-500">Bulan pelaksanaan</dt>
                                        <dd @class(['font-semibold', 'text-stone-900' => $bulanPelaksanaan !== [], 'text-gold-700' => $bulanPelaksanaan === []])>{{ JadwalPelaksanaan::teksBulan($bulanPelaksanaan, $tahun) ?? 'belum diisi' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-stone-500">Batas laporan</dt>
                                        <dd @class(['font-semibold', 'text-stone-900' => $batasLaporan, 'text-stone-400' => ! $batasLaporan])>{{ $batasLaporan ?? 'belum diisi' }}</dd>
                                    </div>
                                </dl>
                                @if ($bulanPelaksanaan === [])
                                    <p class="mt-3 flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-700 ring-1 ring-gold-200" data-testid="usul-tanpa-bulan">
                                        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
                                        Bulan pelaksanaan belum diisi — AI hanya mengusulkan uraian; bulannya Anda pilih sendiri setelah dipakai.
                                    </p>
                                @endif

                                <details class="group mt-3 border-t border-stone-200 pt-3" wire:ignore.self data-testid="bahan-usul-jadwal">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs font-medium text-stone-600 hover:text-stone-900">
                                        <span>Bahan lain yang dipakai AI ({{ count($bahanAi) }} isian{{ $catatanAi !== '' ? ' + catatan konteks' : '' }})</span>
                                        <x-ui.icon name="chevron-down" class="size-4 shrink-0 transition group-open:rotate-180" />
                                    </summary>
                                    <dl class="mt-2 space-y-1.5 text-xs">
                                        @foreach ($bahanAi as $b)
                                            <div class="flex gap-2">
                                                <dt class="w-28 shrink-0 text-stone-400">{{ $b['label'] }}</dt>
                                                <dd class="min-w-0 text-stone-700">{{ $b['nilai'] }}</dd>
                                            </div>
                                        @endforeach
                                        <div class="flex gap-2">
                                            <dt class="w-28 shrink-0 text-stone-400">Catatan konteks</dt>
                                            <dd @class(['min-w-0', 'text-stone-700' => $catatanAi !== '', 'text-gold-700' => $catatanAi === ''])>{{ $catatanAi !== '' ? Str::limit($catatanAi, 180) : 'kosong — usulan lebih umum' }}</dd>
                                        </div>
                                    </dl>
                                </details>
                                <p class="mt-3 flex items-start gap-1.5 text-[11px] text-stone-500">
                                    <x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" />
                                    Nama dan NIP pejabat tidak pernah dikirim ke AI.
                                </p>
                            </section>

                            <fieldset data-testid="jenis-usul-jadwal">
                                <legend class="text-sm font-medium text-stone-700">Jenis kegiatan</legend>
                                <p class="mt-0.5 text-xs text-stone-500">Menentukan langkah yang diusulkan. Ditebak dari nama kegiatan — ubah bila kurang tepat.</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @foreach ($jenisKegiatan as $kunci => $judul)
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg bg-white px-3 py-2.5 text-sm ring-1 ring-stone-200 transition ring-inset hover:bg-stone-50 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600 has-focus-visible:outline-2 has-focus-visible:outline-brand-600"
                                               wire:key="jenis-usul-{{ $kunci }}">
                                            <input type="radio" name="usul_jenis" value="{{ $kunci }}" wire:model="usulJenis" @checked($usulJenis === $kunci) class="mt-0.5 size-4 shrink-0 accent-brand-700">
                                            <span class="min-w-0">
                                                <span class="block font-medium text-stone-800">{{ $judul }}</span>
                                                @if ($kunci === $tebakanJenis && $kunci !== 'umum')
                                                    <span class="block text-[11px] font-medium text-brand-700">Cocok dengan nama kegiatan</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('usulJenis') <p class="form-error">{{ $message }}</p> @enderror
                            </fieldset>

                            <div x-data="{ sisip(teks) { const ta = $refs.instruksi; ta.value = ta.value.trim() ? ta.value.replace(/[\s.;,]*$/, '; ') + teks : teks; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); } }">
                                <label for="usul-instruksi" class="form-label">Instruksi tambahan <span class="font-normal text-stone-400">(opsional)</span></label>
                                <textarea id="usul-instruksi" x-ref="instruksi" wire:model="usulInstruksi" rows="2" maxlength="300" class="form-input resize-y"
                                          placeholder="mis. tambahkan rapat evaluasi bersama komite" @error('usulInstruksi') aria-invalid="true" @enderror></textarea>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="text-[11px] text-stone-400">Sisipkan:</span>
                                    @foreach (MatrikJadwal::SARAN_INSTRUKSI as $saran)
                                        <button type="button" x-on:click="sisip(@js($saran))"
                                                class="rounded-full px-2.5 py-0.5 text-xs font-medium text-stone-600 ring-1 ring-stone-200 ring-inset hover:bg-stone-100 hover:text-stone-900">+ {{ $saran }}</button>
                                    @endforeach
                                </div>
                                @error('usulInstruksi') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <footer class="flex items-center justify-end gap-2 border-t border-stone-200 px-4 py-3 sm:px-6">
                            <x-ai.status-kuota :status="$this->statusKuota()" ringkas class="mr-auto" />
                            <button type="button" class="btn-ghost" wire:click="tutupUsulJadwal">Batal</button>
                            <button type="button" class="btn-primary" wire:click="buatUsulJadwal" wire:loading.attr="disabled" wire:target="buatUsulJadwal">
                                <x-ui.icon name="sparkles" class="size-4" />
                                {{ $usulGagal ? 'Coba lagi' : 'Buat usulan' }}
                            </button>
                        </footer>
                    @else
                        {{-- Langkah 2: pilih versi --}}
                        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6" wire:key="usul-hasil" data-testid="usul-hasil">
                            <p class="text-sm text-stone-600">
                                {{ count($usulan) }} versi untuk <strong class="font-semibold text-stone-900">{{ $jenisKegiatan[$usulJenis] ?? 'kegiatan ini' }}</strong>@if (trim($usulInstruksi) !== '') dengan instruksi “{{ Str::limit(trim($usulInstruksi), 120) }}”@endif.
                                Pilih satu untuk mengisi tabel jadwal, lalu sesuaikan bila perlu.
                            </p>
                            @if ($jadwalTerisi > 0)
                                <p class="flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-700 ring-1 ring-gold-200" data-testid="usul-ganti-jadwal">
                                    <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
                                    Memakai satu versi mengganti {{ $jadwalTerisi }} baris jadwal yang sudah terisi. Masih bisa diurungkan sebelum disimpan.
                                </p>
                            @endif

                            @foreach ($usulan as $i => $versi)
                                @php
                                    $susunanVersi = JadwalKegiatan::dari(['jadwal' => MatrikJadwal::jadwalUntukDisimpan($versi['baris'])]);
                                @endphp
                                <article class="rounded-xl bg-white ring-1 ring-stone-200" aria-labelledby="judul-versi-{{ $i }}" data-testid="versi-usulan-{{ $i + 1 }}" wire:key="versi-usulan-{{ $i }}">
                                    <header class="flex items-start justify-between gap-3 px-4 pt-3">
                                        <div class="min-w-0">
                                            <h3 id="judul-versi-{{ $i }}" class="text-sm font-semibold text-stone-900">{{ $versi['judul'] }}{{ $versi['ciri'] !== '' ? ' · '.$versi['ciri'] : '' }}</h3>
                                            @if ($versi['keterangan'] !== '')
                                                <p class="text-xs text-stone-500">{{ $versi['keterangan'] }}</p>
                                            @endif
                                        </div>
                                        <span class="shrink-0 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">{{ count($versi['baris']) }} baris</span>
                                    </header>
                                    <ol class="mt-2 divide-y divide-stone-100 px-4 text-sm">
                                        @foreach ($versi['baris'] as $n => $baris)
                                            @php
                                                $waktuUsul = $susunanVersi->waktu($n, $tahun);
                                            @endphp
                                            <li class="grid grid-cols-[1.25rem_minmax(0,1fr)] gap-x-2 py-2 sm:grid-cols-[1.25rem_minmax(0,1fr)_auto]">
                                                <span class="text-stone-400 tabular-nums">{{ $n + 1 }}</span>
                                                <span class="text-stone-800">{{ $baris['uraian'] }}</span>
                                                <span @class(['col-start-2 text-xs sm:col-start-auto sm:pt-0.5 sm:text-right sm:whitespace-nowrap', 'text-stone-600' => $waktuUsul, 'text-stone-400 italic' => ! $waktuUsul])>{{ $waktuUsul ?? 'bulan dipilih sendiri' }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                    <div class="flex justify-end border-t border-stone-100 px-4 py-2.5">
                                        <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="pakaiUsulan({{ $i }})" wire:loading.attr="disabled">
                                            <x-ui.icon name="check" class="size-3.5 text-brand-600" />
                                            Pakai {{ $versi['judul'] }}
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-200 px-4 py-3 sm:px-6">
                            <button type="button" class="btn-ghost" wire:click="tolakUsulan" title="Buang semua versi dan kembali ke permintaan">
                                <x-ui.icon name="x" class="size-4" />
                                Tolak semua
                            </button>
                            <button type="button" class="btn-secondary" wire:click="buatUsulJadwal" wire:loading.attr="disabled" wire:target="buatUsulJadwal">
                                <x-ui.icon name="sparkles" class="size-4 text-brand-600" />
                                Buat ulang
                            </button>
                        </footer>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
