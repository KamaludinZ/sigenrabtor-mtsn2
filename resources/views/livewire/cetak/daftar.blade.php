@use('App\Support\Dokumen\CetakDokumen')
@use('App\Support\Dokumen\DaftarCetak')
@use('App\Support\Format')

@php
    $semua = $this->semua;
    $baris = $this->baris;
    $ringkasan = DaftarCetak::ringkasan($semua);
    $dokumen = $this->dokumen;
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $pilihanLeading = collect($semua)->unique('leading')->filter(fn ($b) => $b['leading'] !== '')->sortBy('leading', SORT_NATURAL)->mapWithKeys(fn ($b) => [$b['leading'] => $b['leading'].($b['nama_leading'] ? ' · '.$b['nama_leading'] : '')])->all();
    $pilihanSumber = collect($semua)->pluck('sumber')->filter()->unique()->values()->all();
    $adaSaringan = $cari !== '' || $leading !== '' || $sumber !== '' || $keadaan !== '';
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Cetak &amp; Unduh</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $tahun }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Cetak cover, KAK/TOR, dan RAB tiap kegiatan per sumber dana. Dokumen yang belum lengkap atau belum disetujui tetap bisa dicetak dengan tanda air DRAF.</p>
        </div>
        <a href="{{ route('buku.index') }}" wire:navigate class="btn-secondary shrink-0 self-start sm:self-auto" data-testid="tautan-buku">
            <x-ui.icon name="book" class="size-4" /> Cetak buku per leading
        </a>
    </div>

    {{-- Ringkasan sekaligus saringan keadaan --}}
    <nav class="mt-6 grid grid-cols-3 gap-3" aria-label="Keadaan cetak" data-testid="ringkasan-cetak">
        @foreach (['' => ['Semua dokumen', $ringkasan['jumlah'], 'text-stone-900'], 'siap' => [DaftarCetak::KEADAAN['siap'], $ringkasan['siap'], 'text-brand-800'], 'draf' => [DaftarCetak::KEADAAN['draf'], $ringkasan['draf'], 'text-red-700']] as $kunci => [$label, $angka, $warna])
            <button type="button" wire:click="$set('keadaan', '{{ $kunci }}')" aria-pressed="{{ $keadaan === $kunci ? 'true' : 'false' }}" data-keadaan-saring="{{ $kunci === '' ? 'semua' : $kunci }}"
                    @class(['rounded-2xl p-3 text-left ring-1 transition sm:p-4', 'bg-white shadow-xs ring-2 ring-brand-600' => $keadaan === $kunci, 'bg-white/70 ring-stone-200 hover:bg-white hover:ring-stone-300' => $keadaan !== $kunci])>
                <span class="block text-xs font-semibold text-stone-500">{{ $label }}</span>
                <span class="mt-1 block text-xl font-bold tabular-nums sm:text-2xl {{ $warna }}">{{ $angka }}</span>
            </button>
        @endforeach
    </nav>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        {{-- Daftar dokumen --}}
        <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-daftar-cetak" data-testid="daftar-cetak">
            <header class="space-y-3 border-b border-stone-100 px-4 py-4 sm:px-5">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 id="judul-daftar-cetak" class="text-base font-semibold text-stone-900">Dokumen kegiatan</h2>
                    <span class="text-xs text-stone-500 tabular-nums" data-testid="jumlah-tampil">{{ count($baris) }} dari {{ $ringkasan['jumlah'] }}</span>
                </div>
                <label class="block">
                    <span class="sr-only">Cari kegiatan</span>
                    <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan…" class="form-input py-1.5" autocomplete="off" data-testid="cari-cetak">
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="sr-only">Saring leading</span>
                        <select wire:model.live="leading" class="form-input py-1.5" data-testid="saring-leading">
                            <option value="">Semua leading</option>
                            @foreach ($pilihanLeading as $kode => $labelLeading)
                                <option value="{{ $kode }}">{{ $labelLeading }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Saring sumber dana</span>
                        <select wire:model.live="sumber" class="form-input py-1.5" data-testid="saring-sumber">
                            <option value="">Semua sumber</option>
                            @foreach ($pilihanSumber as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </header>

            @if ($baris === [])
                <div class="px-5 py-10 text-center" data-testid="cetak-kosong">
                    <x-ui.icon name="file-text" class="mx-auto size-8 text-stone-300" />
                    <p class="mt-2 text-sm font-semibold text-stone-700">{{ $adaSaringan ? 'Tidak ada dokumen yang cocok dengan saringan.' : 'Belum ada kegiatan TA '.$tahun.'.' }}</p>
                    @if ($adaSaringan)
                        <button type="button" wire:click="hapusSaringan" data-testid="hapus-saringan-cetak" class="mt-3 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                    @endif
                </div>
            @else
                <ul class="max-h-[40rem] divide-y divide-stone-100 overflow-y-auto" role="list">
                    @foreach ($baris as $b)
                        @php
                            $aktif = $dokumen !== null && $dokumen['baris']['kunci'] === $b['kunci'];
                        @endphp
                        <li wire:key="cetak-{{ $b['kunci'] }}">
                            <button type="button" wire:click="pilih('{{ $b['kunci'] }}')" aria-current="{{ $aktif ? 'true' : 'false' }}" data-testid="dokumen-cetak" data-kunci="{{ $b['kunci'] }}" data-keadaan="{{ $b['keadaan'] }}"
                                    @class(['flex w-full items-start gap-3 px-4 py-3 text-left transition sm:px-5', 'bg-brand-50/70' => $aktif, 'hover:bg-stone-50' => ! $aktif])>
                                <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-brand-100 text-brand-700' => $b['keadaan'] === 'siap', 'bg-red-50 text-red-600' => $b['keadaan'] === 'draf'])>
                                    <x-ui.icon :name="$b['keadaan'] === 'siap' ? 'printer' : 'alert'" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-2 text-sm font-semibold text-stone-900">{{ $b['nama'] }}</span>
                                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
                                        @if ($b['sumber'])
                                            <x-rab.lencana-sumber :sumber="$b['sumber']" awalan="RAB" />
                                        @else
                                            <span class="rounded bg-stone-100 px-1.5 py-0.5 font-semibold text-stone-600">Tanpa sumber dana</span>
                                        @endif
                                        <x-ui.status-badge :status="$b['status']" />
                                        @if ($b['leading'] !== '')
                                            <span>Leading {{ $b['leading'] }}</span>
                                        @endif
                                    </span>
                                    @if ($b['keadaan'] === 'draf' && $b['alasan'] !== [])
                                        <span class="mt-1 block truncate text-xs text-red-700">DRAF: {{ $b['alasan'][0] }}@if (count($b['alasan']) > 1) <span class="text-stone-500">(+{{ count($b['alasan']) - 1 }})</span>@endif</span>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Dokumen terpilih --}}
        {{-- Layar sempit: panel ada di bawah daftar — gulirkan ke sini begitu dokumen dipilih. --}}
        <section class="min-w-0 scroll-mt-4" aria-label="Dokumen terpilih" data-testid="panel-cetak-terpilih"
                 x-data x-on:dokumen-cetak-dipilih.window="if (window.matchMedia('(max-width: 1023px)').matches) $nextTick(() => $el.scrollIntoView({ block: 'start' }))">
            @if ($dokumen === null)
                <div class="rounded-2xl bg-white px-5 py-10 text-center text-sm text-stone-500 shadow-xs ring-1 ring-stone-200">Pilih dokumen di daftar untuk melihat lembar yang akan dicetak.</div>
            @else
                @php
                    $b = $dokumen['baris'];
                    $draf = $dokumen['cetak']->set->draf;
                @endphp
                <div class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" wire:key="terpilih-{{ $b['kunci'] }}">
                    <header class="border-b border-stone-100 px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-stone-900" data-testid="judul-terpilih">{{ $b['nama'] }}</h2>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $b['sumber'] ? 'RAB '.$b['sumber'] : 'Tanpa sumber dana' }} · {{ $b['label_status'] }}@if ($b['sumber']) · {{ $b['rincian'] }} rincian · <x-ui.uang :nilai="$b['total']" />@endif</p>
                            </div>
                            <span @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1', 'bg-red-50 text-red-700 ring-red-200' => $draf, 'bg-brand-50 text-brand-800 ring-brand-200' => ! $draf]) data-testid="keadaan-terpilih" data-keadaan="{{ $draf ? 'draf' : 'siap' }}">
                                <x-ui.icon :name="$draf ? 'alert' : 'check-circle'" class="size-3.5" /> {{ $draf ? 'Tercetak sebagai DRAF' : 'Siap dicetak' }}
                            </span>
                        </div>

                        @if ($draf)
                            <ul class="mt-3 space-y-1.5 rounded-xl bg-red-50/60 px-3 py-2.5 text-xs ring-1 ring-red-100" data-testid="alasan-draf-cetak">
                                @foreach ($dokumen['saran'] as $s)
                                    <li class="text-red-800">
                                        <span class="font-semibold">{{ $s['teks'] }}</span>
                                        <a href="{{ $s['tautan'] }}" wire:navigate class="ml-1 text-brand-700 underline decoration-brand-300 underline-offset-2 hover:text-brand-800">{{ $s['saran'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </header>

                    <div class="space-y-4 px-4 py-4 sm:px-5">
                        {{-- Lembar yang dicetak --}}
                        <fieldset>
                            <legend class="text-xs font-semibold text-stone-600">Lembar yang dicetak</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (CetakDokumen::LEMBAR as $kunciLembar => $labelLembar)
                                    @php
                                        $tersedia = in_array($kunciLembar, $b['lembar'], true);
                                        $terpilih = in_array($kunciLembar, $dokumen['lembar'], true);
                                    @endphp
                                    <button type="button" wire:click="aturLembar('{{ $kunciLembar }}')" @disabled(! $tersedia) aria-pressed="{{ $terpilih ? 'true' : 'false' }}" data-lembar-pilih="{{ $kunciLembar }}"
                                            @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ring-1 transition disabled:cursor-not-allowed disabled:opacity-50', 'bg-brand-50 text-brand-800 ring-brand-300' => $terpilih, 'text-stone-600 ring-stone-200 hover:bg-stone-50' => ! $terpilih])>
                                        <x-ui.icon :name="$terpilih ? 'check' : 'plus'" class="size-3.5" /> {{ $labelLembar }}
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>

                        <div>
                            <p class="text-xs font-semibold text-stone-600">Nama berkas</p>
                            <p class="mt-1 break-all rounded-lg bg-stone-50 px-3 py-2 font-mono text-xs text-stone-700 ring-1 ring-stone-200" data-testid="nama-berkas-terpilih">{{ $dokumen['nama_berkas'] }}</p>
                        </div>

                        {{-- Aksi cetak & unduh --}}
                        <div class="flex flex-wrap gap-2" data-testid="aksi-cetak">
                            <a href="{{ $dokumen['tautan_cetak'] }}" target="_blank" rel="noopener" class="btn-primary" data-testid="cetak-terpilih">
                                <x-ui.icon name="printer" class="size-4" /> Cetak / Simpan PDF
                            </a>
                            <a href="{{ $dokumen['pratinjau'] }}" wire:navigate class="btn-secondary" data-testid="buka-pratinjau">
                                <x-ui.icon name="eye" class="size-4" /> Pratinjau lengkap
                            </a>
                            @if ($b['sumber'] && $b['rincian'] > 0)
                                <a href="{{ route('kegiatan.rab.excel', ['kegiatan' => $b['kegiatan_id'], 'sumber' => $b['sumber']]) }}" class="btn-secondary" data-testid="unduh-excel-terpilih">
                                    <x-ui.icon name="download" class="size-4" /> RAB Excel
                                </a>
                                <a href="{{ route('kegiatan.rab.pdf', ['kegiatan' => $b['kegiatan_id'], 'sumber' => $b['sumber']]) }}" class="btn-secondary" data-testid="unduh-pdf-terpilih">
                                    <x-ui.icon name="download" class="size-4" /> RAB PDF
                                </a>
                            @endif
                            <span class="btn-secondary cursor-not-allowed opacity-60" aria-disabled="true" data-testid="unduh-word-terpilih">
                                <x-ui.icon name="file-text" class="size-4" /> Word <x-ui.segera />
                            </span>
                        </div>
                    </div>

                    {{-- Pratinjau kecil lembar terpilih (komponen yang sama dengan halaman cetak) --}}
                    <div class="border-t border-stone-100 bg-stone-100/70 px-3 py-4 sm:px-5">
                        <p class="mb-3 text-xs font-semibold text-stone-600">Pratinjau lembar</p>
                        <div class="max-h-[40rem] space-y-4 overflow-y-auto overflow-x-hidden" data-testid="pratinjau-lembar-cetak">
                            @foreach ($dokumen['lembar'] as $kunciLembar)
                                <div class="mx-auto w-[210mm] [zoom:0.4] sm:[zoom:0.55] lg:[zoom:0.6]" data-lembar-mini="{{ $kunciLembar }}" wire:key="mini-{{ $b['kunci'] }}-{{ $kunciLembar }}">
                                    <div class="lembar-cetak">
                                        @switch($kunciLembar)
                                            @case('cover')
                                                <x-dokumen.cover :gambar="$dokumen['cover']['gambar']" :teks="$dokumen['cover']['teks']" :nilai="$dokumen['cover']['nilai']" :draf="$draf" :jangkar="false" class="shadow-md" />
                                                @break
                                            @case('kak')
                                                <x-dokumen.kak-tor :susunan="$dokumen['kak']" :bagian="range(1, 9)" class="shadow-md" />
                                                @break
                                            @default
                                                <x-dokumen.rab :susunan="$dokumen['rab']" class="shadow-md" />
                                        @endswitch
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
