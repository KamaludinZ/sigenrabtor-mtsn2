@use('App\Enums\SumberDana')
@use('App\Support\Dokumen\BukuDokumen')
@use('App\Support\Format')

@php
    $tahun = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $terpilih = $this->terpilih;
    $isi = $this->isi;
    $bukuIni = $terpilih ? collect($this->peta)->first(fn ($b) => $b['leading'] === $terpilih['leading'] && $b['sumber'] === $terpilih['sumber']) : null;
    $jumlahDraf = $isi ? count(array_filter($isi['baris'], fn ($b) => $b['draf'])) : 0;
    $admin = \App\Support\Stub\StubData::currentUser()['role'] === 'admin';
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Buku Dokumen</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $tahun }}
            </span>
        </div>
        <p class="mt-1 max-w-3xl text-sm text-stone-500">Satu buku per leading &amp; sumber dana: cover buku, daftar kegiatan bernomor halaman, lalu KAK/TOR &amp; RAB tiap kegiatan — digabung menjadi satu PDF siap cetak.</p>
    </div>

    @if ($terpilih === null)
        <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center" data-testid="buku-kosong">
            <x-ui.icon name="book" class="mx-auto size-10 text-stone-300" />
            <p class="mt-3 text-sm font-medium text-stone-700">Akun ini belum tertaut ke leading mana pun.</p>
            <p class="mt-1 text-sm text-stone-500">Minta admin menautkan akun Anda di Pengaturan › Leading &amp; User.</p>
        </div>
    @else
        {{-- Parameter buku --}}
        <section class="mt-6 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" aria-label="Parameter buku" data-testid="parameter-buku">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">
                <label class="block min-w-0">
                    <span class="text-xs font-semibold text-stone-600">Leading</span>
                    <select wire:model.live="leading" class="form-input mt-1" aria-label="Leading">
                        @if ($admin)
                            <option value="">Otomatis — buku pertama yang berisi kegiatan</option>
                        @endif
                        @foreach ($this->leadings as $l)
                            <option value="{{ $l['kode'] }}" @selected($l['kode'] === $terpilih['leading'] && $leading !== '')>{{ $l['kode'] }} · {{ $l['nama_unit'] }}{{ $l['is_active'] ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                </label>
                <div>
                    <span class="block text-xs font-semibold text-stone-600" id="label-sumber-buku">Sumber dana</span>
                    <div class="mt-1 inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-labelledby="label-sumber-buku">
                        @foreach (SumberDana::cases() as $s)
                            <button type="button" wire:click="$set('sumber', '{{ $s->value }}')" aria-pressed="{{ $terpilih['sumber'] === $s->value ? 'true' : 'false' }}"
                                    @class(['rounded-md px-4 py-1.5 text-sm font-semibold transition', 'bg-white text-stone-900 shadow-xs' => $terpilih['sumber'] === $s->value, 'text-stone-500 hover:text-stone-800' => $terpilih['sumber'] !== $s->value])>{{ $s->value }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" disabled><x-ui.icon name="download" class="size-4" /> Gabung PDF <x-ui.segera /></button>
                    @if ($isi['baris'] === [] || ($proses['tahap'] ?? null) === 'ambil')
                        <button type="button" class="btn-primary" disabled wire:key="susun-nonaktif"><x-ui.icon name="layers" class="size-4" /> Susun buku</button>
                    @else
                        <button type="button" wire:click="susun" class="btn-primary" wire:key="susun-aktif" data-testid="susun-buku"><x-ui.icon name="layers" class="size-4" /> Susun buku</button>
                    @endif
                </div>
            </div>
            <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500" data-testid="nama-berkas-buku">
                <x-ui.icon name="file-text" class="size-3.5" />
                <span class="font-mono text-stone-700">{{ BukuDokumen::namaBerkas($terpilih['leading'], $terpilih['sumber']) }}</span>
                <span>· {{ $bukuIni['jumlah'] }} kegiatan · Total RAB {{ Format::rupiah($isi['total']) }} · ± {{ $isi['simulasi']['terakhir'] }} halaman</span>
            </p>
        </section>

        @if ($proses)
            @include('livewire.buku.partials.proses-susun')
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            {{-- Susunan buku --}}
            <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200 lg:col-start-1 lg:row-start-1" aria-labelledby="judul-susunan" data-testid="susunan-buku" wire:loading.class="opacity-60" wire:target="leading,sumber,pilih">
                <header class="flex flex-wrap items-start justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-susunan" class="text-base font-semibold text-stone-900">{{ $terpilih['leading'] }} · {{ $bukuIni['nama_leading'] }} — {{ $terpilih['sumber'] }}</h2>
                        <p class="mt-0.5 text-sm text-stone-500">{{ $isi['daftar_isi']['judul'] }} urut {{ mb_lcfirst(\App\Support\Dokumen\DaftarIsi::URUTAN[$isi['daftar_isi']['urutan']]) }}; nomor halaman mengikuti pengaturan nomor halaman TA {{ $tahun }}.</p>
                    </div>
                    @if ($isi['baris'] !== [])
                        <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', 'bg-red-50 text-red-700 ring-red-200' => $jumlahDraf > 0, 'bg-brand-50 text-brand-800 ring-brand-200' => $jumlahDraf === 0]) data-testid="keadaan-buku">
                            {{ $jumlahDraf > 0 ? $jumlahDraf.' kegiatan masih DRAF' : 'Semua siap dicetak' }}
                        </span>
                    @endif
                </header>

                <ol class="divide-y divide-stone-100 text-sm">
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-stone-600 sm:px-6" data-bagian-buku="cover">
                        <span class="flex items-center gap-2"><x-ui.icon name="book" class="size-4 text-stone-400" /> Cover buku</span>
                        <span class="text-xs text-stone-400">tanpa nomor</span>
                    </li>
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-stone-600 sm:px-6" data-bagian-buku="daftar-isi">
                        <span class="flex items-center gap-2"><x-ui.icon name="clipboard" class="size-4 text-stone-400" /> {{ \Illuminate\Support\Str::title(mb_strtolower($isi['daftar_isi']['judul'])) }}</span>
                        <span class="text-xs text-stone-400">{{ $isi['simulasi']['halaman'][1]['label'] ?? 'tanpa nomor' }}</span>
                    </li>
                    @forelse ($isi['baris'] as $b)
                        <li class="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 px-5 py-3 sm:px-6" data-kegiatan="{{ $b['id'] }}" wire:key="buku-{{ $terpilih['leading'] }}-{{ $terpilih['sumber'] }}-{{ $b['id'] }}">
                            <span class="pt-0.5 text-xs font-semibold text-stone-400 tabular-nums">{{ $b['no'] }}.</span>
                            <div class="min-w-0">
                                <a href="{{ route('kegiatan.pratinjau', ['kegiatan' => $b['id'], 'sumber' => $terpilih['sumber']]) }}" wire:navigate class="font-medium text-stone-900 hover:text-brand-800">{{ $b['nama_kegiatan'] }}</a>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
                                    <span class="font-mono">{{ $b['kode_rktm'] ?? 'Kode RKTM belum dipilih' }}</span>
                                    <span>· {{ Format::rupiah($b['total']) }}</span>
                                    <x-ui.status-badge :status="$b['status']->value" />
                                    @if ($b['draf'])
                                        <span class="rounded-full bg-red-50 px-1.5 py-px text-[10px] font-semibold text-red-700 ring-1 ring-red-200" data-draf>DRAF</span>
                                    @endif
                                </p>
                            </div>
                            <span class="pt-0.5 text-right text-xs text-stone-500 tabular-nums" data-halaman>hal. {{ $b['halaman'] ?? '…' }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center sm:px-6" data-testid="buku-tanpa-kegiatan">
                            <p class="text-sm font-medium text-stone-700">Belum ada kegiatan {{ $bukuIni['nama_leading'] }} yang dibiayai {{ $terpilih['sumber'] }} tahun ini.</p>
                            <p class="mt-1 text-sm text-stone-500">Kegiatan masuk buku ini begitu sumber dana {{ $terpilih['sumber'] }} dipilih di Data Kegiatan.</p>
                        </li>
                    @endforelse
                </ol>
            </section>

            {{-- Halaman daftar kegiatan buku ini (konfigurasi daftar isi + halaman awal dari simulasi nomor halaman). --}}
            <section class="min-w-0 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5 lg:col-start-1" aria-labelledby="judul-halaman-daftar-isi" data-testid="halaman-daftar-isi">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="judul-halaman-daftar-isi" class="text-sm font-semibold text-stone-900">Halaman daftar kegiatan</h2>
                    @if ($admin)
                        <a href="{{ route('pengaturan.template.index', ['tab' => 'daftar_isi']) }}" wire:navigate class="text-xs font-semibold text-brand-700 hover:text-brand-800">Atur tampilan</a>
                    @endif
                </div>
                <div class="mt-3 overflow-x-auto">
                    <x-dokumen.daftar-isi :halaman="$isi['halaman_daftar_isi']" :nomor="$isi['simulasi']['halaman'][1]['label'] ?? null" :posisi="$isi['nomor_halaman']['posisi']" class="min-w-[20rem] max-w-md" />
                </div>
                <p class="mt-3 text-xs text-stone-500">Kolom Halaman memakai perkiraan dari pengaturan nomor halaman ({{ \App\Support\Dokumen\NomorHalaman::HALAMAN_KEGIATAN['cover'] + \App\Support\Dokumen\NomorHalaman::HALAMAN_KEGIATAN['kak'] + \App\Support\Dokumen\NomorHalaman::HALAMAN_KEGIATAN['rab'] }} halaman per kegiatan); angka pastinya dihitung saat PDF digabung.</p>
            </section>

            <aside class="space-y-4 lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-label="Cover & pengaturan buku">
                {{-- Halaman cover buku: desain cover default tahun aktif berisi nilai buku; klik untuk memperbesar. --}}
                <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-cover-buku" data-testid="cover-buku"
                         x-data="{ besar: false }" x-on:keydown.escape.window="besar = false">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 id="judul-cover-buku" class="text-sm font-semibold text-stone-900">Cover buku</h2>
                        <button type="button" x-on:click="besar = true" class="text-xs font-semibold text-brand-700 hover:text-brand-800" data-testid="perbesar-cover-buku">Perbesar</button>
                    </div>
                    <button type="button" x-on:click="besar = true" class="mt-3 block w-full text-left" aria-label="Perbesar cover buku">
                        <x-dokumen.cover-buku :leading="$terpilih['leading']" :sumber="$terpilih['sumber']" :draf="$jumlahDraf > 0" />
                    </button>
                    <p class="mt-2 text-xs text-stone-500">Memakai desain cover default TA {{ $tahun }}{{ $admin ? '' : ' yang diatur admin' }}.</p>

                    <div x-cloak x-show="besar" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/70 p-4" role="dialog" aria-modal="true" aria-label="Cover buku" data-testid="cover-buku-besar" x-on:click.self="besar = false">
                        <div class="w-full max-w-md">
                            <x-dokumen.cover-buku :leading="$terpilih['leading']" :sumber="$terpilih['sumber']" :draf="$jumlahDraf > 0" />
                            <div class="mt-3 flex justify-center">
                                <button type="button" x-on:click="besar = false" class="btn-secondary bg-white">Tutup</button>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Riwayat penyusunan buku ini (BukuDokumenRepository). --}}
                <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-riwayat-buku" data-testid="riwayat-buku">
                    <h2 id="judul-riwayat-buku" class="text-sm font-semibold text-stone-900">Riwayat susun</h2>
                    @if ($this->riwayat === [])
                        <p class="mt-1 text-xs text-stone-500">Buku ini belum pernah disusun.</p>
                    @else
                        <ol class="mt-2 space-y-2 text-xs">
                            @foreach (array_slice($this->riwayat, 0, 5) as $r)
                                <li class="rounded-lg bg-stone-50 px-2.5 py-2 ring-1 ring-stone-100" data-riwayat-buku="{{ $r['id'] }}" wire:key="riwayat-{{ $r['id'] }}">
                                    <p class="flex items-center justify-between gap-2">
                                        <span class="font-semibold text-stone-800">{{ \Illuminate\Support\Carbon::parse($r['dibuat_pada'])->locale('id')->translatedFormat('j M Y, H.i') }}</span>
                                        @if ($r['ada_draf'])
                                            <span class="rounded-full bg-red-50 px-1.5 py-px text-[10px] font-semibold text-red-700 ring-1 ring-red-200">Ada DRAF</span>
                                        @else
                                            <span class="rounded-full bg-brand-50 px-1.5 py-px text-[10px] font-semibold text-brand-800 ring-1 ring-brand-200">Siap</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-stone-500">{{ $r['dibuat_oleh'] }} · {{ $r['jumlah_kegiatan'] }} kegiatan · ± {{ $r['halaman'] }} halaman</p>
                                    <p class="mt-0.5 text-stone-400">{{ $r['file_pdf_path'] ? 'PDF tersedia' : 'PDF belum digabung' }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                @if ($admin)
                    <section class="rounded-2xl bg-white p-4 text-sm shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pengaturan-buku">
                        <h2 id="judul-pengaturan-buku" class="text-sm font-semibold text-stone-900">Pengaturan buku</h2>
                        <ul class="mt-2 space-y-1.5 text-xs">
                            <li><a href="{{ route('pengaturan.template.index', ['tab' => 'daftar_isi']) }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Daftar isi</a> <span class="text-stone-500">— judul, kolom, urutan</span></li>
                            <li><a href="{{ route('pengaturan.template.index', ['tab' => 'nomor_halaman']) }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Nomor halaman</a> <span class="text-stone-500">— format &amp; mulai</span></li>
                        </ul>
                    </section>
                @endif
            </aside>
        </div>

        {{-- Peta semua buku tahun aktif --}}
        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-peta-buku" data-testid="peta-buku">
            <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                <h2 id="judul-peta-buku" class="text-base font-semibold text-stone-900">Semua buku TA {{ $tahun }}</h2>
                <p class="mt-0.5 text-sm text-stone-500">Satu buku untuk tiap leading &amp; sumber dana. Pilih untuk melihat susunannya.</p>
            </header>
            <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                @foreach ($this->peta as $b)
                    @php
                        $aktif = $b['leading'] === $terpilih['leading'] && $b['sumber'] === $terpilih['sumber'];
                    @endphp
                    <button type="button" wire:click="pilih('{{ $b['leading'] }}', '{{ $b['sumber'] }}')" aria-pressed="{{ $aktif ? 'true' : 'false' }}"
                            data-buku="{{ $b['leading'] }}|{{ $b['sumber'] }}" data-jumlah="{{ $b['jumlah'] }}"
                            @class(['rounded-xl p-3 text-left ring-1 transition', 'bg-brand-50/60 ring-2 ring-brand-600' => $aktif, 'bg-white ring-stone-200 hover:ring-stone-300' => ! $aktif && $b['jumlah'] > 0, 'bg-stone-50 ring-stone-100 text-stone-400' => ! $aktif && $b['jumlah'] === 0])>
                        <span class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate text-xs font-semibold {{ $b['jumlah'] > 0 ? 'text-stone-800' : '' }}">{{ $b['leading'] }} · {{ $b['nama_leading'] }}</span>
                            <x-rab.lencana-sumber :sumber="$b['sumber']" class="text-[10px]" />
                        </span>
                        <span class="mt-1 block text-lg font-bold tabular-nums {{ $b['jumlah'] > 0 ? 'text-stone-900' : '' }}">{{ $b['jumlah'] }} <span class="text-xs font-medium text-stone-500">kegiatan</span></span>
                        <span class="block text-xs tabular-nums {{ $b['jumlah'] > 0 ? 'text-stone-500' : '' }}">{{ $b['jumlah'] > 0 ? Format::rupiah($b['total']).' · '.$b['status']['final'].' final' : 'Belum ada kegiatan' }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
</div>
