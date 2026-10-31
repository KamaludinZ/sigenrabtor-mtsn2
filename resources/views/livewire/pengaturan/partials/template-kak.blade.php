@use('App\Contracts\TemplateDokumenRepository')
@use('App\Livewire\Pengaturan\TemplateDokumen')
@use('App\Support\Dokumen\NilaiVariabel')
@use('App\Support\Dokumen\VariabelTemplate')
@use('App\Support\Format')
@use('Illuminate\Support\Carbon')

{{-- Tab KAK/TOR Template Dokumen: unggah master .docx, periksa variabel ${…} terhadap daftar terdaftar, kelola master tersimpan. --}}
@php
    $masters = $this->masters;
    $analisis = $this->analisisMaster;
    $tidakTerdaftar = $analisis?->tidakTerdaftar() ?? [];
    $jenisAda = array_unique(array_column($masters, 'jenis'));
@endphp

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
    <div class="min-w-0 space-y-6">
        {{-- Unggah master --}}
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-unggah-master" data-testid="unggah-master">
            <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                <h2 id="judul-unggah-master" class="text-base font-semibold text-stone-900">Unggah master KAK/TOR (.docx)</h2>
                <p class="mt-0.5 text-sm text-stone-500">Susun dokumen di Word seperti biasa, lalu tulis variabel di bagian yang diisi otomatis, mis. <code class="rounded bg-stone-100 px-1 text-xs">${nama_kegiatan}</code>. Variabel diperiksa sebelum template boleh disimpan.</p>
            </header>
            {{-- wire:key per keadaan (memilih ↔ berkas terbaca): status Alpine unggah dibuat ulang, tidak terbawa morph. --}}
            <div class="space-y-4 px-5 py-5 sm:px-6" wire:key="unggah-master-{{ $analisis ? 'terbaca' : 'memilih' }}"
                 x-data="{ unggah: false, persen: 0 }"
                 x-on:livewire-upload-start="unggah = true; persen = 0"
                 x-on:livewire-upload-progress="persen = $event.detail.progress"
                 x-on:livewire-upload-finish="unggah = false"
                 x-on:livewire-upload-error="unggah = false">
                @if (! $analisis)
                    <label for="berkas-master"
                           class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-300 px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40 has-focus-visible:outline-2 has-focus-visible:outline-brand-600">
                        <x-ui.icon name="upload" class="size-8 text-stone-400" />
                        <span class="text-sm font-semibold text-stone-800">Pilih berkas master .docx</span>
                        <span class="text-xs text-stone-500">Dokumen Word .docx · maks. {{ TemplateDokumen::MAKS_KB_MASTER / 1024 }} MB</span>
                        <input type="file" id="berkas-master" wire:model="berkasMaster" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="sr-only" data-testid="input-berkas-master">
                    </label>
                    <div x-show="unggah" x-cloak>
                        <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                        <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                    </div>
                    <p class="flex items-center gap-2 text-sm text-stone-600" wire:loading.flex wire:target="berkasMaster">
                        <x-ui.icon name="loader" class="size-4 animate-spin" /> Membaca variabel di dokumen…
                    </p>
                    @error('berkasMaster')
                        <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-master">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                        </p>
                    @enderror
                @else
                    <div class="flex flex-wrap items-start gap-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="info-master">
                        <x-ui.icon name="file-text" class="mt-0.5 size-5 shrink-0 text-stone-500" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $berkasMaster?->getClientOriginalName() }}</p>
                            <p class="text-xs text-stone-500" data-testid="ringkasan-variabel">
                                {{ count($analisis->variabel) }} variabel ditemukan · {{ count($analisis->variabel) - count($tidakTerdaftar) }} terdaftar{{ $tidakTerdaftar !== [] ? ' · '.count($tidakTerdaftar).' tidak terdaftar' : '' }}
                            </p>
                        </div>
                        <label for="ganti-berkas-master" class="btn-ghost cursor-pointer px-2.5 py-1 text-xs">
                            Unggah ulang
                            <input type="file" id="ganti-berkas-master" wire:model="berkasMaster" accept=".docx" class="sr-only">
                        </label>
                    </div>

                    @if ($analisis->variabel === [])
                        <p class="flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-700 ring-1 ring-gold-200" role="alert" data-testid="master-tanpa-variabel">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                            Belum ada variabel ${…} di dokumen ini. Tandai bagian yang diisi otomatis, mis. ${nama_kegiatan} atau ${total}, lalu unggah ulang.
                        </p>
                    @elseif ($tidakTerdaftar !== [])
                        <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="master-tidak-terdaftar">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                            {{ count($tidakTerdaftar) }} variabel tidak terdaftar — kemungkinan salah tulis. Perbaiki di Word lalu unggah ulang; template ini belum bisa disimpan.
                        </p>
                    @endif

                    @if ($analisis->variabel !== [])
                        <ul class="divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200" aria-label="Variabel di dokumen" data-testid="variabel-master">
                            @foreach ($analisis->variabel as $v)
                                <li class="flex items-start gap-3 px-3 py-2.5" data-variabel="{{ $v['nama'] }}" data-terdaftar="{{ $v['terdaftar'] ? 'ya' : 'tidak' }}">
                                    @if ($v['terdaftar'])
                                        <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                                    @else
                                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-red-600" />
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <code @class(['rounded px-1.5 py-0.5 text-xs font-semibold', 'bg-brand-50 text-brand-800' => $v['terdaftar'], 'bg-red-50 text-red-700' => ! $v['terdaftar']])>${{ '{' }}{{ $v['nama'] }}{{ '}' }}</code>
                                            @if ($v['jumlah'] > 1)
                                                <span class="text-[11px] text-stone-400">{{ $v['jumlah'] }}×</span>
                                            @endif
                                        </p>
                                        @if ($v['terdaftar'])
                                            <p class="mt-0.5 text-xs text-stone-500">{{ $v['keterangan'] }} · {{ VariabelTemplate::KELOMPOK[$v['kelompok']] }}</p>
                                        @elseif ($v['saran'])
                                            <p class="mt-0.5 text-xs text-red-700">Tidak terdaftar. Mungkin maksud Anda <code class="font-semibold">${{ '{' }}{{ $v['saran'] }}{{ '}' }}</code>?</p>
                                        @else
                                            <p class="mt-0.5 text-xs text-red-700">Tidak terdaftar — lihat daftar variabel di samping.</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if (($belumAda = $analisis->dianjurkanBelumAda()) !== [])
                            <p class="flex items-start gap-2 text-xs text-stone-500" data-testid="dianjurkan-belum-ada">
                                <x-ui.icon name="info" class="mt-px size-3.5 shrink-0 text-stone-400" />
                                Belum dipakai: {{ implode(', ', array_map(fn ($n) => '${'.$n.'}', $belumAda)) }} — dianjurkan agar nama, tahun, dan angka tidak diketik manual.
                            </p>
                        @endif
                    @endif

                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <x-ui.field label="Nama template" for="nama-master" error="namaMaster">
                            <input type="text" id="nama-master" wire:model="namaMaster" maxlength="100" class="form-input" autocomplete="off">
                        </x-ui.field>
                        <x-ui.field label="Jenis" for="jenis-master" error="jenisMaster"
                                    :hint="in_array($jenisMaster, $jenisAda, true) ? 'Default jenis ini tidak berubah.' : 'Menjadi default untuk jenis ini.'">
                            <select id="jenis-master" wire:model.live="jenisMaster" class="form-input">
                                @foreach (TemplateDokumenRepository::JENIS as $kunci => $label)
                                    <option value="{{ $kunci }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                    @error('berkasMaster')
                        <p class="form-error" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($analisis->variabel !== [])
                            <button type="button" class="btn-secondary sm:mr-auto" wire:click="bukaPratinjauMaster('baru')">
                                <x-ui.icon name="eye" class="size-4" /> Pratinjau &amp; uji isi
                            </button>
                        @endif
                        <button type="button" class="btn-ghost" wire:click="batalUnggahMaster">Batal</button>
                        {{-- Dua varian berkunci: @disabled server + wire:loading.attr pada elemen yang sama membuat Livewire memulihkan "disabled" lama setelah unggah ulang. --}}
                        @if ($analisis->bisaDisimpan())
                            <button type="button" class="btn-primary" wire:key="simpan-master-aktif" wire:click="simpanMaster" wire:loading.attr="disabled" wire:target="simpanMaster, berkasMaster">
                                <x-ui.icon name="save" class="size-4" /> Simpan master
                            </button>
                        @else
                            <button type="button" class="btn-primary" wire:key="simpan-master-terkunci" disabled title="Perbaiki variabel dulu">
                                <x-ui.icon name="save" class="size-4" /> Simpan master
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- Master tersimpan --}}
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-master-tersimpan" data-testid="master-tersimpan">
            <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                <h2 id="judul-master-tersimpan" class="text-base font-semibold text-stone-900">Master tersimpan</h2>
                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                    <span>Tahun Anggaran {{ $this->tahun() }} · {{ count($masters) }} template</span>
                    <a href="{{ route('pengaturan.template.master.index') }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800" data-testid="ke-template-master">Kelola per leading →</a>
                </p>
            </header>
            @if ($masters === [])
                <div class="px-5 py-8 text-center sm:px-6" data-testid="master-kosong">
                    <x-ui.icon name="file-text" class="mx-auto size-8 text-stone-300" />
                    <p class="mt-2 text-sm font-medium text-stone-700">Belum ada master KAK/TOR</p>
                    <p class="mt-0.5 text-xs text-stone-500">Selama kosong, dokumen memakai susunan baku sembilan bagian.</p>
                </div>
            @else
                <ul class="divide-y divide-stone-100" aria-label="Daftar master tersimpan">
                    @foreach ($masters as $master)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3 sm:px-6" data-testid="master-{{ $loop->iteration }}" wire:key="master-{{ $master['id'] }}">
                            <x-ui.icon name="file-text" class="size-8 shrink-0 text-brand-700" />
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-stone-900">
                                    {{ $master['nama'] }}
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">{{ $master['jenis'] }}</span>
                                    @if ($master['is_default'])
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200">Default</span>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-stone-500">{{ $master['nama_berkas'] }} · {{ count($master['variabel']) }} variabel · diunggah {{ Format::tanggal(Carbon::parse($master['diperbarui_pada'])->toDateString()) }}</p>
                            </div>
                            @if ($masterAkanDihapus === $master['id'])
                                <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5" role="group" aria-label="Konfirmasi hapus {{ $master['nama'] }}">
                                    <span class="text-xs text-stone-600">Hapus template ini?</span>
                                    <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="batalHapusMaster">Batal</button>
                                    <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="hapusMaster">Hapus</button>
                                </div>
                            @else
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" class="btn-secondary px-2.5 py-1 text-xs" wire:click="bukaPratinjauMaster('{{ $master['id'] }}')" aria-label="Pratinjau {{ $master['nama'] }}">
                                        <x-ui.icon name="eye" class="size-3.5" /> Pratinjau
                                    </button>
                                    <a href="{{ route('pengaturan.template.master.unduh', $master['id']) }}" class="btn-secondary px-2.5 py-1 text-xs">
                                        <x-ui.icon name="download" class="size-3.5" /> Unduh
                                    </a>
                                    @php
                                        $pakai = $this->pemakaianMaster[$master['id']] ?? ['kegiatan' => 0, 'dokumen' => 0];
                                    @endphp
                                    @if ($pakai['kegiatan'] + $pakai['dokumen'] > 0)
                                        <span class="rounded-md p-1.5 text-stone-300" data-testid="hapus-master-terkunci"
                                              title="Tidak bisa dihapus — {{ implode(' & ', array_filter([$pakai['kegiatan'] ? $pakai['kegiatan'].' kegiatan memilihnya' : null, $pakai['dokumen'] ? $pakai['dokumen'].' dokumen memakainya' : null])) }}. Nonaktifkan di Template Master."
                                              aria-label="{{ $master['nama'] }} dipakai — tidak bisa dihapus">
                                            <x-ui.icon name="lock" class="size-4" />
                                        </span>
                                    @else
                                        <button type="button" class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600" wire:click="mintaHapusMaster('{{ $master['id'] }}')"
                                                title="Hapus" aria-label="Hapus {{ $master['nama'] }}">
                                            <x-ui.icon name="trash" class="size-4" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Daftar variabel terdaftar (salin untuk ditempel di Word) --}}
    <aside class="min-w-0">
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto" aria-labelledby="judul-daftar-variabel" data-testid="daftar-variabel"
                 x-data="{ disalin: null, salin(teks) { navigator.clipboard?.writeText(teks); this.disalin = teks; setTimeout(() => this.disalin = null, 1500) } }">
            <h2 id="judul-daftar-variabel" class="text-sm font-semibold text-stone-900">Variabel terdaftar</h2>
            <p class="mt-0.5 text-xs text-stone-500">Klik untuk menyalin, lalu tempel di Word. Penulisan harus sama persis (huruf kecil, garis bawah).</p>
            <div class="mt-3 space-y-4">
                @foreach (VariabelTemplate::KELOMPOK as $kelompok => $judulKelompok)
                    <div>
                        <h3 class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">{{ $judulKelompok }}</h3>
                        <ul class="mt-1.5 space-y-1">
                            @foreach (VariabelTemplate::DAFTAR as $nama => [$keterangan, $kelompokVariabel, $jenis])
                                @if ($kelompokVariabel === $kelompok)
                                    <li>
                                        <button type="button" x-on:click="salin(@js('${'.$nama.'}'))" title="Salin ${{ '{' }}{{ $nama }}{{ '}' }}"
                                                class="group flex w-full items-start gap-2 rounded-md px-1.5 py-1 text-left hover:bg-stone-50">
                                            <code class="shrink-0 rounded bg-stone-100 px-1.5 py-0.5 text-[11px] font-semibold text-stone-700 group-hover:bg-brand-50 group-hover:text-brand-800">${{ '{' }}{{ $nama }}{{ '}' }}</code>
                                            <span class="min-w-0 text-[11px] leading-snug text-stone-500">
                                                {{ $keterangan }}
                                                @if ($jenis !== 'teks')
                                                    <span class="text-stone-400">({{ ['daftar' => 'daftar bernomor', 'tabel' => 'tabel utuh', 'blok' => 'beberapa baris', 'gambar' => 'gambar'][$jenis] }})</span>
                                                @endif
                                                <span class="font-semibold text-brand-700" x-show="disalin === @js('${'.$nama.'}')" x-cloak>Disalin</span>
                                            </span>
                                        </button>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    </aside>
