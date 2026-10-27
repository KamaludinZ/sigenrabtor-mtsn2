@use('App\Support\Format')
@use('App\Support\MasterKode')

@php
    $baris = $this->terfilter;
    $dipakai = $this->dipakai;
    $ringkas = $this->ringkasan();
    $kelola = $this->bolehKelola();
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $namaLeading = array_column(MasterKode::leadings(), 'nama_unit', 'kode');
    $adaSaringan = $cari !== '' || $leading !== '' || $pemakaian !== '';
@endphp

<div class="mx-auto max-w-7xl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Data Pagu</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $tahun }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">
                Nama kegiatan dan pagu DIPA/Komite dari cascade Perkin. Saat membuat kegiatan, pilih dari sini agar nama dan pagu terisi otomatis.
                @unless ($kelola)
                    Data Pagu diatur admin.
                @endunless
            </p>
        </div>
        @if ($kelola)
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('pagu.template') }}" class="btn-ghost"><x-ui.icon name="download" class="size-4" /> Unduh template</a>
                <button type="button" wire:click="bukaImpor" class="btn-secondary" aria-expanded="{{ $imporTerbuka ? 'true' : 'false' }}" aria-controls="panel-impor-pagu">
                    <x-ui.icon name="sheet" class="size-4" /> Impor dari Excel
                </button>
                <button type="button" wire:click="tambah" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> Tambah data pagu</button>
            </div>
        @endif
    </div>

    @if ($imporTerbuka && $kelola)
        <div class="mt-5" id="panel-impor-pagu">
            <livewire:pagu.impor-pagu wire:key="impor-pagu" />
        </div>
    @endif

    @if ($ubahId !== null && $kelola)
        {{-- Formulir tambah / ubah --}}
        <form wire:submit="simpan" class="mt-5 rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" data-testid="formulir-pagu" wire:key="formulir-pagu-{{ $ubahId }}">
            <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-stone-900">{{ $ubahId === 'baru' ? 'Tambah data pagu' : 'Ubah data pagu' }}</h2>
                <p class="mt-0.5 text-sm text-stone-500">Nama ditulis tanpa awalan "Kegiatan"; nama yang sama tidak boleh dobel dalam satu tahun anggaran.</p>
            </header>
            <div class="grid gap-5 px-5 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                <x-ui.field class="sm:col-span-2" label="Nama kegiatan" for="pagu-nama" error="isian.nama_kegiatan" required>
                    <input type="text" id="pagu-nama" wire:model="isian.nama_kegiatan" maxlength="255" class="form-input" autocomplete="off"
                           @error('isian.nama_kegiatan') aria-invalid="true" aria-describedby="pagu-nama-error" @enderror>
                </x-ui.field>
                <x-ui.field class="sm:col-span-2" label="Leading" for="pagu-leading" error="isian.leading" hint="Kosongkan bila bisa dipilih semua leading.">
                    <select id="pagu-leading" wire:model="isian.leading" class="form-input">
                        <option value="">Tanpa leading</option>
                        @foreach (MasterKode::leadings() as $l)
                            <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field class="sm:col-span-2 lg:col-span-1" label="Pagu DIPA" for="pagu-dipa" error="isian.pagu_dipa">
                    <x-ui.rupiah id="pagu-dipa" model="isian.pagu_dipa" terbilang :nilai-awal="$isian['pagu_dipa']" />
                </x-ui.field>
                <x-ui.field class="sm:col-span-2 lg:col-span-1" label="Pagu Komite" for="pagu-komite" error="isian.pagu_komite">
                    <x-ui.rupiah id="pagu-komite" model="isian.pagu_komite" terbilang :nilai-awal="$isian['pagu_komite']" />
                </x-ui.field>
            </div>
            <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
                <button type="button" wire:click="batal" class="btn-ghost">Batal</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpan"><x-ui.icon name="save" class="size-4" /> Simpan</button>
            </div>
        </form>
    @endif

    {{-- Stats overview: mengikuti saringan daftar di bawahnya. --}}
    <section class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4" aria-label="Ringkasan data pagu" data-testid="ringkasan-pagu">
        <article class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" data-stat="jumlah">
            <h2 class="text-xs font-semibold text-stone-500">Data pagu</h2>
            <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums">{{ Format::angka($ringkas['jumlah']) }}</p>
            <p class="mt-2 text-xs text-stone-500">{{ $ringkas['sudah'] }} sudah jadi kegiatan</p>
        </article>
        <article class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" data-stat="belum">
            <h2 class="text-xs font-semibold text-stone-500">Belum jadi kegiatan</h2>
            <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums">{{ Format::angka($ringkas['belum']) }}</p>
            <p class="mt-2 text-xs text-stone-500">Siap dibuat kegiatan</p>
        </article>
        @foreach ($ringkas['pagu'] as $s => $total)
            <article class="col-span-2 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:col-span-1 sm:p-5" data-stat="{{ $s }}">
                <h2 class="flex items-center gap-1.5 text-xs font-semibold text-stone-500">Pagu <x-rab.lencana-sumber :sumber="$s" class="text-[10px]" /></h2>
                <p class="mt-1 text-2xl font-bold text-stone-900 tabular-nums"><x-ui.uang :nilai="$total" /></p>
                <p class="mt-2 text-xs text-stone-500">{{ $ringkas['berpagu'][$s] }} data berpagu {{ $s }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-label="Daftar data pagu" data-testid="daftar-pagu">
        <div class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-stone-900">Daftar data pagu</h2>
                <p class="mt-0.5 text-xs text-stone-500">{{ $adaSaringan ? 'Menampilkan '.count($baris).' dari '.count($this->semua).' data sesuai saringan.' : count($baris).' data TA '.$tahun.'.' }}</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-3 lg:w-[38rem]">
                <label class="sm:col-span-1">
                    <span class="sr-only">Cari nama kegiatan</span>
                    <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan…" class="form-input py-1.5" autocomplete="off">
                </label>
                <label>
                    <span class="sr-only">Saring leading</span>
                    <select wire:model.live="leading" class="form-input py-1.5" aria-label="Leading">
                        <option value="">Semua leading</option>
                        <option value="tanpa">Tanpa leading</option>
                        @foreach (MasterKode::leadings() as $l)
                            <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="sr-only">Saring pemakaian</span>
                    <select wire:model.live="pemakaian" class="form-input py-1.5" aria-label="Pemakaian">
                        <option value="">Semua status</option>
                        <option value="belum">Belum jadi kegiatan</option>
                        <option value="dipakai">Sudah jadi kegiatan</option>
                    </select>
                </label>
            </div>
        </div>

        @if ($baris === [])
            <div class="px-5 py-10 text-center sm:px-6" data-testid="pagu-kosong">
                <p class="text-sm font-medium text-stone-700">{{ $adaSaringan ? 'Tidak ada data pagu yang cocok.' : 'Belum ada data pagu TA '.$tahun.'.' }}</p>
                @if ($adaSaringan)
                    <button type="button" wire:click="hapusSaringan" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                @elseif ($kelola)
                    <p class="mt-1 text-sm text-stone-500">Tambah satu per satu atau impor dari Excel cascade Perkin.</p>
                @endif
            </div>
        @else
            {{-- Layar lebar: tabel --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                        <tr>
                            <th scope="col" class="w-10 py-2 pr-2 pl-5 text-right sm:pl-6">No</th>
                            <th scope="col" class="px-3 py-2 text-left">Nama kegiatan</th>
                            <th scope="col" class="px-3 py-2 text-left">Leading</th>
                            <th scope="col" class="px-3 py-2 text-right">Pagu DIPA</th>
                            <th scope="col" class="px-3 py-2 text-right">Pagu Komite</th>
                            <th scope="col" class="px-3 py-2 text-left">Status</th>
                            <th scope="col" class="px-5 py-2 sm:pr-6"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($baris as $i => $p)
                            @php
                                $kegiatan = $dipakai[$p['id']];
                            @endphp
                            <tr class="align-top" data-pagu="{{ $p['id'] }}" wire:key="pagu-{{ $p['id'] }}">
                                <td class="py-3 pr-2 pl-5 text-right text-xs text-stone-400 tabular-nums sm:pl-6">{{ $i + 1 }}</td>
                                <th scope="row" class="px-3 py-3 text-left font-medium text-stone-900">
                                    {{ $p['nama_kegiatan'] }}
                                    @if ($p['asal'] === 'impor')
                                        <span class="ml-1 rounded bg-stone-100 px-1.5 py-px text-[10px] font-semibold text-stone-500" title="Dari impor Excel">IMPOR</span>
                                    @endif
                                </th>
                                <td class="px-3 py-3 text-stone-600">{{ $p['leading'] ? $p['leading'].' '.($namaLeading[$p['leading']] ?? '') : '—' }}</td>
                                @foreach (['DIPA', 'Komite'] as $s)
                                    <td @class(['px-3 py-3 text-right whitespace-nowrap tabular-nums', 'text-stone-900' => $p['pagu'][$s] !== null, 'text-stone-300' => $p['pagu'][$s] === null])>{{ $p['pagu'][$s] !== null ? Format::rupiah($p['pagu'][$s]) : '—' }}</td>
                                @endforeach
                                <td class="px-3 py-3 text-xs">
                                    @if ($kegiatan)
                                        <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatan['id']]) }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-800" data-status-pagu="dipakai">
                                            <x-ui.icon name="check-circle" class="size-3.5" /> Sudah jadi kegiatan
                                        </a>
                                    @else
                                        <span class="text-stone-500" data-status-pagu="belum">Belum jadi kegiatan</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap sm:pr-6">
                                    @if ($akanDihapus === $p['id'])
                                        <span class="inline-flex items-center gap-1.5" role="group" aria-label="Konfirmasi hapus {{ $p['nama_kegiatan'] }}">
                                            <span class="text-xs text-stone-600">Hapus?</span>
                                            <button type="button" wire:click="$set('akanDihapus', null)" class="btn-ghost px-2 py-1 text-xs">Batal</button>
                                            <button type="button" wire:click="hapus" class="btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1">
                                            @unless ($kegiatan)
                                                <a href="{{ route('kegiatan.create', ['pagu' => $p['id']]) }}" wire:navigate class="btn-secondary px-2.5 py-1 text-xs">Buat kegiatan</a>
                                            @endunless
                                            @if ($kelola)
                                                <button type="button" wire:click="ubah('{{ $p['id'] }}')" class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800" aria-label="Ubah {{ $p['nama_kegiatan'] }}"><x-ui.icon name="pencil" class="size-4" /></button>
                                                <button type="button" wire:click="mintaHapus('{{ $p['id'] }}')" class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600" aria-label="Hapus {{ $p['nama_kegiatan'] }}"><x-ui.icon name="trash" class="size-4" /></button>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Layar sempit: kartu --}}
            <ul class="divide-y divide-stone-100 md:hidden">
                @foreach ($baris as $p)
                    @php
                        $kegiatan = $dipakai[$p['id']];
                    @endphp
                    <li class="px-4 py-3.5" data-pagu-kartu="{{ $p['id'] }}" wire:key="kartu-pagu-{{ $p['id'] }}">
                        <p class="text-sm font-medium text-stone-900">{{ $p['nama_kegiatan'] }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ \App\Support\Pagu\DataPagu::keterangan($p) }}</p>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                            @if ($kegiatan)
                                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatan['id']]) }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-brand-700"><x-ui.icon name="check-circle" class="size-3.5" /> Sudah jadi kegiatan</a>
                            @else
                                <a href="{{ route('kegiatan.create', ['pagu' => $p['id']]) }}" wire:navigate class="btn-secondary px-2.5 py-1 text-xs">Buat kegiatan</a>
                            @endif
                            @if ($kelola)
                                @if ($akanDihapus === $p['id'])
                                    <span class="inline-flex items-center gap-1.5" role="group" aria-label="Konfirmasi hapus {{ $p['nama_kegiatan'] }}">
                                        <button type="button" wire:click="$set('akanDihapus', null)" class="btn-ghost px-2 py-1 text-xs">Batal</button>
                                        <button type="button" wire:click="hapus" class="btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                    </span>
                                @else
                                    <span class="inline-flex gap-1">
                                        <button type="button" wire:click="ubah('{{ $p['id'] }}')" class="btn-ghost px-2 py-1 text-xs" aria-label="Ubah {{ $p['nama_kegiatan'] }}">Ubah</button>
                                        <button type="button" wire:click="mintaHapus('{{ $p['id'] }}')" class="btn-ghost px-2 py-1 text-xs text-red-700" aria-label="Hapus {{ $p['nama_kegiatan'] }}">Hapus</button>
                                    </span>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
