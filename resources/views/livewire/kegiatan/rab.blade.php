@use('App\Support\Format')
@use('App\Support\Stub\StubRab')

@php
    $pagu = $this->pagu();
    $selisih = $pagu !== null ? $pagu - $this->total : null;
    $persen = $pagu ? min(100, round($this->total / $pagu * 100)) : 0;
    $perKategori = collect($this->rincian)->groupBy('kategori')->map(fn ($baris) => $baris->sum('jumlah'));
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
            <span>Data Kegiatan</span>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="text-stone-700">RAB</span>
        </nav>
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Rencana Anggaran Biaya</h1>
            <x-ui.status-badge :status="$this->status()->value" />
        </div>
        <p class="mt-1 text-sm text-stone-500">{{ $dataKegiatan['nama_kegiatan'] }}</p>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="rab" :kegiatan-id="$kegiatanId" />
    </div>

    @if ($this->daftarSumber() === [])
        <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center" data-testid="rab-tanpa-sumber">
            <x-ui.icon name="wallet" class="mx-auto size-10 text-stone-300" />
            <p class="mt-3 text-base font-semibold text-stone-800">Sumber dana belum dipilih</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-stone-500">RAB disusun terpisah untuk tiap sumber dana. Pilih DIPA, Komite, atau keduanya beserta pagunya di Data Kegiatan.</p>
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#sumber-dana" class="btn-primary mt-5">
                <x-ui.icon name="wallet" class="size-4" />
                Atur sumber dana
            </a>
        </div>
    @else
        {{-- Pilih RAB per sumber dana --}}
        <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="RAB per sumber dana">
            @foreach ($dataKegiatan['sumber_dana'] as $baris)
                @php $aktif = $baris['sumber'] === $sumber; @endphp
                <button type="button" role="tab" aria-selected="{{ $aktif ? 'true' : 'false' }}"
                        wire:click="pilihSumber('{{ $baris['sumber'] }}')"
                        @class([
                            'min-w-40 rounded-xl px-4 py-2.5 text-left ring-1 transition',
                            'bg-brand-800 text-white ring-brand-800 shadow-sm' => $aktif,
                            'bg-white text-stone-700 ring-stone-200 hover:ring-stone-300' => ! $aktif,
                        ])>
                    <span class="block text-sm font-semibold">RAB {{ $baris['sumber'] }}</span>
                    <span @class(['block text-xs', 'text-brand-100' => $aktif, 'text-stone-500' => ! $aktif])>
                        Pagu {{ $baris['pagu'] ? Format::rupiah($baris['pagu']) : 'belum diisi' }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="min-w-0 space-y-6">
                {{-- Header RAB (format resmi) --}}
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="header-rab">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Rencana Anggaran Belanja</p>
                        <h2 class="mt-0.5 text-base font-bold text-stone-900 uppercase">Kegiatan {{ $dataKegiatan['nama_kegiatan'] }}</h2>
                    </header>
                    <dl class="grid gap-x-6 gap-y-3 px-5 py-4 text-sm sm:grid-cols-2 sm:px-6">
                        @foreach ([
                            'Leading sektor' => $this->header['leading'],
                            'Nama program kegiatan' => $this->header['program'],
                            'Kode anggaran' => $this->header['kode_anggaran'],
                            'Tahun anggaran' => $this->header['tahun'],
                            'Sumber dana' => $sumber,
                            'Jumlah yang diajukan' => Format::rupiah($this->total).',-',
                        ] as $label => $nilai)
                            <div class="min-w-0">
                                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                                <dd class="font-medium text-stone-900">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                {{-- Rincian belanja --}}
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="rincian-rab">
                    <header class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-stone-900">Rincian belanja</h2>
                            <p class="text-sm text-stone-500">{{ count($this->rincian) }} baris · jumlah = volume × harga satuan</p>
                        </div>
                    </header>

                    @if ($this->rincian === [])
                        <div class="px-6 py-10 text-center">
                            <x-ui.icon name="coins" class="mx-auto size-8 text-stone-300" />
                            <p class="mt-2 text-sm font-medium text-stone-700">Belum ada rincian belanja untuk RAB {{ $sumber }}</p>
                            <p class="mt-0.5 text-xs text-stone-500">Rincian ditambahkan baris demi baris; jumlah, total, dan terbilang dihitung otomatis.</p>
                        </div>
                    @else
                        {{-- Tabel (layar sedang ke atas) --}}
                        <div class="hidden overflow-x-auto sm:block">
                            <table class="w-full text-sm">
                                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                                    <tr>
                                        <th scope="col" class="w-10 px-4 py-2.5 text-left">No</th>
                                        <th scope="col" class="px-2 py-2.5 text-left">Kode</th>
                                        <th scope="col" class="px-2 py-2.5 text-left">Uraian</th>
                                        <th scope="col" class="px-2 py-2.5 text-right">Vol</th>
                                        <th scope="col" class="px-2 py-2.5 text-left">Satuan</th>
                                        <th scope="col" class="px-2 py-2.5 text-right">Harga satuan</th>
                                        <th scope="col" class="px-4 py-2.5 text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    @foreach ($this->rincian as $item)
                                        <tr class="align-top hover:bg-stone-50/60">
                                            <td class="px-4 py-2.5 text-stone-500 tabular-nums">{{ $loop->iteration }}.</td>
                                            <td class="px-2 py-2.5 font-mono text-xs text-stone-500">{{ $item['kode'] }}</td>
                                            <td class="px-2 py-2.5 text-stone-800">
                                                {{ $item['uraian'] }}
                                                <span class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-500 uppercase">{{ StubRab::KATEGORI[$item['kategori']] ?? $item['kategori'] }}</span>
                                            </td>
                                            <td class="px-2 py-2.5 text-right tabular-nums">{{ Format::angka($item['volume']) }}</td>
                                            <td class="px-2 py-2.5 text-stone-600">{{ $item['satuan'] }}</td>
                                            <td class="px-2 py-2.5 text-right tabular-nums">{{ Format::angka($item['harga_satuan']) }}</td>
                                            <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ Format::angka($item['jumlah']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-stone-200 bg-stone-50">
                                        <th scope="row" colspan="6" class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-stone-600 uppercase">Total keseluruhan</th>
                                        <td class="px-4 py-3 text-right text-base font-bold text-stone-900 tabular-nums" data-testid="total-rab">{{ Format::angka($this->total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Kartu (layar kecil) --}}
                        <ol class="divide-y divide-stone-100 sm:hidden">
                            @foreach ($this->rincian as $item)
                                <li class="px-5 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="min-w-0 text-sm text-stone-800"><span class="text-stone-400">{{ $loop->iteration }}.</span> {{ $item['uraian'] }}</p>
                                        <p class="shrink-0 text-sm font-semibold tabular-nums">{{ Format::angka($item['jumlah']) }}</p>
                                    </div>
                                    <p class="mt-0.5 text-xs text-stone-500 tabular-nums">
                                        {{ Format::angka($item['volume']) }} {{ $item['satuan'] }} × {{ Format::rupiah($item['harga_satuan']) }} · {{ $item['kode'] }}
                                    </p>
                                </li>
                            @endforeach
                            <li class="flex items-center justify-between bg-stone-50 px-5 py-3">
                                <span class="text-xs font-semibold text-stone-600 uppercase">Total</span>
                                <span class="text-base font-bold tabular-nums">{{ Format::rupiah($this->total) }}</span>
                            </li>
                        </ol>
                    @endif
                </section>
            </div>

            {{-- Ringkasan --}}
            <aside class="space-y-4">
                <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" data-testid="ringkasan-rab">
                    <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Ringkasan RAB {{ $sumber }}</p>
                    <p class="mt-2 text-2xl font-bold text-stone-900 tabular-nums">{{ Format::rupiah($this->total) }}</p>
                    <p class="text-xs text-stone-500">Total RAB = total KAK = jumlah yang diajukan</p>

                    <div class="mt-4 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-stone-500">Pagu</span><span class="font-medium tabular-nums">{{ $pagu !== null ? Format::rupiah($pagu) : '—' }}</span></div>
                        @if ($selisih !== null)
                            <div class="flex justify-between">
                                <span class="text-stone-500">{{ $selisih >= 0 ? 'Sisa pagu' : 'Melebihi pagu' }}</span>
                                <span @class(['font-semibold tabular-nums', 'text-brand-700' => $selisih >= 0, 'text-red-600' => $selisih < 0])>{{ Format::rupiah(abs($selisih)) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                <div @class(['h-full rounded-full', 'bg-brand-600' => $selisih >= 0, 'bg-red-500' => $selisih < 0]) style="width: {{ $persen }}%"></div>
                            </div>
                        @endif
                    </div>

                    @if ($perKategori->isNotEmpty())
                        <div class="mt-4 border-t border-stone-100 pt-3">
                            <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Per kelompok belanja</p>
                            <dl class="mt-2 space-y-1 text-sm">
                                @foreach (StubRab::KATEGORI as $kunci => $label)
                                    <div class="flex justify-between">
                                        <dt class="text-stone-500">{{ $label }}</dt>
                                        <dd class="tabular-nums">{{ Format::rupiah($perKategori[$kunci] ?? 0) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>

                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="btn-secondary w-full">
                    <x-ui.icon name="clipboard" class="size-4" />
                    Kembali ke Data Kegiatan
                </a>
            </aside>
        </div>
    @endif
</div>
