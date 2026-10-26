@use('App\Livewire\Pengaturan\TemplateDokumen')
@use('App\Support\Dokumen\TeksCover')
@use('App\Support\Format')
@use('Illuminate\Support\Carbon')

@php
    $covers = $this->covers;
    $pratinjau = $this->pratinjau;
    $info = $this->infoGambar;
    $pertama = $covers === [];
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Template Dokumen"
                         deskripsi="Tampilan cover, isi KAK/TOR, daftar isi buku, dan nomor halaman yang dipakai semua kegiatan tahun ini. Selama belum diatur, dokumen memakai susunan baku aplikasi.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Jenis template">
                @foreach (TemplateDokumen::TAB as $kunci => $label)
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800">{{ $label }}</span>
                    @elseif (in_array($kunci, TemplateDokumen::TAB_TERSEDIA, true))
                        <button type="button" wire:click="$set('tab', '{{ $kunci }}')" class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">{{ $label }}</button>
                    @else
                        <span class="flex shrink-0 cursor-not-allowed items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-400" title="Sedang disiapkan">
                            {{ $label }} <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
                        </span>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)] xl:grid-cols-[minmax(0,1fr)_minmax(0,27rem)]">
        <div class="min-w-0 space-y-6">
            {{-- Unggah desain cover --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-unggah-cover" data-testid="unggah-cover">
                <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                    <h2 id="judul-unggah-cover" class="text-base font-semibold text-stone-900">Unggah desain cover</h2>
                    <p class="mt-0.5 text-sm text-stone-500">Gambar latar cover KAK/TOR tanpa teks; judul, nama kegiatan, leading, dan tahun ditulis aplikasi di atasnya.</p>
                </header>
                {{-- wire:key per keadaan (memilih ↔ gambar terpilih): status Alpine unggah dibuat ulang, tidak terbawa morph. --}}
                <div class="space-y-4 px-5 py-5 sm:px-6" wire:key="unggah-{{ $info ? 'terpilih' : 'memilih' }}"
                     x-data="{ unggah: false, persen: 0 }"
                     x-on:livewire-upload-start="unggah = true; persen = 0"
                     x-on:livewire-upload-progress="persen = $event.detail.progress"
                     x-on:livewire-upload-finish="unggah = false"
                     x-on:livewire-upload-error="unggah = false">
                    @if (! $info)
                        <label for="gambar-cover"
                               class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-300 px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40 has-focus-visible:outline-2 has-focus-visible:outline-brand-600">
                            <x-ui.icon name="upload" class="size-8 text-stone-400" />
                            <span class="text-sm font-semibold text-stone-800">Pilih gambar cover</span>
                            <span class="text-xs text-stone-500">JPG atau PNG · maks. {{ TemplateDokumen::MAKS_KB / 1024 }} MB · potret A4, mis. 1240 × 1754 piksel</span>
                            <input type="file" id="gambar-cover" wire:model="gambar" accept="image/png,image/jpeg" class="sr-only" data-testid="input-gambar-cover">
                        </label>
                        <div x-show="unggah" x-cloak>
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                            <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                        </div>
                        @error('gambar')
                            <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-gambar">
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                            </p>
                        @enderror
                    @else
                        <div class="flex flex-wrap items-start gap-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="info-gambar">
                            <x-ui.icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-brand-600" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-stone-900">{{ $info['nama'] }}</p>
                                <p class="text-xs text-stone-500">{{ Format::angka($info['lebar']) }} × {{ Format::angka($info['tinggi']) }} piksel · {{ Format::angka($info['kb']) }} KB</p>
                                @if ($info['a4'])
                                    <p class="mt-1 text-xs font-medium text-brand-700" data-testid="rasio-gambar" data-a4="ya">Perbandingan sesuai A4 potret.</p>
                                @else
                                    <p class="mt-1 flex items-start gap-1 text-xs font-medium text-gold-700" data-testid="rasio-gambar" data-a4="tidak">
                                        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
                                        {{ $info['potret'] ? 'Perbandingan tidak sama dengan A4' : 'Gambar mendatar, bukan potret' }} — bagian tepi akan terpotong menyesuaikan halaman. Lihat pratinjau sebelum menyimpan.
                                    </p>
                                @endif
                            </div>
                            <label for="ganti-gambar-cover" class="btn-ghost cursor-pointer px-2.5 py-1 text-xs">
                                Ganti
                                <input type="file" id="ganti-gambar-cover" wire:model="gambar" accept="image/png,image/jpeg" class="sr-only">
                            </label>
                        </div>
                        <x-ui.field label="Nama cover" for="nama-cover" error="namaCover"
                                    :hint="$pertama ? 'Cover pertama tahun ini otomatis menjadi cover default untuk semua kegiatan.' : 'Cover default tidak berubah; variasi per leading & sumber dana menyusul.'">
                            <input type="text" id="nama-cover" wire:model="namaCover" maxlength="100" class="form-input" autocomplete="off">
                        </x-ui.field>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" class="btn-ghost" wire:click="batalUnggah">Batal</button>
                            <button type="button" class="btn-primary" wire:click="simpanCover" wire:loading.attr="disabled" wire:target="simpanCover, gambar">
                                <x-ui.icon name="save" class="size-4" />
                                {{ $pertama ? 'Simpan sebagai cover default' : 'Simpan cover' }}
                            </button>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Cover tersimpan --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-cover-tersimpan" data-testid="cover-tersimpan">
                <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <h2 id="judul-cover-tersimpan" class="text-base font-semibold text-stone-900">Cover tersimpan</h2>
                    <p class="text-xs text-stone-500">Tahun Anggaran {{ $this->tahun() }} · {{ count($covers) }} cover</p>
                </header>
                @if ($covers === [])
                    <div class="px-5 py-8 text-center sm:px-6" data-testid="cover-kosong">
                        <x-ui.icon name="file-text" class="mx-auto size-8 text-stone-300" />
                        <p class="mt-2 text-sm font-medium text-stone-700">Belum ada desain cover</p>
                        <p class="mt-0.5 text-xs text-stone-500">Selama kosong, cover KAK/TOR memakai tata letak baku (teks saja).</p>
                    </div>
                @else
                    <ul class="divide-y divide-stone-100" aria-label="Daftar cover tersimpan">
                        @foreach ($covers as $cover)
                            @php
                                $dipratinjau = $pratinjau['jenis'] === 'tersimpan' && $pratinjau['cover']['id'] === $cover['id'];
                            @endphp
                            <li @class(['flex items-center gap-4 px-5 py-3 sm:px-6', 'bg-brand-50/40' => $dipratinjau]) data-testid="cover-{{ $loop->iteration }}" wire:key="cover-{{ $cover['id'] }}">
                                <img src="{{ route('pengaturan.template.cover.gambar', $cover['id']) }}" alt="" loading="lazy"
                                     class="aspect-[210/297] w-12 shrink-0 rounded-sm object-cover ring-1 ring-stone-200">
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-stone-900">
                                        {{ $cover['nama'] }}
                                        @if ($cover['is_default'])
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200">Default</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-stone-500">{{ Format::angka($cover['lebar']) }} × {{ Format::angka($cover['tinggi']) }} piksel · diunggah {{ Format::tanggal(Carbon::parse($cover['diperbarui_pada'])->toDateString()) }}</p>
                                </div>
                                @if ($akanDihapus === $cover['id'])
                                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5" role="group" aria-label="Konfirmasi hapus {{ $cover['nama'] }}">
                                        <span class="text-xs text-stone-600">Hapus cover ini?</span>
                                        <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="batalHapus">Batal</button>
                                        <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="hapusCover">Hapus</button>
                                    </div>
                                @else
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" @class(['btn-secondary px-2.5 py-1 text-xs', 'ring-brand-300 text-brand-800' => $dipratinjau]) wire:click="pilihCover('{{ $cover['id'] }}')"
                                                @if ($dipratinjau) aria-pressed="true" @else aria-pressed="false" @endif>
                                            <x-ui.icon name="eye" class="size-3.5" /> Pratinjau
                                        </button>
                                        <button type="button" class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600" wire:click="mintaHapus('{{ $cover['id'] }}')"
                                                title="Hapus" aria-label="Hapus {{ $cover['nama'] }}">
                                            <x-ui.icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Pratinjau --}}
        <aside class="min-w-0">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5 lg:sticky lg:top-24" aria-labelledby="judul-pratinjau-cover" data-testid="panel-pratinjau-cover">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 id="judul-pratinjau-cover" class="text-sm font-semibold text-stone-900">Pratinjau cover</h2>
                        <p class="text-xs text-stone-500" data-testid="sumber-pratinjau" data-jenis="{{ $pratinjau['jenis'] }}">{{ $pratinjau['judul'] }}</p>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs text-stone-600">
                        <input type="checkbox" wire:model.live="tampilkanKotak" class="size-4 rounded accent-brand-700">
                        Tampilkan kotak teks
                    </label>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <label class="min-w-0">
                        <span class="sr-only">Contoh kegiatan</span>
                        <select wire:model.live="contohKegiatan" class="form-input py-1.5 text-xs" aria-label="Contoh kegiatan untuk pratinjau">
                            @foreach ($this->daftarContoh as $contoh)
                                <option value="{{ $contoh['id'] }}">{{ $contoh['nama'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (count($this->sumberContoh()) > 1)
                        <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Sumber dana contoh">
                            @foreach ($this->sumberContoh() as $sumber)
                                <button type="button" wire:click="$set('contohSumber', '{{ $sumber }}')" aria-pressed="{{ $sumber === $contohSumber ? 'true' : 'false' }}"
                                        @class(['rounded-md px-2.5 py-1 text-xs font-semibold', 'bg-white text-stone-900 shadow-xs' => $sumber === $contohSumber, 'text-stone-500 hover:text-stone-800' => $sumber !== $contohSumber])>{{ $sumber }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="relative mt-4">
                    <div wire:loading.flex wire:target="gambar" class="absolute inset-0 z-10 items-center justify-center bg-white/80 text-sm text-stone-600">
                        <x-ui.icon name="loader" class="mr-2 size-4 animate-spin" /> Menyiapkan pratinjau…
                    </div>
                    <x-dokumen.cover :gambar="$pratinjau['url']" :teks="$pratinjau['teks']" :nilai="$this->nilaiContoh" :garis="$tampilkanKotak" class="max-w-sm" />
                </div>
                <p class="mt-3 text-xs text-stone-500">
                    Kotak teks memakai susunan bawaan ({{ count(TeksCover::FIELD) }} kolom: kop, judul, nama kegiatan, sumber dana, “Disusun oleh”, leading, kota, tahun). Isinya diambil dari data tiap kegiatan saat dicetak.
                </p>
            </section>
        </aside>
    </div>
</div>
