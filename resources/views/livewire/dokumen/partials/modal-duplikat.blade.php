{{--
    Modal Duplikat dokumen Riwayat Dokumen (AksiRiwayatDokumen::rencanaDuplikat → duplikat): salin kegiatan dokumen itu
    sebagai dasar kegiatan baru — tahun lalu menjadi draf tahun aktif (nama tetap), tahun aktif menjadi kegiatan baru
    bernama lain (`namaDuplikat`). Tidak bisa → alasan + tautan; selesai → tautan draf baru & Susun KAK/TOR jenis asal.
    Esc/latar menutup (`tutupDuplikat`). Variabel: $rencana, $hasil.
--}}
@php
    $dok = $rencana['dokumen'];
@endphp
<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="judul-duplikat-dokumen"
     data-testid="modal-duplikat-dokumen" data-id="{{ $dok['id'] }}" data-mode="{{ $rencana['mode'] ?? 'tidak' }}" wire:key="modal-duplikat-{{ $dok['id'] }}"
     x-data x-trap.inert.noscroll="true" x-init="$nextTick(() => ($refs.nama ?? $refs.utama)?.focus())" x-on:keydown.escape.window="$wire.tutupDuplikat()" x-on:click.self="$wire.tutupDuplikat()">
    <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl ring-1 ring-stone-200 sm:rounded-2xl">
        <header class="flex items-start gap-3 border-b border-stone-100 px-5 py-4">
            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-ui.icon name="copy" class="size-4" /></span>
            <div class="min-w-0 flex-1">
                <h2 id="judul-duplikat-dokumen" class="text-base font-semibold text-stone-900">{{ $hasil ? 'Kegiatan baru dibuat' : 'Salin sebagai kegiatan baru' }}</h2>
                <p class="mt-0.5 text-sm text-stone-500">Dari {{ $dok['label_jenis'] }}{{ $dok['sumber'] ? ' '.$dok['sumber'] : '' }} v{{ $dok['versi'] }} · {{ $dok['nama_kegiatan'] }} · TA {{ $dok['tahun_anggaran'] }}</p>
            </div>
            <button type="button" wire:click="tutupDuplikat" class="-mr-1 rounded-md p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" data-testid="tutup-duplikat-dokumen">
                <x-ui.icon name="x" class="size-5" /><span class="sr-only">Tutup</span>
            </button>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4 text-sm">
            @if ($hasil)
                <div class="flex items-start gap-2.5 rounded-xl bg-brand-50 px-4 py-3 text-brand-900 ring-1 ring-brand-200" data-testid="hasil-duplikat-dokumen">
                    <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
                    <p>{{ $hasil['pesan'] }} Lengkapi isiannya, lalu susun dokumennya.</p>
                </div>
                @foreach ($hasil['catatan'] as $c)
                    <p class="flex items-start gap-1.5 text-xs text-gold-800" data-catatan="{{ $c['jenis'] }}"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $c['teks'] }}</p>
                @endforeach
            @elseif (! $rencana['bisa'])
                <div class="flex items-start gap-2.5 rounded-xl bg-stone-50 px-4 py-3 text-stone-700 ring-1 ring-stone-200" role="alert" data-testid="duplikat-tidak-bisa">
                    <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-stone-500" />
                    <p>{{ $rencana['alasan'] }}</p>
                </div>
            @else
                <p class="text-stone-600">
                    @if ($rencana['mode'] === 'tahun_lalu')
                        Kegiatan TA {{ $dok['tahun_anggaran'] }} ini disalin menjadi draf TA {{ $rencana['tahun'] }} beserta RAB, termin, matrik, dan jadwalnya. Kegiatan aslinya tidak berubah.
                    @else
                        Data kegiatan, RAB, termin, matrik, dan jadwalnya disalin menjadi kegiatan baru TA {{ $rencana['tahun'] }} berstatus Draf. Kegiatan asal dan dokumennya tidak berubah.
                    @endif
                </p>

                <label class="block">
                    <span class="form-label">Nama kegiatan baru</span>
                    @if ($rencana['nama_terkunci'])
                        <span class="mt-1 block rounded-lg bg-stone-50 px-3 py-2 text-stone-800 ring-1 ring-stone-200" data-testid="nama-duplikat-tetap">{{ $rencana['nama'] }}</span>
                        <span class="mt-1 block text-xs text-stone-500">Nama tetap sama agar kegiatan TA {{ $rencana['tahun'] }} mudah dikenali; bisa diubah setelah disalin.</span>
                    @else
                        <input type="text" wire:model.live.blur="namaDuplikat" x-ref="nama" maxlength="{{ \App\Support\Dokumen\AksiRiwayatDokumen::MAKS_NAMA }}" x-on:keydown.enter.prevent="$wire.namaDuplikat = $el.value; $wire.duplikat()"
                               @class(['form-input mt-1', 'ring-red-400' => $errors->has('namaDuplikat')]) aria-describedby="galat-nama-duplikat" data-testid="nama-duplikat">
                        @error('namaDuplikat')
                            <span id="galat-nama-duplikat" class="mt-1 block text-xs font-medium text-red-700" role="alert" data-testid="galat-nama-duplikat">{{ $message }}</span>
                        @else
                            <span id="galat-nama-duplikat" class="mt-1 block text-xs text-stone-500">Nama harus berbeda dari kegiatan lain di TA {{ $rencana['tahun'] }}.</span>
                        @enderror
                    @endif
                </label>

                <div>
                    <p class="text-xs font-semibold text-stone-500">Yang diperbarui otomatis</p>
                    <dl class="mt-1.5 space-y-1.5" data-testid="perubahan-duplikat">
                        @foreach ($rencana['perubahan'] as [$judulUbah, $teksUbah])
                            <div class="flex gap-2 text-xs">
                                <dt class="w-28 shrink-0 font-semibold text-stone-700">{{ $judulUbah }}</dt>
                                <dd class="text-stone-600">{{ $teksUbah }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                @if ($rencana['catatan'] !== [])
                    <div class="space-y-1" data-testid="catatan-duplikat">
                        @foreach ($rencana['catatan'] as $c)
                            <p class="flex items-start gap-1.5 text-xs text-gold-800" data-catatan="{{ $c['jenis'] }}"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $c['teks'] }}</p>
                        @endforeach
                    </div>
                @endif
            @endif

            @error('duplikat')
                <p class="flex items-start gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-800 ring-1 ring-red-200" role="alert" data-testid="galat-duplikat"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $message }}</p>
            @enderror
        </div>

        <footer class="flex flex-wrap justify-end gap-2 border-t border-stone-100 px-5 py-3">
            @if ($hasil)
                <button type="button" wire:click="tutupDuplikat" class="btn-ghost">Tutup</button>
                <a href="{{ $hasil['tautan_dokumen'] }}" wire:navigate class="btn-secondary" data-testid="hasil-susun-dokumen"><x-ui.icon name="file-text" class="size-4" /> Susun {{ $dok['label_jenis'] }}</a>
                <a href="{{ $hasil['tautan'] }}" wire:navigate class="btn-primary" x-ref="utama" data-testid="hasil-buka-kegiatan"><x-ui.icon name="pencil" class="size-4" /> Buka kegiatan baru</a>
            @elseif (! $rencana['bisa'])
                <button type="button" wire:click="tutupDuplikat" class="btn-ghost" @if (! $rencana['tautan']) x-ref="utama" @endif>Tutup</button>
                @if ($rencana['tautan'])
                    <a href="{{ $rencana['tautan']['url'] }}" wire:navigate class="btn-primary" x-ref="utama" data-testid="tautan-duplikat">{{ $rencana['tautan']['label'] }}</a>
                @endif
            @else
                <button type="button" wire:click="tutupDuplikat" class="btn-ghost">Batal</button>
                <button type="button" wire:click="duplikat" wire:loading.attr="disabled" wire:target="duplikat" class="btn-primary" x-ref="utama" data-testid="konfirmasi-duplikat-dokumen">
                    <x-ui.icon name="copy" class="size-4" /> Salin menjadi draf
                </button>
            @endif
        </footer>
    </div>
</div>