</div>

{{-- Panel pratinjau isi master: mode penanda (variabel sebagai penanda) atau uji isi (nilai contoh kegiatan × sumber dana). --}}
@if ($pratinjauMaster && ($isiMaster = $this->pratinjauTemplate))
    @php
        $kosongMaster = $isiMaster->kosong();
        $judulPratinjau = $pratinjauMaster === 'baru' ? ($berkasMaster?->getClientOriginalName() ?? 'Berkas baru').' (belum disimpan)' : (collect($masters)->firstWhere('id', $pratinjauMaster)['nama'] ?? '');
        $sumberContoh = $this->sumberContoh();
    @endphp
    <div class="fixed inset-0 z-50 flex" role="dialog" aria-modal="true" aria-labelledby="judul-pratinjau-master" data-testid="panel-pratinjau-master"
         x-data="{ init() { document.documentElement.classList.add('overflow-hidden') }, destroy() { document.documentElement.classList.remove('overflow-hidden') } }"
         x-on:keydown.escape.window="$wire.tutupPratinjauMaster()">
        <div class="absolute inset-0 bg-stone-900/40" wire:click="tutupPratinjauMaster" aria-hidden="true"></div>
        <div class="relative ml-auto flex h-full w-full max-w-4xl flex-col bg-stone-100 shadow-xl" x-trap="true">
            <header class="flex flex-wrap items-center gap-3 border-b border-stone-200 bg-white px-4 py-3 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h2 id="judul-pratinjau-master" class="truncate text-sm font-semibold text-stone-900">Pratinjau master — {{ $judulPratinjau }}</h2>
                    <p class="text-xs text-stone-500">Teks dokumen tanpa format Word; variabel ditandai atau diganti isi contoh kegiatan.</p>
                </div>
                <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Mode pratinjau">
                    @foreach (['penanda' => 'Penanda', 'isi' => 'Uji isi'] as $mode => $labelMode)
                        <button type="button" wire:click="$set('modeMaster', '{{ $mode }}')" aria-pressed="{{ $modeMaster === $mode ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1 text-xs font-semibold', 'bg-white text-stone-900 shadow-xs' => $modeMaster === $mode, 'text-stone-500 hover:text-stone-800' => $modeMaster !== $mode])>{{ $labelMode }}</button>
                    @endforeach
                </div>
                <button type="button" class="btn-ghost" wire:click="tutupPratinjauMaster"><x-ui.icon name="x" class="size-4" /> Tutup</button>
            </header>

            @if ($modeMaster === 'isi')
                <div class="flex flex-wrap items-center gap-2 border-b border-stone-200 bg-white px-4 py-2 sm:px-6">
                    <label class="min-w-0 flex-1 sm:max-w-sm">
                        <span class="sr-only">Contoh kegiatan</span>
                        <select wire:model.live="contohKegiatan" class="form-input py-1.5 text-xs" aria-label="Contoh kegiatan untuk uji isi">
                            @foreach ($this->daftarContoh as $contoh)
                                <option value="{{ $contoh['id'] }}">{{ $contoh['nama'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (count($sumberContoh) > 1)
                        <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Sumber dana contoh">
                            @foreach ($sumberContoh as $sumber)
                                <button type="button" wire:click="$set('contohSumber', '{{ $sumber }}')" aria-pressed="{{ $sumber === $contohSumber ? 'true' : 'false' }}"
                                        @class(['rounded-md px-2.5 py-1 text-xs font-semibold', 'bg-white text-stone-900 shadow-xs' => $sumber === $contohSumber, 'text-stone-500 hover:text-stone-800' => $sumber !== $contohSumber])>{{ $sumber }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($kosongMaster !== [])
                    <div class="border-b border-gold-200 bg-gold-50 px-4 py-2 text-xs text-stone-700 sm:px-6" data-testid="variabel-kosong">
                        <p class="flex items-center gap-1.5 font-semibold"><x-ui.icon name="alert" class="size-4 text-gold-600" /> {{ count($kosongMaster) }} variabel kosong untuk contoh ini — dokumen akan bercap DRAF:</p>
                        <ul class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($kosongMaster as $nama)
                                <li class="rounded-full bg-white px-2 py-0.5 ring-1 ring-gold-200" data-kosong="{{ $nama }}"><code class="font-semibold">${{ '{' }}{{ $nama }}{{ '}' }}</code>{{ isset(NilaiVariabel::ASAL[$nama]) ? ' · '.NilaiVariabel::ASAL[$nama] : '' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="flex items-center gap-1.5 border-b border-brand-200 bg-brand-50 px-4 py-2 text-xs font-medium text-brand-800 sm:px-6" data-testid="variabel-lengkap">
                        <x-ui.icon name="check-circle" class="size-4" /> Semua variabel terisi untuk contoh ini.
                    </p>
                @endif
            @else
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-stone-200 bg-white px-4 py-2 text-xs text-stone-500 sm:px-6" aria-label="Keterangan penanda">
                    <span class="inline-flex items-center gap-1.5"><code class="rounded bg-brand-50 px-1 font-semibold text-brand-800">${…}</code> terdaftar</span>
                    <span class="inline-flex items-center gap-1.5"><code class="rounded bg-red-50 px-1 font-semibold text-red-700">${…}</code> tidak terdaftar</span>
                </div>
            @endif

            <div class="flex-1 overflow-y-auto p-3 sm:p-6">
                @include('livewire.pengaturan.partials.isi-master', ['isiMaster' => $isiMaster, 'modeMaster' => $modeMaster])
            </div>
        </div>
    </div>
@endif
