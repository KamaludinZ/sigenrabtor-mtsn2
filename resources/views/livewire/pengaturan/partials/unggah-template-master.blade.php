@use('App\Contracts\TemplateDokumenRepository')
@use('App\Support\MasterKode')
@use('App\Support\Pengaturan\UnggahMasterTemplate')

{{--
    Panel unggah template Word berpenanda (TemplateMaster): berkas .docx dibaca seketika — penanda ${…} terdaftar/tidak
    beserta saran ejaan (AnalisisTemplate) dan isi dokumen dengan penanda disorot (PratinjauTemplate mode penanda) —
    lalu nama, jenis, pemilik (umum/leading), catatan versi, dan jadikan default pemilik × jenis.
--}}
@php
    $analisis = $this->analisis;
    $tidakTerdaftar = $analisis?->tidakTerdaftar() ?? [];
    $bisaSimpan = $this->validasi['bisa_disimpan'] ?? false;
    $galatPenanda = $this->validasi['galat']['penanda'] ?? null;
    $adaDefault = $this->adaDefault;
@endphp

<div class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-labelledby="judul-unggah-template" data-testid="form-unggah-master"
     x-data x-on:keydown.escape.window="$wire.tutupUnggah()">
    <div class="absolute inset-0 bg-stone-900/40" wire:click="tutupUnggah" aria-hidden="true"></div>
    <form wire:submit="simpanUnggah" class="relative flex h-full w-full max-w-2xl flex-col bg-white shadow-xl" x-trap.noscroll="true">
        <header class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
            <div>
                <h2 id="judul-unggah-template" class="text-base font-semibold text-stone-900">Unggah template Word berpenanda</h2>
                <p class="mt-0.5 text-sm text-stone-500">Tulis penanda <code class="rounded bg-stone-100 px-1 text-xs">${nama_variabel}</code> di bagian yang diisi otomatis. Penanda diperiksa sebelum template boleh disimpan.</p>
            </div>
            <button type="button" class="btn-ghost -mr-2 p-1.5" wire:click="tutupUnggah" aria-label="Tutup"><x-ui.icon name="x" class="size-5" /></button>
        </header>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
            {{-- Berkas (wire:key per keadaan agar status Alpine unggah dibuat ulang) --}}
            <div wire:key="berkas-master-{{ $analisis ? 'terbaca' : 'memilih' }}"
                 x-data="{ unggah: false, persen: 0 }"
                 x-on:livewire-upload-start="unggah = true; persen = 0"
                 x-on:livewire-upload-progress="persen = $event.detail.progress"
                 x-on:livewire-upload-finish="unggah = false"
                 x-on:livewire-upload-error="unggah = false">
                @if (! $analisis)
                    <label for="berkas-template-master"
                           class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-300 px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40 has-focus-visible:outline-2 has-focus-visible:outline-brand-600">
                        <x-ui.icon name="upload" class="size-8 text-stone-400" />
                        <span class="text-sm font-semibold text-stone-800">Pilih berkas template .docx</span>
                        <span class="text-xs text-stone-500">Dokumen Word .docx · maks. {{ UnggahMasterTemplate::MAKS_KB / 1024 }} MB</span>
                        <input type="file" id="berkas-template-master" wire:model="berkas" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="sr-only" data-testid="input-berkas-template">
                    </label>
                    <div x-show="unggah" x-cloak class="mt-2">
                        <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                        <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                    </div>
                    <p class="mt-2 flex items-center gap-2 text-sm text-stone-600" wire:loading.flex wire:target="berkas">
                        <x-ui.icon name="loader" class="size-4 animate-spin" /> Membaca penanda di dokumen…
                    </p>
                @else
                    <div class="flex flex-wrap items-start gap-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="berkas-terbaca">
                        <x-ui.icon name="file-text" class="mt-0.5 size-5 shrink-0 text-stone-500" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $berkas?->getClientOriginalName() }}</p>
                            <p class="text-xs text-stone-500" data-testid="ringkasan-penanda" data-jumlah="{{ count($analisis->variabel) }}" data-tidak-terdaftar="{{ count($tidakTerdaftar) }}">
                                {{ count($analisis->variabel) }} penanda · {{ count($analisis->variabel) - count($tidakTerdaftar) }} terdaftar{{ $tidakTerdaftar !== [] ? ' · '.count($tidakTerdaftar).' tidak terdaftar' : '' }}
                            </p>
                        </div>
                        <label for="ganti-berkas-template" class="btn-ghost cursor-pointer px-2.5 py-1 text-xs">
                            Ganti berkas
                            <input type="file" id="ganti-berkas-template" wire:model="berkas" accept=".docx" class="sr-only">
                        </label>
                    </div>
                @endif
                @error('berkas')
                    <p class="mt-2 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-berkas-template">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                    </p>
                @enderror
            </div>

            @if ($analisis)
                @if ($galatPenanda)
                    <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-penanda">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $galatPenanda }}
                    </p>
                @endif
                @if ($analisis->variabel === [])
                    <p class="flex items-start gap-2 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-700 ring-1 ring-gold-200" role="alert" data-testid="tanpa-penanda">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        Belum ada penanda ${…} di dokumen ini. Tandai bagian yang diisi otomatis, mis. ${nama_kegiatan} atau ${total}, lalu unggah ulang.
                    </p>
                @else
                    <section aria-labelledby="judul-penanda" data-testid="daftar-penanda">
                        <h3 id="judul-penanda" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Penanda di dokumen</h3>
                        @if ($tidakTerdaftar !== [])
                            <p class="mt-2 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="penanda-tidak-terdaftar">
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                                {{ count($tidakTerdaftar) }} penanda tidak terdaftar — kemungkinan salah tulis. Perbaiki di Word lalu unggah ulang.
                            </p>
                        @endif
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($analisis->variabel as $v)
                                <li @class(['rounded-lg px-2 py-1 text-xs ring-1', 'bg-brand-50 text-brand-800 ring-brand-200' => $v['terdaftar'], 'bg-red-50 text-red-700 ring-red-200' => ! $v['terdaftar']])
                                    data-penanda-nama="{{ $v['nama'] }}" data-terdaftar="{{ $v['terdaftar'] ? 'ya' : 'tidak' }}" title="{{ $v['keterangan'] ?? 'Tidak terdaftar' }}">
                                    <code class="font-semibold">${{ '{' }}{{ $v['nama'] }}{{ '}' }}</code>@if ($v['jumlah'] > 1) <span class="opacity-70">×{{ $v['jumlah'] }}</span>@endif
                                    @if (! $v['terdaftar'] && $v['saran'])
                                        <span class="ml-1">→ maksud Anda <code class="font-semibold">${{ '{' }}{{ $v['saran'] }}{{ '}' }}</code>?</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($this->peringatanPenanda !== [])
                            <ul class="mt-2 space-y-1 rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800 ring-1 ring-gold-200" data-testid="peringatan-penanda">
                                @foreach ($this->peringatanPenanda as $p)
                                    <li class="flex items-start gap-1.5" data-kunci="{{ $p['kunci'] }}"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $p['pesan'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                    @if ($this->penanda)
                        <details class="rounded-xl bg-stone-50 ring-1 ring-stone-200" data-testid="isi-berpenanda">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Lihat isi dokumen dengan penanda disorot</summary>
                            <div class="max-h-96 overflow-auto border-t border-stone-200 p-3">
                                @include('livewire.pengaturan.partials.isi-master', ['isiMaster' => $this->penanda, 'modeMaster' => 'penanda'])
                            </div>
                        </details>
                    @endif
                @endif
            @endif

            {{-- Identitas template --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nama template" for="nama-template" error="nama" class="sm:col-span-2">
                    <input type="text" id="nama-template" wire:model="nama" maxlength="100" placeholder="mis. KAK/TOR kegiatan reguler" class="form-input" data-testid="nama-template" @error('nama') aria-invalid="true" @enderror>
                </x-ui.field>
                <x-ui.field label="Jenis" for="jenis-template" error="jenisBaru">
                    <select id="jenis-template" wire:model.live="jenisBaru" class="form-input" data-testid="jenis-template">
                        @foreach (TemplateDokumenRepository::JENIS as $kunciJenis => $labelJenis)
                            <option value="{{ $kunciJenis }}">{{ $labelJenis }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Pemilik" for="pemilik-template" error="pemilik">
                    <select id="pemilik-template" wire:model.live="pemilik" class="form-input" data-testid="pemilik-template">
                        <option value="">Umum — semua leading</option>
                        @foreach (MasterKode::leadings() as $l)
                            <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Catatan versi (opsional)" for="catatan-template" error="catatan" class="sm:col-span-2">
                    <input type="text" id="catatan-template" wire:model="catatan" maxlength="{{ UnggahMasterTemplate::MAKS_CATATAN }}" placeholder="mis. Versi 2 — dengan bagian narasumber eksternal" class="form-input" data-testid="catatan-template" @error('catatan') aria-invalid="true" @enderror>
                </x-ui.field>
            </div>
            <label @class(['flex items-start gap-2.5 rounded-lg px-3 py-2.5 text-sm ring-1', 'bg-stone-50 text-stone-700 ring-stone-200' => $adaDefault, 'bg-brand-50 text-brand-900 ring-brand-200' => ! $adaDefault]) data-testid="pilih-default-template" data-ada-default="{{ $adaDefault ? 'ya' : 'tidak' }}">
                @if ($adaDefault)
                    <input type="checkbox" wire:model="jadikanDefault" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="jadikan-default">
                    <span>Jadikan default {{ $jenisBaru }} {{ $pemilik === '' ? 'umum' : 'leading '.$pemilik }}<span class="block text-xs text-stone-500">Dipakai otomatis saat kegiatan baru dibuat; default lama tetap tersimpan sebagai pilihan.</span></span>
                @else
                    <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                    <span>Otomatis menjadi default {{ $jenisBaru }} {{ $pemilik === '' ? 'umum' : 'leading '.$pemilik }}<span class="block text-xs text-brand-800/80">Belum ada template {{ $jenisBaru }} untuk pemilik ini tahun ini.</span></span>
                @endif
            </label>
        </div>

        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
            <button type="button" class="btn-ghost" wire:click="tutupUnggah">Batal</button>
            {{-- Dua varian ber-wire:key: status disabled dari server tidak dicampur wire:loading.attr. --}}
            @if ($bisaSimpan)
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanUnggah,berkas" wire:key="simpan-template-bisa" data-testid="simpan-template">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="simpanUnggah">Simpan template</span><span wire:loading wire:target="simpanUnggah">Menyimpan…</span>
                </button>
            @else
                <button type="button" class="btn-primary" disabled wire:key="simpan-template-belum" data-testid="simpan-template-belum" title="{{ $analisis ? ($galatPenanda ?? UnggahMasterTemplate::pesanTidakBisaDisimpan($analisis)) : 'Pilih berkas .docx dulu' }}">
                    <x-ui.icon name="save" class="size-4" /> Simpan template
                </button>
            @endif
        </footer>
    </form>
</div>
