@use('App\Enums\StatusKegiatan')
@use('App\Support\Kegiatan\ArsipFinal')
@use('Illuminate\Support\Carbon')

@php
    $arsip = $this->arsip;
    $sumber = $this->sumber;
    $boleh = ArsipFinal::bolehUnggah($this->status);
    $jumlahAda = count(array_intersect($sumber, array_keys($arsip)));
    $lengkap = $sumber !== [] && $jumlahAda === count($sumber);
@endphp

{{-- Arsip final bertanda tangan per sumber dana (PRD F18): unggah selama Disetujui, baca-saja selama Final. --}}
<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" aria-labelledby="judul-arsip-final" data-testid="panel-arsip-final">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <h2 id="judul-arsip-final" class="flex items-center gap-1.5 text-base font-semibold text-stone-900">
                <x-ui.icon :name="$this->status === StatusKegiatan::Final ? 'lock' : 'file-text'" class="size-4 text-brand-700" />
                Arsip final bertanda tangan
            </h2>
            <p class="mt-0.5 text-sm text-stone-500">
                @if ($boleh)
                    Cetak KAK/TOR & RAB, mintakan tanda tangan, lalu unggah hasil pindaiannya (PDF) untuk tiap sumber dana. Setelah lengkap, tandai final di panel status untuk mengunci data.
                @else
                    Arsip yang diunggah sebelum kegiatan ditandai final. Data terkunci; hanya admin yang dapat membuka kunci.
                @endif
            </p>
        </div>
        @if ($sumber !== [])
            <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', 'bg-brand-50 text-brand-800 ring-brand-200' => $lengkap, 'bg-gold-50 text-gold-700 ring-gold-200' => ! $lengkap]) data-testid="jumlah-arsip">
                {{ $jumlahAda }} dari {{ count($sumber) }} diunggah
            </span>
        @endif
    </div>

    @if ($sumber === [])
        <p class="mt-3 rounded-lg bg-stone-50 px-3 py-2 text-sm text-stone-600 ring-1 ring-stone-200">Kegiatan belum memilih sumber dana, jadi tidak ada arsip per sumber dana yang perlu diunggah.</p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($sumber as $s)
                @php
                    $a = $arsip[$s] ?? null;
                    $idInput = 'arsip-'.mb_strtolower($s);
                @endphp
                <li class="rounded-xl p-3 ring-1 ring-stone-200" data-arsip="{{ $s }}" wire:key="arsip-{{ $s }}"
                    x-data="{ unggah: false, persen: 0 }"
                    {{-- Memilih berkas bukan perubahan isian formulir: jangan sampai ke penanda "belum disimpan" Data Kegiatan. --}}
                    x-on:input.stop
                    x-on:livewire-upload-start="unggah = true; persen = 0"
                    x-on:livewire-upload-progress="persen = $event.detail.progress"
                    x-on:livewire-upload-finish="unggah = false"
                    x-on:livewire-upload-error="unggah = false"
                    x-on:livewire-upload-cancel="unggah = false">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-rab.lencana-sumber :sumber="$s" awalan="RAB" class="text-xs" />
                        <div class="min-w-0 flex-1 text-sm">
                            @if ($a)
                                <p class="flex items-center gap-1.5 font-medium text-stone-900">
                                    <x-ui.icon name="check-circle" class="size-4 shrink-0 text-brand-700" />
                                    <span class="truncate" data-nama-arsip>{{ $a['nama_berkas'] }}</span>
                                    <span class="shrink-0 text-xs font-normal text-stone-500">{{ ArsipFinal::ukuran($a['ukuran']) }}</span>
                                </p>
                                <p class="text-xs text-stone-500">Diunggah {{ $a['diunggah_oleh'] }} · {{ Carbon::parse($a['diunggah_pada'])->locale('id')->translatedFormat('j F Y, H.i') }}</p>
                            @else
                                <p class="flex items-center gap-1.5 font-medium text-gold-700"><x-ui.icon name="alert" class="size-4 shrink-0" /> Belum diunggah</p>
                                <p class="text-xs text-stone-500">PDF hasil pindaian KAK/TOR & RAB {{ $s }} yang sudah ditandatangani, maks. {{ ArsipFinal::MAKS_KB / 1024 }} MB.</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($a)
                                <a href="{{ route('kegiatan.arsip', ['kegiatan' => $kegiatanId, 'sumber' => $s, 'v' => $a['diunggah_pada']]) }}" target="_blank" rel="noopener" class="btn-ghost px-2.5 py-1.5 text-sm">
                                    <x-ui.icon name="eye" class="size-4" /> Lihat
                                </a>
                            @endif
                            @if ($boleh && $konfirmasiHapus !== $s)
                                <label for="{{ $idInput }}" class="btn-secondary cursor-pointer px-2.5 py-1.5 text-sm has-focus-visible:outline-2 has-focus-visible:outline-brand-600" x-bind:class="{ 'pointer-events-none opacity-60': unggah }">
                                    <x-ui.icon name="upload" class="size-4" /> {{ $a ? 'Ganti' : 'Unggah PDF' }}
                                    <input type="file" id="{{ $idInput }}" wire:model="berkas.{{ $s }}" accept="application/pdf,.pdf" class="sr-only" data-testid="input-arsip-{{ mb_strtolower($s) }}">
                                </label>
                                @if ($a)
                                    <button type="button" wire:click="$set('konfirmasiHapus', '{{ $s }}')" class="btn-ghost px-2 py-1.5 text-sm text-red-700 hover:bg-red-50" aria-label="Hapus arsip RAB {{ $s }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($konfirmasiHapus === $s && $a)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-200" role="group" aria-label="Konfirmasi hapus arsip RAB {{ $s }}">
                            <span class="text-sm text-red-800">Hapus arsip RAB {{ $s }}? Kegiatan belum bisa ditandai final sampai diunggah lagi.</span>
                            <span class="flex gap-1.5">
                                <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="$set('konfirmasiHapus', null)">Batal</button>
                                <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="hapus('{{ $s }}')">Hapus</button>
                            </span>
                        </div>
                    @endif

                    <div x-show="unggah" x-cloak class="mt-3">
                        <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                        <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
                    </div>
                    @error('berkas.'.$s)
                        <p class="mt-3 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-arsip-{{ mb_strtolower($s) }}">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                        </p>
                    @enderror
                </li>
            @endforeach
        </ul>

        @if ($boleh && $lengkap)
            <p class="mt-3 flex items-center gap-1.5 text-sm font-medium text-brand-800" data-testid="arsip-lengkap">
                <x-ui.icon name="check-circle" class="size-4" /> Semua arsip lengkap — tandai final di panel status untuk mengunci data.
            </p>
        @endif
    @endif
</section>
