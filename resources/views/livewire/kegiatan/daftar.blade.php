@use('App\Enums\StatusKegiatan')
@use('App\Livewire\Kegiatan\Daftar')
@use('App\Support\Format')
@use('App\Support\Kegiatan\JadwalPelaksanaan')
@use('App\Support\MasterKode')
@use('Illuminate\Support\Carbon')

@php
    $baris = $this->halamanIni;
    $jumlah = count($this->terfilter);
    $aktif = $this->filterAktif();
    $ringkasan = $this->ringkasan;
    $mulai = $jumlah === 0 ? 0 : ($halaman - 1) * $perHalaman + 1;
    $sampai = min($jumlah, $halaman * $perHalaman);
    $jumlahHalaman = $this->jumlahHalaman();
    $statusPaguTampil = [
        'tanpa_sumber' => ['Sumber dana belum dipilih', 'text-stone-400'],
        'rab_kosong' => ['RAB belum diisi', 'text-gold-700'],
        'tanpa_pagu' => ['Pagu belum diisi', 'text-stone-500'],
        'sesuai' => ['Sesuai pagu', 'text-brand-700'],
        'sisa' => ['Di bawah pagu', 'text-brand-700'],
        'melebihi' => ['Melebihi pagu', 'text-red-600'],
    ];
    $kepala = fn (string $kolom, string $label, string $kelas = '') => compact('kolom', 'label', 'kelas');
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Data Kegiatan</h1>
            <p class="mt-1 text-sm text-stone-500">Tahun Anggaran {{ $tahun }} · {{ count($this->semuaBaris) }} kegiatan</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('kegiatan.template') }}" class="btn-secondary" data-testid="unduh-template">
                <x-ui.icon name="download" class="size-4" /> Unduh template
            </a>
            <a href="{{ route('kegiatan.salin_impor') }}" wire:navigate class="btn-secondary" data-testid="tautan-salin-impor">
                <x-ui.icon name="copy" class="size-4" /> Duplikat &amp; impor Perkin
            </a>
            <button type="button" wire:click="bukaImpor" class="btn-secondary" aria-expanded="{{ $imporTerbuka ? 'true' : 'false' }}" aria-controls="panel-impor">
                <x-ui.icon name="sheet" class="size-4" /> Impor dari Excel
            </button>
            <a href="{{ route('kegiatan.create') }}" wire:navigate class="btn-primary">
                <x-ui.icon name="plus" class="size-4" /> Kegiatan baru
            </a>
        </div>
    </div>

    @if ($imporTerbuka)
        <div class="mt-5" id="panel-impor">
            <livewire:kegiatan.impor-kegiatan wire:key="impor-kegiatan" />
        </div>
    @endif

    {{-- Ringkasan batas pagu (PRD F12): jumlah kegiatan per kondisi anggaran sebagai penyaring, dan RAB yang perlu ditindaklanjuti. --}}
    @php
        $jumlahPerKondisi = $this->jumlahPerKondisi;
        $perluPerhatian = $this->perluPerhatian;
    @endphp
    <section class="mt-6 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" aria-labelledby="judul-batas-pagu" data-testid="ringkasan-batas-pagu">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 id="judul-batas-pagu" class="text-sm font-semibold text-stone-900">Kondisi anggaran terhadap pagu</h2>
            <p class="text-xs text-stone-500">Jumlah kegiatan per kondisi; kegiatan dengan dua RAB masuk kondisi terberatnya. Pilih kondisi untuk menyaring tabel.</p>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5" role="group" aria-label="Saring menurut kondisi anggaran">
            @foreach (Daftar::STATUS_PAGU as $kunciKondisi => $labelKondisi)
                <button type="button" wire:click="saringKondisi('{{ $kunciKondisi }}')" aria-pressed="{{ $statusPagu === $kunciKondisi ? 'true' : 'false' }}"
                        data-testid="kondisi-{{ $kunciKondisi }}"
                        class="rounded-xl bg-white px-3 py-2.5 text-left ring-1 ring-stone-200 transition hover:ring-stone-300 aria-pressed:bg-stone-50 aria-pressed:ring-2 aria-pressed:ring-brand-700">
                    <span @class(['block text-lg font-bold tabular-nums', $jumlahPerKondisi[$kunciKondisi] > 0 ? $statusPaguTampil[$kunciKondisi][1] : 'text-stone-300'])>{{ $jumlahPerKondisi[$kunciKondisi] }}</span>
                    <span class="block text-xs text-stone-600">{{ $labelKondisi }}</span>
                </button>
            @endforeach
        </div>

        @if ($perluPerhatian !== [])
            <div class="mt-4" data-testid="perlu-perhatian">
                <h3 class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Perlu perhatian · {{ count($perluPerhatian) }} RAB</h3>
                <ul class="mt-2 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200">
                    @foreach (array_slice($perluPerhatian, 0, 5) as $p)
                        <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-3 py-2.5" data-testid="perhatian-{{ $p['id'] }}-{{ mb_strtolower($p['sumber']) }}">
                            <div class="min-w-0">
                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $p['id'], 'sumber' => $p['sumber']]) }}" wire:navigate
                                   class="text-sm font-medium text-stone-900 hover:text-brand-700">{{ $p['nama'] }}</a>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-stone-500">
                                    <x-rab.lencana-sumber :sumber="$p['sumber']" awalan="RAB" class="text-[10px]" />
                                    <span class="tabular-nums">{{ Format::rupiah($p['total']) }} · pagu {{ $p['pagu'] !== null ? Format::rupiah($p['pagu']) : 'belum diisi' }}</span>
                                </span>
                            </div>
                            <x-rab.status-pagu :status="$p['status']" class="text-xs" />
                        </li>
                    @endforeach
                </ul>
                @if (count($perluPerhatian) > 5)
                    <p class="mt-2 text-xs text-stone-500">dan {{ count($perluPerhatian) - 5 }} RAB lainnya — pilih “Melebihi pagu” atau “Pagu belum diisi” untuk melihat semuanya di tabel.</p>
                @endif
            </div>
        @endif
    </section>

    <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-label="Tabel kegiatan" x-data="{ filterTerbuka: false }">
        {{-- Cari & filter --}}
        <div class="space-y-3 border-b border-stone-100 px-4 py-4 sm:px-5">
            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1">
                    <label for="cari-kegiatan" class="sr-only">Cari nama kegiatan atau kode RKTM</label>
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" id="cari-kegiatan" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan atau kode RKTM…" class="form-input pl-9">
                </div>
                <button type="button" class="btn-secondary shrink-0 lg:hidden" x-on:click="filterTerbuka = ! filterTerbuka" x-bind:aria-expanded="filterTerbuka" aria-controls="filter-kegiatan">
                    <x-ui.icon name="layers" class="size-4" /> Filter
                    @if (count($aktif) - ($cari !== '' ? 1 : 0) > 0)
                        <span class="rounded-full bg-brand-700 px-1.5 text-[11px] font-semibold text-white">{{ count($aktif) - ($cari !== '' ? 1 : 0) }}</span>
                    @endif
                </button>
            </div>

            <div id="filter-kegiatan" class="hidden grid-cols-2 gap-3 sm:grid-cols-3 lg:grid lg:grid-cols-6" x-bind:class="{ 'grid': filterTerbuka, 'hidden': ! filterTerbuka }" data-testid="filter-kegiatan">
                @php
                    $pilihan = [
                        'leading' => ['Leading', ['belum' => 'Belum dipilih'] + collect(MasterKode::semuaLeading())->mapWithKeys(fn ($l) => [$l['kode'] => $l['nama_unit'].($l['is_active'] ? '' : ' (nonaktif)')])->all()],
                        'sumber' => ['Sumber dana', Daftar::SUMBER],
                        'status' => ['Status', collect(StatusKegiatan::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
                        'bulan' => ['Bulan pelaksanaan', JadwalPelaksanaan::NAMA_BULAN],
                        'statusPagu' => ['Kondisi anggaran', Daftar::STATUS_PAGU],
                        'asal' => ['Asal data', Daftar::ASAL],
                    ];
                @endphp
                @foreach ($pilihan as $properti => [$label, $opsi])
                    <div class="min-w-0">
                        <label for="filter-{{ $properti }}" class="mb-1 block text-xs font-medium text-stone-500">{{ $label }}</label>
                        <select id="filter-{{ $properti }}" wire:model.live="{{ $properti }}" @class(['form-input py-1.5 text-sm', 'ring-brand-400 bg-brand-50/40' => $this->{$properti} !== ''])>
                            <option value="">Semua</option>
                            @foreach ($opsi as $nilai => $teks)
                                <option value="{{ $nilai }}">{{ $teks }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @if ($aktif !== [])
                <div class="flex flex-wrap items-center gap-1.5" data-testid="filter-aktif">
                    <span class="text-xs text-stone-500">Filter aktif:</span>
                    @foreach ($aktif as $properti => $teks)
                        <button type="button" wire:click="hapusFilter('{{ $properti }}')"
                                class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-800 ring-1 ring-brand-200 ring-inset hover:bg-brand-100"
                                aria-label="Hapus filter {{ $teks }}">
                            {{ $teks }} <x-ui.icon name="x" class="size-3" />
                        </button>
                    @endforeach
                    <button type="button" wire:click="hapusSemuaFilter" class="ml-1 text-xs font-semibold text-stone-500 hover:text-stone-800">Hapus semua filter</button>
                </div>
            @endif
        </div>

        {{-- Ringkasan hasil --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 border-b border-stone-100 bg-stone-50/60 px-4 py-2.5 text-xs text-stone-600 sm:px-5" data-testid="ringkasan-daftar">
            <span><span class="font-semibold text-stone-900">{{ $ringkasan['kegiatan'] }}</span> kegiatan{{ $aktif !== [] ? ' sesuai filter' : '' }}</span>
            @foreach ($ringkasan['pagu'] as $namaSumber => $nilai)
                <span class="inline-flex items-center gap-1">Pagu <x-rab.lencana-sumber :sumber="$namaSumber" class="text-[10px]" /> <span class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($nilai) }}</span></span>
            @endforeach
            <span>Total RAB <span class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasan['total']) }}</span></span>
            @if ($ringkasan['melebihi'] > 0)
                <button type="button" wire:click="$set('statusPagu', 'melebihi')" class="inline-flex items-center gap-1 font-semibold text-red-600 hover:text-red-700">
                    <x-ui.icon name="alert" class="size-3.5" /> {{ $ringkasan['melebihi'] }} melebihi pagu
                </button>
            @endif
        </div>

        @if ($baris === [])
            <div class="px-6 py-14 text-center" data-testid="daftar-kosong">
                <x-ui.icon name="clipboard" class="mx-auto size-9 text-stone-300" />
                @if ($aktif !== [])
                    <p class="mt-2 text-sm font-semibold text-stone-800">Tidak ada kegiatan yang cocok dengan filter.</p>
                    <button type="button" wire:click="hapusSemuaFilter" class="btn-secondary mt-4">Hapus semua filter</button>
                @else
                    <p class="mt-2 text-sm font-semibold text-stone-800">Belum ada kegiatan.</p>
                    <p class="mt-0.5 text-xs text-stone-500">Buat kegiatan baru atau impor banyak kegiatan sekaligus dari Excel.</p>
                @endif
            </div>
        @else
            {{-- Tabel: di layar sempit bergulir mendatar, kolom Kegiatan tetap terlihat. --}}
            <p class="flex items-center gap-1 px-4 pt-2 text-[11px] text-stone-400 lg:hidden" aria-hidden="true">
                Geser tabel ke samping untuk melihat semua kolom <x-ui.icon name="chevron-right" class="size-3" />
            </p>
            <div class="overflow-x-auto" data-testid="tabel-kegiatan-wadah">
                <table class="w-full min-w-[60rem] text-left text-sm" data-testid="tabel-kegiatan">
                    <caption class="sr-only">Daftar kegiatan Tahun Anggaran {{ $tahun }}, {{ $jumlah }} kegiatan</caption>
                    <thead class="border-b border-stone-200 bg-stone-50 text-xs font-semibold text-stone-500">
                        <tr>
                            <th scope="col" class="w-10 px-3 py-2.5 text-right">No</th>
                            @foreach ([
                                $kepala('nama', 'Kegiatan', 'sticky left-0 z-10 bg-stone-50 min-w-[12rem] sm:min-w-[16rem]'),
                                $kepala('leading', 'Leading', 'min-w-[8rem]'),
                                $kepala('bulan', 'Bulan'),
                                $kepala('pagu', 'Sumber dana & pagu'),
                                $kepala('total', 'Total RAB', 'text-right'),
                                $kepala('status', 'Status'),
                                $kepala('diperbarui', 'Diubah'),
                            ] as $k)
                                @php $diurut = $urut === $k['kolom']; @endphp
                                <th scope="col" class="px-3 py-2.5 {{ $k['kelas'] }}"
                                    aria-sort="{{ $diurut ? ($arah === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <button type="button" wire:click="urutkan('{{ $k['kolom'] }}')"
                                            @class(['inline-flex items-center gap-1 hover:text-stone-800', 'text-stone-900' => $diurut])>
                                        {{ $k['label'] }}
                                        <x-ui.icon :name="$diurut && $arah === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-3 {{ $diurut ? '' : 'opacity-0' }}" />
                                    </button>
                                </th>
                            @endforeach
                            <th scope="col" class="px-3 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($baris as $b)
                            <tr class="group align-top hover:bg-stone-50/70" wire:key="kegiatan-{{ $b['id'] }}" data-kegiatan="{{ $b['id'] }}">
                                <td class="px-3 py-3 text-right text-stone-400 tabular-nums">{{ $mulai + $loop->index }}</td>
                                <td class="sticky left-0 z-10 bg-white px-3 py-3 group-hover:bg-stone-50">
                                    <a href="{{ route('kegiatan.edit', ['kegiatan' => $b['id']]) }}" wire:navigate class="font-semibold text-stone-900 hover:text-brand-800">{{ $b['nama'] }}</a>
                                    <span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
                                        @if ($b['kode_rktm'])
                                            <span class="font-mono" title="Kode RKTM">{{ $b['kode_rktm'] }}</span>
                                        @else
                                            <span class="text-stone-400">Kode RKTM belum dipilih</span>
                                        @endif
                                        @if ($b['asal'] === 'impor')
                                            <span class="rounded bg-stone-100 px-1.5 py-px text-[10px] font-semibold text-stone-500" title="Dibuat lewat impor Excel">IMPOR</span>
                                        @endif
                                        <x-kode.lencana-hilang :hilang="$b['kode_hilang']" :href="route('kegiatan.kode', ['kegiatan' => $b['id']])" />
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-stone-700">
                                    @if ($b['leading_nama'])
                                        {{ $b['leading_nama'] }} <span class="block text-xs text-stone-400">{{ $b['leading'] }}</span>
                                    @else
                                        <span class="text-stone-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-stone-700" title="{{ $b['bulan_lengkap'] }}">{{ $b['bulan_teks'] ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @forelse ($b['sumber'] as $s)
                                        <span class="flex items-center gap-1.5 whitespace-nowrap">
                                            <x-rab.lencana-sumber :sumber="$s['sumber']" class="text-[10px]" />
                                            <span class="text-stone-700 tabular-nums">{{ $s['pagu'] !== null ? Format::rupiah($s['pagu']) : 'pagu belum diisi' }}</span>
                                        </span>
                                    @empty
                                        <span class="text-xs text-stone-400">Belum dipilih</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <span class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($b['total']) }}</span>
                                    <span @class(['block text-xs font-medium', $statusPaguTampil[$b['status_pagu']][1]]) data-status-pagu="{{ $b['status_pagu'] }}">
                                        {{ $statusPaguTampil[$b['status_pagu']][0] }}
                                    </span>
                                    @if ($b['item'] > 0)
                                        <span class="block text-[11px] text-stone-400">{{ $b['item'] }} item</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$b['status']->value" />
                                    @if ($b['kunci'])
                                        <span class="mt-1 block text-[11px] whitespace-nowrap text-stone-500" title="{{ $b['kunci']->kalimat() }}" data-testid="jejak-kunci-baris">
                                            {{ $b['kunci']->pada ? 'Terkunci '.Carbon::parse($b['kunci']->pada)->locale('id')->translatedFormat('j M Y') : 'Terkunci' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs text-stone-600">
                                    @if ($b['diperbarui'])
                                        @php $waktu = Carbon::parse($b['diperbarui'])->locale('id'); @endphp
                                        <time datetime="{{ $waktu->toIso8601String() }}" title="{{ $waktu->translatedFormat('l, j F Y H.i') }}">{{ $waktu->translatedFormat('j M Y') }}</time>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end">
                                        @foreach ([['kegiatan.edit', 'Data'], ['kegiatan.kode', 'Kode'], ['kegiatan.rab', 'RAB']] as [$rute, $label])
                                            <a href="{{ route($rute, ['kegiatan' => $b['id']]) }}" wire:navigate title="Buka {{ $label }} — {{ $b['nama'] }}"
                                               aria-label="{{ $label }} {{ $b['nama'] }}"
                                               class="rounded-md px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800">{{ $label }}</a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-stone-200 bg-stone-50 text-sm">
                        <tr>
                            <th scope="row" colspan="4" class="sticky left-0 bg-stone-50 px-3 py-2.5 text-left text-xs font-semibold text-stone-600">
                                Jumlah {{ $jumlah }} kegiatan{{ $aktif !== [] ? ' sesuai filter' : '' }}
                            </th>
                            <td class="px-3 py-2.5 font-semibold text-stone-900 tabular-nums" data-testid="total-pagu-daftar">{{ Format::rupiah(array_sum($ringkasan['pagu'])) }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-stone-900 tabular-nums" data-testid="total-rab-daftar">{{ Format::rupiah($ringkasan['total']) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Halaman --}}
            <div class="flex flex-col gap-3 border-t border-stone-100 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="flex items-center gap-2 text-xs text-stone-500">
                    <span data-testid="posisi-halaman">Menampilkan {{ $mulai }}–{{ $sampai }} dari {{ $jumlah }}</span>
                    <label for="per-halaman" class="ml-2">Per halaman</label>
                    <select id="per-halaman" wire:model.live="perHalaman" class="form-input w-auto py-1 text-xs">
                        @foreach (Daftar::PER_HALAMAN as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($jumlahHalaman > 1)
                    <nav class="flex items-center gap-1" aria-label="Halaman tabel">
                        <button type="button" wire:click="keHalaman({{ $halaman - 1 }})" @disabled($halaman <= 1) class="btn-ghost px-2 py-1 text-xs disabled:opacity-40">Sebelumnya</button>
                        @foreach (range(1, $jumlahHalaman) as $n)
                            @if ($n === 1 || $n === $jumlahHalaman || abs($n - $halaman) <= 1)
                                <button type="button" wire:click="keHalaman({{ $n }})" @if ($n === $halaman) aria-current="page" @endif
                                        @class(['min-w-8 rounded-md px-2 py-1 text-xs font-semibold tabular-nums', 'bg-brand-700 text-white' => $n === $halaman, 'text-stone-600 hover:bg-stone-100' => $n !== $halaman])>{{ $n }}</button>
                            @elseif (abs($n - $halaman) === 2)
                                <span class="px-1 text-stone-400">…</span>
                            @endif
                        @endforeach
                        <button type="button" wire:click="keHalaman({{ $halaman + 1 }})" @disabled($halaman >= $jumlahHalaman) class="btn-ghost px-2 py-1 text-xs disabled:opacity-40">Berikutnya</button>
                    </nav>
                @endif
            </div>
        @endif
    </section>
</div>
