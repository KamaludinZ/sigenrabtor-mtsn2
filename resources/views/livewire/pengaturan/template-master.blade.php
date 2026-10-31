@use('App\Contracts\TemplateDokumenRepository')
@use('App\Support\Dokumen\DaftarTemplateMaster')
@use('App\Support\Format')
@use('App\Support\MasterKode')
@use('Illuminate\Support\Carbon')

{{--
    Halaman utama Template Master (DaftarTemplateMaster): ringkasan, saringan (URL), kelompok per pemilik (umum lalu per
    leading) berisi kartu template — jenis, default, status, catatan versi, variabel, berkas, pemakaian — dan panel samping
    leading tanpa template khusus + aturan multi-template.
--}}
@php
    $data = $this->data;
    $r = $data['ringkasan'];
    $adaSaringan = $cari !== '' || $leading !== '' || $jenis !== '' || $status !== '';
    // Pilihan pemilik: leading aktif, ditambah leading nonaktif yang sedang dipilih.
    $pilihanPemilik = array_values(array_filter(MasterKode::semuaLeading(), fn (array $l) => $l['is_active'] || $l['kode'] === $leading));
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Template Master KAK/TOR"
                         deskripsi="Semua master .docx ber-variabel tahun ini. Satu leading boleh punya lebih dari satu template untuk jenis yang sama, dengan satu default per jenis; template umum berlaku untuk semua leading.">
        <x-slot:aksi>
            <a href="{{ route('pengaturan.format.index', ['tab' => 'template']) }}" wire:navigate class="btn-secondary" data-testid="ke-pilihan-template">
                <x-ui.icon name="layers" class="size-4" /> Pilihan template
            </a>
            @unless ($data['terkunci'])
                <button type="button" class="btn-primary" wire:click="bukaUnggah" data-testid="unggah-template-master">
                    <x-ui.icon name="upload" class="size-4" /> Unggah template
                </button>
            @endunless
        </x-slot:aksi>
    </x-pengaturan.kepala>

    {{-- Ringkasan --}}
    <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="ringkasan-template-master">
        @foreach ([
            ['jumlah', 'Template master', $r['jumlah'], $r['aktif'].' aktif · '.$r['nonaktif'].' nonaktif'],
            ['umum', 'Umum', $r['umum'], 'Berlaku untuk semua leading'],
            ['khusus', 'Khusus leading', $r['khusus'], $r['leading'].' leading bertemplate sendiri'],
            ['dipakai', 'Sedang dipakai', $r['dipakai'], $r['tanpa_berkas'] > 0 ? $r['tanpa_berkas'].' berkas tidak ditemukan' : 'Pilihan template & kegiatan TA '.$data['tahun']],
        ] as [$kunci, $label, $nilai, $keterangan])
            <div class="rounded-2xl bg-white px-4 py-3 shadow-xs ring-1 ring-stone-200" data-ringkasan="{{ $kunci }}" data-nilai="{{ $nilai }}">
                <dt class="text-xs font-medium text-stone-500">{{ $label }}</dt>
                <dd class="mt-0.5 text-2xl font-bold text-stone-900 tabular-nums">{{ $nilai }}</dd>
                <dd @class(['mt-0.5 text-xs', 'text-gold-700' => $kunci === 'dipakai' && $r['tanpa_berkas'] > 0, 'text-stone-500' => ! ($kunci === 'dipakai' && $r['tanpa_berkas'] > 0)])>{{ $keterangan }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-5">
            @if ($r['jumlah'] > 0)
                {{-- Saringan --}}
                <div class="flex flex-wrap items-center gap-3" data-testid="saringan-template-master">
                    <x-ui.kotak-cari model="cari" :nilai="$cari" wire:model.live.debounce.300ms="cari" label="Cari template" placeholder="Cari nama, catatan versi, atau nama berkas…"
                                     :jumlah="$adaSaringan ? $data['tersaring'] : null" :total="$r['jumlah']" satuan="template" testid="cari-template-master" class="w-full sm:max-w-xs sm:flex-1" />
                    <label class="sr-only" for="saring-pemilik">Pemilik</label>
                    <select id="saring-pemilik" wire:model.live="leading" class="form-input w-auto py-1.5 text-sm" data-testid="saring-pemilik">
                        <option value="">Semua pemilik</option>
                        <option value="{{ DaftarTemplateMaster::UMUM }}">Umum — semua leading</option>
                        @foreach ($pilihanPemilik as $l)
                            <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}{{ $l['is_active'] ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="saring-jenis-master">Jenis</label>
                    <select id="saring-jenis-master" wire:model.live="jenis" class="form-input w-auto py-1.5 text-sm" data-testid="saring-jenis-master">
                        <option value="">Semua jenis</option>
                        @foreach (TemplateDokumenRepository::JENIS as $kunciJenis => $labelJenis)
                            <option value="{{ $kunciJenis }}">{{ $labelJenis }}</option>
                        @endforeach
                    </select>
                    <div class="inline-flex rounded-lg bg-stone-100 p-0.5" role="group" aria-label="Status" data-testid="saring-status-master" data-status="{{ $status }}">
                        @foreach (['' => 'Semua', ...array_map(fn (array $s) => $s[0], DaftarTemplateMaster::STATUS)] as $kunciStatus => $labelStatus)
                            <button type="button" wire:click="$set('status', '{{ $kunciStatus }}')" aria-pressed="{{ $status === $kunciStatus ? 'true' : 'false' }}"
                                    @class(['rounded-md px-3 py-1 text-xs font-semibold transition', 'bg-white text-stone-900 shadow-xs' => $status === $kunciStatus, 'text-stone-500 hover:text-stone-800' => $status !== $kunciStatus])>{{ $labelStatus }}</button>
                        @endforeach
                    </div>
                    @if ($adaSaringan)
                        <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="hapusSaringan" data-testid="hapus-saringan-master">Hapus saringan</button>
                    @endif
                </div>
            @endif

            @if ($r['jumlah'] === 0)
                <section class="rounded-2xl bg-white px-6 py-10 text-center shadow-xs ring-1 ring-stone-200" data-testid="template-master-kosong">
                    <x-ui.icon name="file-text" class="mx-auto size-10 text-stone-300" />
                    <h2 class="mt-3 text-base font-semibold text-stone-900">Belum ada template master TA {{ $data['tahun'] }}</h2>
                    <p class="mx-auto mt-1 max-w-md text-sm text-stone-500">Selama belum ada, KAK/TOR disusun dengan susunan baku aplikasi. Unggah master .docx yang memuat variabel seperti <code class="rounded bg-stone-100 px-1 text-xs">${nama_kegiatan}</code> dan <code class="rounded bg-stone-100 px-1 text-xs">${total}</code>.</p>
                    <ol class="mx-auto mt-4 max-w-sm space-y-1.5 text-left text-sm text-stone-600">
                        <li class="flex gap-2"><span class="font-semibold text-stone-400">1.</span> Susun dokumen di Word, tandai bagian otomatis dengan variabel terdaftar.</li>
                        <li class="flex gap-2"><span class="font-semibold text-stone-400">2.</span> Unggah — variabel diperiksa sebelum template disimpan.</li>
                        <li class="flex gap-2"><span class="font-semibold text-stone-400">3.</span> Pilih template untuk jenis kegiatan atau leading di Format Seragam.</li>
                    </ol>
                    @unless ($data['terkunci'])
                        <button type="button" class="btn-primary mt-5" wire:click="bukaUnggah" data-testid="unggah-template-pertama"><x-ui.icon name="upload" class="size-4" /> Unggah template pertama</button>
                    @endunless
                </section>
            @elseif ($data['tersaring'] === 0)
                <section class="rounded-2xl bg-white px-6 py-8 text-center shadow-xs ring-1 ring-stone-200" data-testid="template-master-tanpa-hasil">
                    <p class="text-sm text-stone-600">Tidak ada template yang cocok dengan saringan ini.</p>
                    <button type="button" class="btn-secondary mt-3" wire:click="hapusSaringan">Hapus saringan</button>
                </section>
            @else
                @foreach ($data['kelompok'] as $kelompok)
                    <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kelompok-{{ $kelompok['kunci'] }}" data-testid="kelompok-template" data-kunci="{{ $kelompok['kunci'] }}" wire:key="kelompok-{{ $kelompok['kunci'] }}">
                        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-3 sm:px-6">
                            <div class="min-w-0">
                                <h2 id="judul-kelompok-{{ $kelompok['kunci'] }}" class="flex items-center gap-2 text-sm font-semibold text-stone-900">
                                    <x-ui.icon :name="$kelompok['leading'] === null ? 'layers' : 'users'" class="size-4 text-stone-400" />
                                    {{ $kelompok['label'] }}
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">{{ count($kelompok['template']) }}</span>
                                </h2>
                            </div>
                            <span class="flex flex-wrap items-center gap-3">
                            @unless ($data['terkunci'] || ($kelompok['leading'] !== null && ! collect(MasterKode::leadings())->contains('kode', $kelompok['leading'])))
                                <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="bukaUnggah('{{ $kelompok['leading'] ?? '' }}')" data-testid="unggah-untuk-pemilik"><x-ui.icon name="plus" class="size-3.5" /> Tambah template</button>
                            @endunless
                            @if ($kelompok['default'] !== [])
                                <p class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" data-testid="default-kelompok">
                                    <span>Default:</span>
                                    @foreach ($kelompok['default'] as $jenisDefault => $namaDefault)
                                        <span class="rounded-md bg-brand-50 px-1.5 py-0.5 font-medium text-brand-800">{{ $jenisDefault }} — {{ $namaDefault }}</span>
                                    @endforeach
                                </p>
                            @endif
                            </span>
                        </header>
                        <ul class="divide-y divide-stone-100">
                            @foreach ($kelompok['template'] as $t)
                                <li @class(['flex flex-col gap-3 px-5 py-4 sm:flex-row sm:flex-wrap sm:items-start sm:px-6', 'bg-brand-50/50' => $this->baru === $t['id']]) data-testid="template-master" data-id="{{ $t['id'] }}" data-status="{{ $t['aktif'] ? 'aktif' : 'nonaktif' }}" data-default="{{ $t['default'] ? 'ya' : 'tidak' }}" wire:key="template-{{ $t['id'] }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-2">
                                            <span @class(['text-sm font-semibold', 'text-stone-900' => $t['aktif'], 'text-stone-500' => ! $t['aktif']])>{{ $t['nama'] }}</span>
                                            <span class="rounded-md bg-stone-100 px-1.5 py-0.5 text-[11px] font-semibold text-stone-700" title="{{ $t['label_jenis'] }}">{{ $t['jenis'] }}</span>
                                            @if ($t['default'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset"><x-ui.icon name="check" class="size-3" /> Default</span>
                                            @endif
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ DaftarTemplateMaster::STATUS[$t['aktif'] ? 'aktif' : 'nonaktif'][1] }}" data-testid="status-template">{{ DaftarTemplateMaster::STATUS[$t['aktif'] ? 'aktif' : 'nonaktif'][0] }}</span>
                                        </p>
                                        <p @class(['mt-1 text-sm', 'text-stone-600' => $t['catatan'], 'text-stone-400 italic' => ! $t['catatan']]) data-testid="catatan-template">{{ $t['catatan'] ?? 'Belum ada catatan versi.' }}</p>
                                        <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                                            <span class="inline-flex items-center gap-1"><x-ui.icon name="tag" class="size-3.5" /> {{ $t['variabel'] }} variabel</span>
                                            @if ($t['ada_berkas'])
                                                <span class="inline-flex min-w-0 items-center gap-1"><x-ui.icon name="file-text" class="size-3.5 shrink-0" /> <span class="truncate">{{ $t['nama_berkas'] ?? 'master.docx' }}</span></span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-gold-700" data-testid="berkas-hilang"><x-ui.icon name="alert" class="size-3.5" /> Berkas tidak ditemukan</span>
                                            @endif
                                            @if ($t['diperbarui_pada'])
                                                <span>Diperbarui {{ Format::tanggal(Carbon::parse($t['diperbarui_pada'])->toDateString()) }}{{ $t['pengunggah'] ? ' · diunggah '.$t['pengunggah'] : '' }}</span>
                                            @endif
                                        </p>
                                        <p class="mt-1.5 text-xs text-stone-500" data-testid="pemakaian-template" data-jumlah="{{ count($t['dipakai']) + $t['kegiatan'] }}">
                                            @if ($t['dipakai'] === [] && $t['kegiatan'] === 0)
                                                Belum dipakai pilihan template maupun kegiatan.
                                            @else
                                                Dipakai:
                                                @foreach ($t['dipakai'] as $p)
                                                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-stone-700">{{ $p['label_jenis'] }}{{ $p['leading'] ? ' · '.$p['leading'] : '' }}</span>
                                                @endforeach
                                                @if ($t['kegiatan'] > 0)
                                                    <span class="font-medium text-stone-700">{{ $t['kegiatan'] }} kegiatan TA {{ $data['tahun'] }}</span>
                                                @endif
                                            @endif
                                            @if ($t['dokumen'] > 0)
                                                <span class="inline-flex items-center gap-1 text-stone-600" data-testid="dokumen-template" data-jumlah="{{ $t['dokumen'] }}" title="Template yang sudah dipakai dokumen tidak bisa dihapus — hanya dinonaktifkan.">
                                                    <x-ui.icon name="lock" class="size-3" /> {{ $t['dokumen'] }} dokumen tercatat
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-1.5 sm:justify-end">
                                        @if ($t['ada_berkas'])
                                            <a href="{{ $t['tautan_unduh'] }}" class="btn-ghost px-2.5 py-1 text-xs" data-testid="unduh-template-master"><x-ui.icon name="download" class="size-3.5" /> Unduh</a>
                                        @endif
                                        @unless ($data['terkunci'])
                                            @unless ($t['default'] || ! $t['aktif'])
                                                <button type="button" class="btn-ghost px-2.5 py-1 text-xs" wire:click="jadikanTemplateDefault('{{ $t['id'] }}')" wire:loading.attr="disabled" wire:target="jadikanTemplateDefault('{{ $t['id'] }}')" data-testid="jadikan-default"><x-ui.icon name="check" class="size-3.5" /> Jadikan default</button>
                                            @endunless
                                            @if ($this->diubah !== $t['id'])
                                                <button type="button" class="btn-ghost px-2.5 py-1 text-xs" wire:click="ubahTemplate('{{ $t['id'] }}')" data-testid="ubah-template"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                                            @endif
                                        @endunless
                                        @unless ($data['terkunci'])
                                            @if ($t['aktif'])
                                                @if ($this->akanNonaktif !== $t['id'])
                                                    <button type="button" class="btn-ghost px-2.5 py-1 text-xs text-red-700 hover:text-red-800" wire:click="mintaNonaktif('{{ $t['id'] }}')" data-testid="nonaktifkan-template"><x-ui.icon name="lock" class="size-3.5" /> Nonaktifkan</button>
                                                @endif
                                            @else
                                                <button type="button" class="btn-ghost px-2.5 py-1 text-xs" wire:click="aktifkan('{{ $t['id'] }}')" wire:loading.attr="disabled" wire:target="aktifkan('{{ $t['id'] }}')" data-testid="aktifkan-template"><x-ui.icon name="check" class="size-3.5" /> Aktifkan</button>
                                            @endif
                                        @endunless
                                    </div>
                                    @if ($this->akanNonaktif === $t['id'] && ($dampak = $this->dampakNonaktif))
                                        {{-- Peringatan dampak menonaktifkan (KelolaTemplateMaster::dampak). --}}
                                        <div class="w-full space-y-2 rounded-xl bg-red-50 p-3 text-sm text-red-900 ring-1 ring-red-200 sm:basis-full sm:p-4" role="alert" data-testid="konfirmasi-nonaktif-template"
                                             data-dipilih="{{ count($dampak['dipilih']) }}" data-beralih="{{ count($dampak['beralih']) }}" data-aturan="{{ count($dampak['aturan']) + count($dampak['pilihan']) }}"
                                             x-data x-on:keydown.escape.window="$wire.batalNonaktif()">
                                            <p class="font-semibold">Nonaktifkan “{{ $t['nama'] }}”?</p>
                                            <ul class="ml-4 list-disc space-y-1 text-xs">
                                                <li>Template tidak muncul lagi di pilihan baru (dropdown kegiatan & pilihan default).</li>
                                                @if ($dampak['default'])
                                                    <li data-testid="dampak-default">Template ini default {{ $t['jenis'] }} {{ mb_lcfirst($kelompok['label']) }} — default {{ $dampak['pengganti'] ? 'pindah ke “'.$dampak['pengganti'].'”' : 'menjadi kosong (memakai template umum atau susunan baku)' }}.</li>
                                                @endif
                                                @if ($dampak['dipilih'] !== [])
                                                    <li data-testid="dampak-dipilih">{{ count($dampak['dipilih']) }} kegiatan memilih template ini dan <span class="font-semibold">tetap memakainya</span>: {{ collect($dampak['dipilih'])->take(5)->pluck('nama')->implode(', ') }}{{ count($dampak['dipilih']) > 5 ? ', …' : '' }}.</li>
                                                @endif
                                                @if ($dampak['beralih'] !== [])
                                                    <li data-testid="dampak-beralih">{{ count($dampak['beralih']) }} kegiatan memakainya sebagai default leading dan <span class="font-semibold">akan beralih</span>: {{ collect($dampak['beralih'])->take(5)->pluck('nama')->implode(', ') }}{{ count($dampak['beralih']) > 5 ? ', …' : '' }}.</li>
                                                @endif
                                                @if ($dampak['pilihan'] !== [])
                                                    <li data-testid="dampak-pilihan">Ditunjuk aturan Format Seragam ({{ implode(', ', $dampak['pilihan']) }}) — aturan itu <span class="font-semibold">dilewati</span> selama template nonaktif{{ $dampak['aturan'] !== [] ? ' dan '.count($dampak['aturan']).' kegiatan beralih ke aturan berikutnya' : '' }}; ganti aturannya di Format Seragam bila perlu.</li>
                                                @endif
                                                <li>Dokumen lama yang memakainya tetap utuh; template bisa diaktifkan kembali kapan saja.</li>
                                            </ul>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="btn-ghost text-sm" wire:click="batalNonaktif">Batal</button>
                                                <button type="button" class="btn-danger text-sm" wire:click="nonaktifkan" wire:loading.attr="disabled" wire:target="nonaktifkan" data-testid="ya-nonaktifkan-template">Ya, nonaktifkan</button>
                                            </div>
                                        </div>
                                    @endif
                                    @if ($this->diubah === $t['id'])
                                        {{-- Nama, catatan versi, & default (KelolaTemplateMaster); Enter simpan, Esc batal. --}}
                                        <form wire:submit="simpanUbah" x-on:keydown.escape.prevent.stop="$wire.batalUbah()" class="w-full space-y-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200 sm:order-last sm:basis-full sm:p-4" data-testid="form-ubah-template">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <x-ui.field label="Nama template" for="ubah-nama-{{ $t['id'] }}" error="isianUbah.nama">
                                                    <input type="text" id="ubah-nama-{{ $t['id'] }}" wire:model="isianUbah.nama" maxlength="100" class="form-input text-sm" data-testid="ubah-nama-template" x-init="$el.focus()" @error('isianUbah.nama') aria-invalid="true" @enderror>
                                                </x-ui.field>
                                                <x-ui.field label="Catatan versi" for="ubah-catatan-{{ $t['id'] }}" error="isianUbah.catatan">
                                                    <input type="text" id="ubah-catatan-{{ $t['id'] }}" wire:model="isianUbah.catatan" maxlength="{{ \App\Support\Pengaturan\UnggahMasterTemplate::MAKS_CATATAN }}" placeholder="mis. Versi 2 — dengan narasumber eksternal" class="form-input text-sm" data-testid="ubah-catatan-template" @error('isianUbah.catatan') aria-invalid="true" @enderror>
                                                </x-ui.field>
                                            </div>
                                            @if ($t['default'])
                                                <p class="flex items-center gap-1.5 text-xs text-brand-800"><x-ui.icon name="check-circle" class="size-3.5" /> Sudah default {{ $t['jenis'] }} {{ mb_lcfirst($kelompok['label']) }}. Pilih template lain sebagai default untuk menggantinya.</p>
                                            @elseif ($t['aktif'])
                                                <label class="flex items-start gap-2 text-sm text-stone-700">
                                                    <input type="checkbox" wire:model="isianUbah.default" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="ubah-default-template">
                                                    <span>Jadikan default {{ $t['jenis'] }} {{ mb_lcfirst($kelompok['label']) }}<span class="block text-xs text-stone-500">Default lama tetap tersimpan sebagai pilihan.</span></span>
                                                </label>
                                            @endif
                                            @error('isianUbah.default') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="btn-ghost text-sm" wire:click="batalUbah">Batal</button>
                                                <button type="submit" class="btn-primary text-sm" wire:loading.attr="disabled" wire:target="simpanUbah" data-testid="simpan-ubah-template">
                                                    <span wire:loading.remove wire:target="simpanUbah">Simpan</span><span wire:loading wire:target="simpanUbah">Menyimpan…</span>
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tanpa-khusus" data-testid="leading-tanpa-template" data-jumlah="{{ count($data['tanpa_khusus']) }}">
                <h2 id="judul-tanpa-khusus" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Leading tanpa template khusus</h2>
                @if ($data['tanpa_khusus'] === [])
                    <p class="mt-2 text-xs text-stone-500">Semua leading aktif sudah punya template sendiri.</p>
                @else
                    <p class="mt-1 text-xs text-stone-500">Dokumennya memakai template umum (bila ada) atau susunan baku aplikasi.</p>
                    <ul class="mt-2 space-y-1 text-sm text-stone-700">
                        @foreach ($data['tanpa_khusus'] as $l)
                            <li class="flex gap-2"><span class="w-8 shrink-0 font-semibold text-stone-500 tabular-nums">{{ $l['kode'] }}</span> <span class="min-w-0">{{ $l['nama_unit'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-aturan-master">
                <h2 id="judul-aturan-master" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Aturan template master</h2>
                <ul class="mt-3 space-y-2.5 text-xs text-stone-600">
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Pemilik: umum (semua leading) atau satu leading; satu leading boleh punya beberapa template per jenis.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Satu default per pemilik × jenis — dipakai otomatis saat kegiatan baru dibuat.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Variabel <code class="rounded bg-stone-100 px-1">${…}</code> wajib terdaftar agar salah tulis terdeteksi.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" /> Template nonaktif tidak muncul di pilihan baru; yang sudah dipakai tidak dihapus, hanya dinonaktifkan.</li>
                </ul>
            </section>
        </aside>
    </div>

    @if ($unggahTerbuka)
        @include('livewire.pengaturan.partials.unggah-template-master')
    @endif
</div>
