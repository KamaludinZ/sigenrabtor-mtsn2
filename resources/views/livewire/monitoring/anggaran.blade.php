@use('App\Enums\StatusKegiatan')
@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\MasterKode')

@php
    $ringkasan = $this->ringkasan;
    $perSumber = $ringkasan->perSumber();
    $total = $ringkasan->total();
    $perStatus = $ringkasan->perStatus();
    $aktif = $this->saringanAktif();
    $sumberTampil = $sumber === '' ? SumberDana::urutan() : [$sumber];
    $bagianBerikut = [
        ['kunci' => 'leading', 'judul' => 'Total per leading', 'isi' => 'Pagu, total RAB, dan selisih tiap leading — DIPA dan Komite terpisah.', 'lebar' => false],
        ['kunci' => 'kelompok', 'judul' => 'Rekap per kelompok belanja', 'isi' => 'Total belanja bahan, jasa, dan lain-lain seluruh kegiatan.', 'lebar' => false],
        ['kunci' => 'kegiatan', 'judul' => 'Rincian per kegiatan', 'isi' => 'Tabel kegiatan dengan pagu, total RAB, selisih, dan status, diurutkan dari anggaran terbesar.', 'lebar' => true],
        ['kunci' => 'operator', 'judul' => 'Rekap per operator', 'isi' => 'Jumlah kegiatan dan anggaran per pembuat kegiatan.', 'lebar' => true],
    ];
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Monitoring Anggaran</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $this->tahun() }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Penggunaan anggaran seluruh kegiatan dan leading tahun ini: pagu dari Cascade Perkin dibandingkan dengan total RAB tiap sumber dana.</p>
        </div>
        <button type="button" class="btn-secondary shrink-0" disabled title="Ekspor Excel & PDF sedang disiapkan">
            <x-ui.icon name="download" class="size-4" /> Ekspor
            <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
        </button>
    </div>

    {{-- Saringan --}}
    <section class="mt-6 rounded-2xl bg-white px-5 py-4 shadow-xs ring-1 ring-stone-200 sm:px-6" aria-label="Saringan monitoring" data-testid="saringan-monitoring">
        <div class="grid gap-3 sm:grid-cols-3">
            <label>
                <span class="form-label">Leading</span>
                <select wire:model.live="leading" class="form-input">
                    <option value="">Semua leading</option>
                    @foreach (MasterKode::leadings() as $l)
                        <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="form-label">Sumber dana</span>
                <select wire:model.live="sumber" class="form-input">
                    <option value="">DIPA &amp; Komite</option>
                    @foreach (SumberDana::urutan() as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="form-label">Status dokumen</span>
                <select wire:model.live="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach (StatusKegiatan::cases() as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($aktif !== [])
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs" data-testid="saringan-aktif">
                @foreach ($aktif as $kunci => $label)
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 py-0.5 pr-1 pl-2.5 font-medium text-brand-800 ring-1 ring-brand-200">
                        {{ $label }}
                        <button type="button" wire:click="hapusSaringan('{{ $kunci }}')" class="rounded-full p-0.5 hover:bg-brand-100" aria-label="Lepas saringan {{ $label }}"><x-ui.icon name="x" class="size-3" /></button>
                    </span>
                @endforeach
                <button type="button" wire:click="hapusSaringan" class="font-semibold text-brand-700 hover:text-brand-800">Hapus semua</button>
            </div>
        @endif
    </section>

    {{-- Ringkasan tahun aktif --}}
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan anggaran" data-testid="ringkasan-monitoring">
        <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-kegiatan">
            <h2 class="text-xs font-semibold text-stone-500">Kegiatan</h2>
            <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums">{{ Format::angka($ringkasan->jumlahKegiatan()) }}</p>
            <ul class="mt-3 flex flex-wrap gap-1.5" aria-label="Kegiatan per status">
                @foreach ($perStatus as $kunciStatus => $jumlahStatus)
                    @if ($jumlahStatus > 0)
                        <li class="inline-flex items-center gap-1" data-status-jumlah="{{ $kunciStatus }}">
                            <x-ui.status-badge :status="$kunciStatus" /> <span class="text-xs font-semibold text-stone-600 tabular-nums">{{ $jumlahStatus }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-pagu">
            <h2 class="text-xs font-semibold text-stone-500">Total pagu</h2>
            <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums"><x-ui.uang :nilai="$total['pagu']" /></p>
            <dl class="mt-3 space-y-1 text-xs">
                @foreach ($sumberTampil as $s)
                    <div class="flex justify-between gap-2"><dt><x-rab.lencana-sumber :sumber="$s" /></dt><dd class="font-semibold text-stone-700 tabular-nums">{{ Format::rupiah($perSumber[$s]['pagu']) }}</dd></div>
                @endforeach
            </dl>
            @if ($total['tanpa_pagu'] > 0)
                <p class="mt-2 text-[11px] font-medium text-gold-700">{{ $total['tanpa_pagu'] }} RAB belum berpagu</p>
            @endif
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" data-testid="kartu-rab">
            <h2 class="text-xs font-semibold text-stone-500">Total RAB</h2>
            <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums"><x-ui.uang :nilai="$total['total']" /></p>
            <dl class="mt-3 space-y-1 text-xs">
                @foreach ($sumberTampil as $s)
                    <div class="flex justify-between gap-2"><dt><x-rab.lencana-sumber :sumber="$s" /></dt><dd class="font-semibold text-stone-700 tabular-nums">{{ Format::rupiah($perSumber[$s]['total']) }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-2 text-[11px] text-stone-500">{{ $total['rab'] }} RAB dari {{ $ringkasan->jumlahKegiatan() }} kegiatan</p>
        </article>
        <article @class(['rounded-2xl p-5 shadow-xs ring-1', 'bg-red-50 ring-red-200' => $total['melebihi'] > 0, 'bg-white ring-stone-200' => $total['melebihi'] === 0]) data-testid="kartu-selisih">
            <h2 class="text-xs font-semibold text-stone-500">{{ $total['selisih'] >= 0 ? 'Sisa pagu' : 'Melebihi pagu' }}</h2>
            <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-stone-900' => $total['selisih'] >= 0, 'text-red-700' => $total['selisih'] < 0])><x-ui.uang :nilai="abs($total['selisih'])" /></p>
            <p class="mt-3 text-xs text-stone-600">Pagu dikurangi total RAB seluruh kegiatan.</p>
            @if ($total['melebihi'] > 0)
                <p class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-red-700" data-testid="jumlah-melebihi"><x-ui.icon name="alert" class="size-3.5" /> {{ $total['melebihi'] }} RAB melebihi pagunya</p>
            @endif
        </article>
    </section>

    {{-- Bagian rekap (diisi bertahap) --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ($bagianBerikut as $bagian)
            <section id="monitoring-{{ $bagian['kunci'] }}" class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200 {{ $bagian['lebar'] ? 'lg:col-span-2' : '' }}"
                     aria-labelledby="judul-monitoring-{{ $bagian['kunci'] }}" data-testid="bagian-monitoring-{{ $bagian['kunci'] }}">
                <header class="flex items-center justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <h2 id="judul-monitoring-{{ $bagian['kunci'] }}" class="text-base font-semibold text-stone-900">{{ $bagian['judul'] }}</h2>
                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
                </header>
                <p class="px-5 py-6 text-sm text-stone-500 sm:px-6">{{ $bagian['isi'] }}</p>
            </section>
        @endforeach
    </div>
</div>
