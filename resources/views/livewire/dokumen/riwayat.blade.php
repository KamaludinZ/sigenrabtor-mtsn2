@use('App\Support\Dokumen\RiwayatDokumen')
@use('App\Support\Format')

@php
    $semua = $this->semua;
    $telusur = $this->penelusuran;
    $ringkasan = $telusur->ringkasan();
    [$perTahun, $jumlahButir] = $this->isi();
    $saringan = $telusur->kriteria;
    $chip = $telusur->label();
    $aktif = $telusur->aktif();
    $adaSaringan = $aktif || $saringan['terbalik'];
    $hitungJenis = $telusur->pilihan('jenis');
    $hitungFormat = $telusur->pilihan('format');
    $dariSemua = fn (string $kunci, string $keterangan) => $aktif ? 'dari '.$ringkasan['semua'][$kunci].' '.$keterangan : null;
    $tampil = min($this->batas, $jumlahButir);
    $waktu = fn (string $iso) => Format::tanggal(substr($iso, 0, 10)).' · '.\Carbon\CarbonImmutable::parse($iso)->setTimezone(config('app.timezone'))->format('H.i');
    $nadaStatus = ['final' => 'bg-stone-800 text-white ring-stone-800', 'disetujui' => 'bg-brand-50 text-brand-800 ring-brand-200'];
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Riwayat Dokumen</h1>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Semua KAK/TOR, RAB, dan set dokumen yang pernah dicetak, diunduh, atau diarsipkan — per kegiatan dan tahun anggaran. Dokumen tahun lalu tetap tersimpan walau kegiatannya sudah tidak ada.</p>
        </div>
        <a href="{{ route('cetak.index') }}" wire:navigate class="btn-secondary shrink-0 self-start sm:self-auto" data-testid="tautan-cetak">
            <x-ui.icon name="printer" class="size-4" /> Cetak &amp; Unduh
        </a>
    </div>

    {{-- Ringkasan --}}
    {{-- Selama ada kata kunci/saringan angka mengikuti hasilnya ("dari …" = seluruh catatan) --}}
    <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="ringkasan-riwayat" data-tersaring="{{ $aktif ? '1' : '0' }}">
        @foreach ([
            'jumlah' => ['Dokumen', $ringkasan['jumlah'], $dariSemua('jumlah', 'dokumen') ?? $ringkasan['tahun'].' tahun anggaran'],
            'kegiatan' => ['Kegiatan', $ringkasan['kegiatan'], $dariSemua('kegiatan', 'kegiatan') ?? 'dengan dokumen tercatat'],
            'arsip' => ['Arsip final', $ringkasan['arsip'], $dariSemua('arsip', 'arsip final') ?? 'PDF bertanda tangan'],
            'draf' => ['Bercap DRAF', $ringkasan['draf'], $dariSemua('draf', 'bercap DRAF') ?? 'dicetak sebelum siap'],
        ] as $kunci => [$label, $angka, $keterangan])
            <div @class(['rounded-2xl p-4 shadow-xs ring-1', 'bg-white ring-stone-200' => ! $aktif, 'bg-brand-50/40 ring-brand-200' => $aktif]) data-ringkasan="{{ $kunci }}">
                <dt class="text-xs font-semibold text-stone-500">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-bold text-stone-900 tabular-nums">{{ $angka }}</dd>
                <dd class="mt-0.5 text-xs text-stone-500">{{ $keterangan }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($semua !== [])
        {{-- Tampilan & urutan --}}
        <div class="mt-6 flex flex-wrap items-start justify-between gap-3" data-testid="alat-riwayat">
            <x-ui.kotak-cari model="cari" :nilai="$cari" wire:model.live.debounce.300ms="cari" label="Cari dokumen" placeholder="Cari kegiatan, nama berkas, nomor dokumen, leading…"
                             :jumlah="$chip === [] ? count($telusur->cocok) : null" :total="$telusur->total" satuan="dokumen" testid="cari-riwayat" class="w-full sm:max-w-md sm:flex-1" />
            <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Tampilan" data-testid="tampilan-riwayat" data-tampilan="{{ $tampilan }}">
                @foreach (\App\Livewire\Dokumen\Riwayat::TAMPILAN as $kunciTampilan => $labelTampilan)
                    <button type="button" wire:click="$set('tampilan', '{{ $kunciTampilan }}')" aria-pressed="{{ $tampilan === $kunciTampilan ? 'true' : 'false' }}" data-pilih-tampilan="{{ $kunciTampilan }}"
                            @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition', 'bg-white text-stone-900 shadow-xs' => $tampilan === $kunciTampilan, 'text-stone-500 hover:text-stone-800' => $tampilan !== $kunciTampilan])>
                        <x-ui.icon :name="$kunciTampilan === 'kegiatan' ? 'clipboard' : 'history'" class="size-4" /> {{ $labelTampilan }}
                    </button>
                @endforeach
            </div>
            @if ($tampilan === 'kronologis')
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <span>Urutkan</span>
                    <select wire:model.live="urut" class="form-input w-auto py-1.5" data-testid="urut-riwayat">
                        @foreach (RiwayatDokumen::URUT as $kunciUrut => $labelUrut)
                            <option value="{{ $kunciUrut }}">{{ $labelUrut }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            </div>
        </div>

        {{-- Saringan jenis, format, rentang tanggal dibuat; x-data tetap (nilai awal lewat data-buka-awal) agar panel tidak tertutup saat saringan berubah --}}
        <div class="mt-3 rounded-2xl bg-white p-3 shadow-xs ring-1 ring-stone-200 sm:p-4" data-testid="saringan-riwayat" data-buka-awal="{{ $chip !== [] || $saringan['terbalik'] ? '1' : '0' }}" x-data="{ buka: $el.dataset.bukaAwal === '1' }">
            <button type="button" class="flex w-full items-center justify-between text-sm font-semibold text-stone-700 sm:hidden" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" data-testid="buka-saringan-riwayat">
                <span class="inline-flex items-center gap-1.5"><x-ui.icon name="filter" class="size-4" /> Saringan @if (count($chip) > 0)<span class="rounded-full bg-brand-100 px-1.5 text-[11px] text-brand-800">{{ count($chip) }}</span>@endif</span>
                <x-ui.icon name="chevron-down" class="size-4 transition" x-bind:class="{ 'rotate-180': buka, 'rotate-0': ! buka }" />
            </button>
            <div class="mt-3 gap-3 sm:mt-0 sm:grid sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]" x-bind:class="{ 'grid': buka, 'hidden': ! buka }">
                <label class="block">
                    <span class="form-label mb-1 text-xs">Jenis dokumen</span>
                    <select wire:model.live="jenis" class="form-input py-1.5" data-testid="saring-jenis">
                        <option value="">Semua jenis ({{ array_sum($hitungJenis) }})</option>
                        @foreach (\App\Support\Dokumen\DokumenKegiatan::JENIS as $kunciJenis => $labelJenis)
                            <option value="{{ $kunciJenis }}" data-jumlah="{{ $hitungJenis[$kunciJenis] }}">{{ $labelJenis }} ({{ $hitungJenis[$kunciJenis] }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="form-label mb-1 text-xs">Format berkas</span>
                    <select wire:model.live="format" class="form-input py-1.5" data-testid="saring-format">
                        <option value="">Semua format ({{ array_sum($hitungFormat) }})</option>
                        @foreach (RiwayatDokumen::FORMAT as $kunciFormat => [$labelFormat])
                            <option value="{{ $kunciFormat }}" data-jumlah="{{ $hitungFormat[$kunciFormat] }}">{{ $labelFormat }} ({{ $hitungFormat[$kunciFormat] }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="form-label mb-1 text-xs">Dibuat sejak</span>
                    <input type="date" wire:model.live.blur="dari" max="{{ $saringan['sampai'] ?: '' }}" class="form-input py-1.5" data-testid="saring-dari">
                </label>
                <label class="block">
                    <span class="form-label mb-1 text-xs">Sampai</span>
                    <input type="date" wire:model.live.blur="sampai" min="{{ $saringan['dari'] ?: '' }}" class="form-input py-1.5" data-testid="saring-sampai">
                </label>
                <div class="flex items-end gap-1.5 sm:col-span-2 lg:col-span-1">
                    <button type="button" wire:click="pintasanTanggal('30hari')" class="btn-ghost px-2.5 py-1.5 text-xs" data-testid="pintasan-30hari">30 hari terakhir</button>
                    <button type="button" wire:click="pintasanTanggal('ta')" class="btn-ghost px-2.5 py-1.5 text-xs" data-testid="pintasan-ta">TA {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }}</button>
                </div>
            </div>
            @if ($saringan['terbalik'])
                <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-700" role="alert" data-testid="rentang-terbalik">
                    <x-ui.icon name="alert" class="size-3.5" /> Tanggal akhir sebelum tanggal awal — rentang tanggal belum diterapkan sampai diperbaiki.
                </p>
            @endif
        </div>

        @if ($adaSaringan)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-testid="chip-saringan-riwayat">
                <span class="text-xs text-stone-500" aria-live="polite" data-testid="hasil-riwayat" data-jumlah="{{ count($telusur->cocok) }}">
                    <span class="font-semibold text-stone-800 tabular-nums">{{ count($telusur->cocok) }}</span> dari {{ $telusur->total }} dokumen<span class="sr-only">{{ $telusur->keterangan() ? ' — '.$telusur->keterangan() : '' }}</span>:
                </span>
                @if ($cari !== '')
                    <button type="button" wire:click="$set('cari', '')" class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2.5 py-1 text-xs font-semibold text-gold-800 ring-1 ring-gold-200 hover:bg-gold-100" data-testid="chip-saringan" data-kunci="cari">
                        “{{ $cari }}” <x-ui.icon name="x" class="size-3" /><span class="sr-only">Lepas kata kunci</span>
                    </button>
                @endif
                @foreach ($chip as $kunciChip => $labelChip)
                    <button type="button" wire:click="lepasSaringan('{{ $kunciChip }}')" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-100" data-testid="chip-saringan" data-kunci="{{ $kunciChip }}">
                        {{ $labelChip }} <x-ui.icon name="x" class="size-3" /><span class="sr-only">Lepas saringan</span>
                    </button>
                @endforeach
                <button type="button" wire:click="hapusSaringan" class="text-xs font-semibold text-stone-600 underline underline-offset-2 hover:text-stone-900" data-testid="hapus-saringan-riwayat">Hapus semua</button>
            </div>
        @endif
    @endif

    @if ($semua !== [] && $this->cocok === [])
        <div class="mt-4 rounded-2xl bg-white px-5 py-10 text-center shadow-xs ring-1 ring-stone-200" data-testid="riwayat-tanpa-hasil">
            <x-ui.icon name="search" class="mx-auto size-8 text-stone-300" />
            <p class="mt-2 text-sm font-semibold text-stone-700">{{ $cari !== '' ? 'Tidak ada dokumen yang cocok dengan “'.$cari.'”'.($chip !== [] ? ' dan saringan yang dipilih.' : '.') : 'Tidak ada dokumen yang cocok dengan saringan yang dipilih.' }}</p>
            <p class="mt-1 text-sm text-stone-500">Coba kata lain atau longgarkan saringan — mis. sebagian nama kegiatan, kode leading, atau rentang tanggal yang lebih lebar.</p>
            <button type="button" wire:click="hapusSaringan" class="mt-3 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus pencarian &amp; saringan</button>
        </div>
    @elseif ($semua === [])
        <div class="mt-6 rounded-2xl bg-white px-5 py-12 text-center shadow-xs ring-1 ring-stone-200" data-testid="riwayat-kosong">
            <x-ui.icon name="history" class="mx-auto size-9 text-stone-300" />
            <p class="mt-3 text-sm font-semibold text-stone-700">Belum ada dokumen yang tercatat.</p>
            <p class="mt-1 text-sm text-stone-500">Dokumen tercatat di sini setelah dicetak, diunduh, atau arsip finalnya diunggah.</p>
            <a href="{{ route('cetak.index') }}" wire:navigate class="btn-primary mt-4"><x-ui.icon name="printer" class="size-4" /> Buka Cetak &amp; Unduh</a>
        </div>
    @else
        <div class="mt-4 space-y-6">
            @foreach ($perTahun as $tahun => $baris)
                <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tahun-{{ $tahun }}" data-testid="riwayat-tahun" data-tahun="{{ $tahun }}" wire:key="tahun-{{ $tahun }}">
                    <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-4 py-3.5 sm:px-5">
                        <h2 id="judul-tahun-{{ $tahun }}" class="text-base font-semibold text-stone-900">Tahun Anggaran {{ $tahun }}</h2>
                        <span class="text-xs text-stone-500 tabular-nums">{{ $tampilan === 'kegiatan' ? count($baris).' kegiatan · '.array_sum(array_column($baris, 'jumlah')).' dokumen' : count($baris).' dokumen' }}</span>
                    </header>

                    @if ($tampilan === 'kegiatan')
                        @include('livewire.dokumen.partials.per-kegiatan', ['kegiatan' => $baris])
                    @else

                    {{-- Layar lebar: tabel --}}
                    <div class="hidden lg:block">
                        <table class="w-full table-fixed text-left text-sm">
                            <colgroup><col class="w-[34%]"><col class="w-[26%]"><col class="w-[14%]"><col class="w-[14%]"><col class="w-[12%]"></colgroup>
                            <thead class="border-b border-stone-100 text-xs font-semibold text-stone-500">
                                <tr>
                                    <th scope="col" class="px-5 py-2.5">Dokumen</th>
                                    <th scope="col" class="px-3 py-2.5">Kegiatan</th>
                                    <th scope="col" class="px-3 py-2.5">Status saat itu</th>
                                    <th scope="col" class="px-3 py-2.5">Dibuat</th>
                                    <th scope="col" class="px-3 py-2.5 text-right">Berkas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($baris as $d)
                                    <tr data-testid="dokumen-riwayat" data-id="{{ $d['id'] }}" data-format="{{ $d['format'] }}" wire:key="d-{{ $d['id'] }}">
                                        <td class="px-5 py-3 align-top">
                                            <div class="flex items-start gap-2.5">
                                                <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-red-50 text-red-700' => $d['format'] === 'pdf', 'bg-sky-50 text-sky-700' => $d['format'] === 'docx', 'bg-brand-50 text-brand-700' => $d['format'] === 'xlsx'])>
                                                    <x-ui.icon :name="$d['ikon_format']" class="size-4" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-stone-900">{{ $d['label_jenis'] }} <span class="font-normal text-stone-500">· {{ $d['label_format'] }} · v{{ $d['versi'] }}</span></p>
                                                    <p class="mt-0.5 truncate font-mono text-[11px] text-stone-500" title="{{ $d['nama_berkas'] }}">{!! RiwayatDokumen::sorot($d['nama_berkas'], $cari) !!}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <p class="line-clamp-2 text-stone-800">{!! RiwayatDokumen::sorot($d['nama_kegiatan'], $cari) !!}</p>
                                            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
                                                @if ($d['sumber'])
                                                    <x-rab.lencana-sumber :sumber="$d['sumber']" />
                                                @endif
                                                @if ($d['kode_leading'] !== '')
                                                    <span title="{{ $d['nama_leading'] }}">Leading {{ $d['kode_leading'] }}</span>
                                                @endif
                                            </p>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1', $nadaStatus[$d['status']] ?? 'bg-stone-100 text-stone-700 ring-stone-300'])>{{ $d['label_status'] }}</span>
                                            @if ($d['bertanda_draf'])
                                                <span class="ml-1 text-[11px] font-bold tracking-wider text-red-700">DRAF</span>
                                            @endif
                                            @if ($d['nomor_dokumen'])
                                                <p class="mt-1 font-mono text-[11px] text-stone-600">{!! RiwayatDokumen::sorot($d['nomor_dokumen'], $cari) !!}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 align-top text-xs text-stone-600">
                                            <p class="whitespace-nowrap">{{ $waktu($d['dibuat_pada']) }}</p>
                                            <p class="mt-0.5 text-stone-500">{{ $d['label_asal'] }} oleh {{ $d['dibuat_oleh'] }}</p>
                                        </td>
                                        <td class="px-3 py-3 text-right align-top text-xs text-stone-500 tabular-nums">{{ $d['ukuran_teks'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Layar sempit: kartu --}}
                    <ul class="divide-y divide-stone-100 lg:hidden" role="list">
                        @foreach ($baris as $d)
                            <li class="px-4 py-3.5" data-testid="kartu-riwayat" data-id="{{ $d['id'] }}" wire:key="k-{{ $d['id'] }}">
                                <div class="flex items-start gap-3">
                                    <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-red-50 text-red-700' => $d['format'] === 'pdf', 'bg-sky-50 text-sky-700' => $d['format'] === 'docx', 'bg-brand-50 text-brand-700' => $d['format'] === 'xlsx'])>
                                        <x-ui.icon :name="$d['ikon_format']" class="size-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-stone-900">{{ $d['label_jenis'] }} <span class="font-normal text-stone-500">· {{ $d['label_format'] }} · v{{ $d['versi'] }}</span></p>
                                        <p class="mt-0.5 line-clamp-2 text-sm text-stone-700">{!! RiwayatDokumen::sorot($d['nama_kegiatan'], $cari) !!}</p>
                                        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
                                            @if ($d['sumber'])
                                                <x-rab.lencana-sumber :sumber="$d['sumber']" />
                                            @endif
                                            <span @class(['inline-flex items-center rounded-full px-2 py-0.5 font-semibold ring-1', $nadaStatus[$d['status']] ?? 'bg-stone-100 text-stone-700 ring-stone-300'])>{{ $d['label_status'] }}</span>
                                            @if ($d['bertanda_draf'])
                                                <span class="font-bold tracking-wider text-red-700">DRAF</span>
                                            @endif
                                        </p>
                                        <p class="mt-1.5 text-xs text-stone-500">{{ $waktu($d['dibuat_pada']) }} · {{ $d['label_asal'] }} oleh {{ $d['dibuat_oleh'] }}@if ($d['ukuran_teks']) · {{ $d['ukuran_teks'] }}@endif</p>
                                        <p class="mt-0.5 truncate font-mono text-[11px] text-stone-400">{!! RiwayatDokumen::sorot($d['nama_berkas'], $cari) !!}</p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($jumlahButir > $tampil)
            <div class="mt-4 text-center">
                <button type="button" wire:click="tampilkanLebih" class="btn-secondary" data-testid="tampilkan-lebih-riwayat">
                    Tampilkan {{ min(\App\Livewire\Dokumen\Riwayat::PER_HALAMAN, $jumlahButir - $tampil) }} lagi
                    <span class="font-normal text-stone-500">({{ $tampil }} dari {{ $jumlahButir }} {{ $tampilan === 'kegiatan' ? 'kegiatan' : 'dokumen' }})</span>
                </button>
            </div>
        @endif
    @endif
</div>
