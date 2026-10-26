@use('App\Livewire\Pengaturan\TemplateDokumen')
@use('App\Support\Dokumen\TeksCover')
@use('App\Support\Format')
@use('Illuminate\Support\Carbon')

{{-- Tab Cover Template Dokumen: unggah & pratinjau desain cover, daftar cover tersimpan, editor tata letak kotak teks. --}}
@php
    $covers = $this->covers;
    $pratinjau = $this->pratinjau;
    $info = $this->infoGambar;
    $pertama = $covers === [];
@endphp

    @if ($coverDiatur && ($coverEditor = collect($covers)->firstWhere('id', $coverDiatur)))
        {{--
            Editor tata letak: kanvas & panel properti dirender Alpine dari $wire.kotak (state lokal, wire:ignore). Kunci
            memuat contoh kegiatan × sumber agar editor dibuat ulang dengan isi contoh baru — perubahan lokal ikut terkirim
            bersama pergantian contoh sehingga tidak hilang.
        --}}
        <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" aria-labelledby="judul-editor-cover" data-testid="editor-cover">
            <header class="flex flex-wrap items-center gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h2 id="judul-editor-cover" class="text-base font-semibold text-stone-900">Atur kotak teks — {{ $coverEditor['nama'] }}</h2>
                    <p class="mt-0.5 text-sm text-stone-500">Seret kotak untuk memindahkan, tarik pegangan kanan untuk mengubah lebar. Isi kotak hanya contoh; saat dicetak diisi data tiap kegiatan.</p>
                </div>
                <label class="min-w-0 sm:w-64">
                    <span class="sr-only">Contoh kegiatan</span>
                    <select wire:model.live="contohKegiatan" class="form-input py-1.5 text-xs" aria-label="Contoh kegiatan untuk editor">
                        @foreach ($this->daftarContoh as $contoh)
                            <option value="{{ $contoh['id'] }}">{{ $contoh['nama'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-ghost" wire:click="batalAtur">Batal</button>
                    <button type="button" class="btn-primary" wire:click="simpanTataLetak" wire:loading.attr="disabled" wire:target="simpanTataLetak">
                        <x-ui.icon name="save" class="size-4" /> Simpan tata letak
                    </button>
                </div>
            </header>
            @error('kotak')
                <p class="flex items-center gap-2 border-b border-red-200 bg-red-50 px-5 py-2 text-sm text-red-700 sm:px-6" role="alert"><x-ui.icon name="alert" class="size-4" /> {{ $message }}</p>
            @enderror

            <div wire:ignore wire:key="editor-{{ $coverDiatur }}-{{ $contohKegiatan }}-{{ $contohSumber }}"
                 x-data="editorCover({ bawaan: @js(TeksCover::bawaan()), label: @js(TeksCover::FIELD), nilai: @js($this->nilaiContoh) })"
                 x-on:pointermove.window="gerak($event)" x-on:pointerup.window="selesai()" x-on:pointercancel.window="selesai()"
                 class="grid gap-6 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0">
                    <div x-ref="kanvas" tabindex="0" x-on:keydown="tombol($event)" data-testid="kanvas-cover"
                         aria-label="Kanvas cover. Panah menggeser kotak terpilih 0,5%, Shift + panah 2%."
                         class="@container relative mx-auto aspect-[210/297] w-full max-w-md overflow-hidden bg-white shadow-sm ring-1 ring-stone-200 select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        <img src="{{ route('pengaturan.template.cover.gambar', $coverEditor['id']) }}" alt="Desain cover" draggable="false" class="pointer-events-none absolute inset-0 size-full object-cover">
                        <template x-for="(k, i) in kotak" x-bind:key="k.field">
                            <div class="absolute cursor-move touch-none font-serif leading-tight break-words whitespace-pre-line"
                                 x-bind:style="gaya(k)" x-bind:data-field="k.field" x-bind:data-terpilih="i === pilih ? 'ya' : 'tidak'"
                                 x-bind:class="i === pilih ? 'outline-2 outline-offset-1 outline-brand-600 bg-brand-50/40' : 'outline-1 outline-offset-1 outline-dashed outline-brand-500/70 hover:bg-brand-50/30'"
                                 x-on:pointerdown="mulai($event, i, 'pindah')">
                                <span x-text="nilai[k.field] ?? ''"></span>
                                <span x-show="i === pilih" x-on:pointerdown.stop="mulai($event, i, 'lebar')" data-testid="pegangan-lebar"
                                      class="absolute top-1/2 -right-2 h-5 w-2.5 -translate-y-1/2 cursor-ew-resize rounded-sm bg-brand-600 ring-2 ring-white" aria-hidden="true"></span>
                            </div>
                        </template>
                    </div>
                    <p class="mx-auto mt-2 max-w-md text-center text-xs text-stone-500">Klik kanvas lalu pakai panah untuk menggeser halus (Shift: lebih jauh).</p>
                </div>

                <div class="min-w-0 space-y-5">
                    <section aria-labelledby="judul-kolom-cover">
                        <h3 id="judul-kolom-cover" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Kolom di cover</h3>
                        <ul class="mt-2 space-y-1" data-testid="daftar-kolom-cover">
                            @foreach (TeksCover::FIELD as $field => $labelField)
                                <li class="flex items-center gap-2 rounded-lg px-1.5 py-1" x-bind:class="terpilih && terpilih.field === '{{ $field }}' ? 'bg-brand-50' : ''">
                                    <input type="checkbox" id="tampil-{{ $field }}" class="size-4 rounded accent-brand-700" x-bind:checked="tampil('{{ $field }}')" x-on:change="alihTampil('{{ $field }}')">
                                    <label for="tampil-{{ $field }}" class="sr-only">Tampilkan {{ $labelField }}</label>
                                    <button type="button" class="min-w-0 flex-1 truncate text-left text-sm text-stone-700 hover:text-brand-700 disabled:text-stone-400"
                                            x-bind:disabled="! tampil('{{ $field }}')" x-on:click="pilihField('{{ $field }}')">{{ $labelField }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <section aria-labelledby="judul-kotak-terpilih" x-show="terpilih" data-testid="properti-kotak">
                        <h3 id="judul-kotak-terpilih" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Kotak terpilih: <span class="normal-case tracking-normal text-stone-700" x-text="terpilih ? label(terpilih.field) : ''"></span></h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            @foreach (['x' => 'Kiri (%)', 'y' => 'Atas (%)', 'width' => 'Lebar (%)', 'font_size' => 'Ukuran huruf (pt)'] as $kolom => $labelKolom)
                                <label class="text-xs text-stone-600">
                                    {{ $labelKolom }}
                                    <input type="number" step="0.5" inputmode="decimal" class="form-input mt-1 py-1.5" data-properti="{{ $kolom }}"
                                           x-bind:value="terpilih ? terpilih.{{ $kolom }} : ''" x-on:change="aturAngka('{{ $kolom }}', $event.target.value)">
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Perataan">
                                @foreach (['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'] as $rata => $labelRata)
                                    <button type="button" class="rounded-md px-2.5 py-1 text-xs font-semibold" x-on:click="atur(pilih, 'align', '{{ $rata }}')"
                                            x-bind:aria-pressed="terpilih && terpilih.align === '{{ $rata }}' ? 'true' : 'false'"
                                            x-bind:class="terpilih && terpilih.align === '{{ $rata }}' ? 'bg-white text-stone-900 shadow-xs' : 'text-stone-500 hover:text-stone-800'">{{ $labelRata }}</button>
                                @endforeach
                            </div>
                            <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Ketebalan">
                                @foreach (['normal' => 'Biasa', 'bold' => 'Tebal'] as $tebal => $labelTebal)
                                    <button type="button" class="rounded-md px-2.5 py-1 text-xs font-semibold" x-on:click="atur(pilih, 'weight', '{{ $tebal }}')"
                                            x-bind:aria-pressed="terpilih && terpilih.weight === '{{ $tebal }}' ? 'true' : 'false'"
                                            x-bind:class="terpilih && terpilih.weight === '{{ $tebal }}' ? 'bg-white text-stone-900 shadow-xs' : 'text-stone-500 hover:text-stone-800'">{{ $labelTebal }}</button>
                                @endforeach
                            </div>
                            <label class="flex items-center gap-2 text-xs text-stone-600">
                                Warna
                                <input type="color" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0" x-bind:value="terpilih ? terpilih.color : '#1c1917'"
                                       x-on:input="atur(pilih, 'color', $event.target.value)" aria-label="Warna teks">
                            </label>
                        </div>
                        <button type="button" class="btn-secondary mt-3 px-3 py-1.5 text-xs" x-on:click="tengahkan()">Tengahkan di halaman</button>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-4">
                        <button type="button" class="btn-ghost px-2.5 py-1.5 text-xs" x-on:click="kembalikan()">Kembalikan susunan bawaan</button>
                        <span x-show="berubah" x-cloak class="inline-flex items-center gap-1 text-xs font-medium text-gold-700" data-testid="tata-letak-berubah">
                            <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
                        </span>
                    </div>
                </div>
            </div>
        </section>
    @else
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
                @if ($pratinjau['jenis'] === 'tersimpan')
                    <button type="button" class="btn-secondary mt-3 w-full justify-center" wire:click="aturKotak('{{ $pratinjau['cover']['id'] }}')">
                        <x-ui.icon name="pencil" class="size-4" /> Atur kotak teks
                    </button>
                @endif
                <p class="mt-3 text-xs text-stone-500">
                    @if ($pratinjau['jenis'] === 'tersimpan')
                        Posisi, lebar, dan gaya kotak bisa diatur per cover. Isinya diambil dari data tiap kegiatan saat dicetak.
                    @else
                        Kotak teks memakai susunan bawaan ({{ count(TeksCover::FIELD) }} kolom: kop, judul, nama kegiatan, sumber dana, “Disusun oleh”, leading, kota, tahun); posisinya bisa diatur setelah cover disimpan.
                    @endif
                </p>
            </section>
        </aside>
    </div>
    @endif
