{{--
    Modal Lihat dokumen Riwayat Dokumen (AksiRiwayatDokumen::lihat): rincian catatan, keadaan kegiatan sekarang, cara
    membuka ulang (arsip final / pratinjau / cetak ulang / unduh dengan data terbaru), dan semua versi jenis × sumber ×
    format yang sama. Bila dokumen susunan ulang akan berbeda dari catatan ini (`perbedaan`: versi lama, total RAB, status,
    cap DRAF), tombol buka ulang baru tampil setelah dikonfirmasi (`konfirmasi-buka-ulang`, Alpine `setuju`; nilai awal lewat
    `data-perbedaan`); cetak ulang memberi notifikasi tab baru. Esc/latar menutup (`tutupLihat`). Variabel: $rincian, $waktu,
    $nadaStatus.
--}}
@php
    $d = $rincian['dokumen'];
    $buka = $rincian['buka'];
@endphp
<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="judul-lihat-dokumen"
     data-testid="modal-lihat-dokumen" data-id="{{ $d['id'] }}" wire:key="modal-lihat-{{ $d['id'] }}"
     x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.tutupLihat()" x-on:click.self="$wire.tutupLihat()">
    <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl ring-1 ring-stone-200 sm:rounded-2xl">
        <header class="flex items-start gap-3 border-b border-stone-100 px-5 py-4">
            <span @class(['mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-red-50 text-red-700' => $d['format'] === 'pdf', 'bg-sky-50 text-sky-700' => $d['format'] === 'docx', 'bg-brand-50 text-brand-700' => $d['format'] === 'xlsx'])>
                <x-ui.icon :name="$d['ikon_format']" class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="judul-lihat-dokumen" class="text-base font-semibold text-stone-900">{{ $d['label_jenis'] }} <span class="font-normal text-stone-500">· {{ $d['label_format'] }} · v{{ $d['versi'] }}</span></h2>
                <p class="mt-0.5 text-sm text-stone-600">{{ $d['nama_kegiatan'] }} · TA {{ $d['tahun_anggaran'] }}</p>
                <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                    @if ($d['sumber'])
                        <x-rab.lencana-sumber :sumber="$d['sumber']" />
                    @endif
                    <span @class(['inline-flex items-center rounded-full px-2 py-0.5 font-semibold ring-1', $nadaStatus[$d['status']] ?? 'bg-stone-100 text-stone-700 ring-stone-300'])>{{ $d['label_status'] }} saat itu</span>
                    @if ($d['bertanda_draf'])
                        <span class="font-bold tracking-wider text-red-700">DRAF</span>
                    @endif
                    <span @class(['rounded-full px-2 py-0.5 font-semibold', 'bg-brand-50 text-brand-800' => $rincian['terbaru'], 'bg-stone-100 text-stone-600' => ! $rincian['terbaru']]) data-testid="lihat-keadaan-versi">{{ $rincian['terbaru'] ? 'Versi terbaru' : 'Versi lama' }}</span>
                </p>
            </div>
            <button type="button" wire:click="tutupLihat" class="-mr-1 rounded-md p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" data-testid="tutup-lihat-dokumen">
                <x-ui.icon name="x" class="size-5" /><span class="sr-only">Tutup</span>
            </button>
        </header>

        <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4 text-sm">
            <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2" data-testid="rincian-dokumen">
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold text-stone-500">Nama berkas</dt>
                    <dd class="mt-0.5 font-mono text-xs break-all text-stone-800">{{ $d['nama_berkas'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-stone-500">Dibuat</dt>
                    <dd class="mt-0.5 text-stone-800">{{ $waktu($d['dibuat_pada']) }}</dd>
                    <dd class="text-xs text-stone-500">{{ $d['label_asal'] }} oleh {{ $d['dibuat_oleh'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-stone-500">Leading</dt>
                    <dd class="mt-0.5 text-stone-800">{{ $d['kode_leading'] !== '' ? $d['kode_leading'].($d['nama_leading'] ? ' · '.$d['nama_leading'] : '') : '—' }}</dd>
                </div>
                @if ($d['template_nama'] ?? null)
                    <div>
                        <dt class="text-xs font-semibold text-stone-500">Template Word</dt>
                        <dd class="mt-0.5 text-stone-800" data-testid="template-riwayat">{{ $d['template_nama'] }}</dd>
                    </div>
                @endif
                @if ($d['nomor_dokumen'])
                    <div>
                        <dt class="text-xs font-semibold text-stone-500">Nomor dokumen</dt>
                        <dd class="mt-0.5 font-mono text-xs text-stone-800">{{ $d['nomor_dokumen'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-semibold text-stone-500">Ukuran</dt>
                    <dd class="mt-0.5 text-stone-800 tabular-nums">{{ $d['ukuran_teks'] ?? '—' }}</dd>
                </div>
                @if ($d['total_teks'])
                    <div>
                        <dt class="text-xs font-semibold text-stone-500">Total RAB saat itu</dt>
                        <dd class="mt-0.5 text-stone-800 tabular-nums" data-testid="total-saat-itu">{{ $d['total_teks'] }}</dd>
                    </div>
                @endif
                @if ($d['ringkas']['jumlah_bagian'])
                    <div>
                        <dt class="text-xs font-semibold text-stone-500">Bagian KAK/TOR lengkap</dt>
                        <dd class="mt-0.5 text-stone-800 tabular-nums">{{ $d['ringkas']['bagian_lengkap'] }} dari {{ $d['ringkas']['jumlah_bagian'] }}</dd>
                    </div>
                @endif
            </dl>

            @if ($rincian['sekarang'])
                @php
                    $kini = $rincian['sekarang'];
                @endphp
                <div class="rounded-xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200" data-testid="keadaan-sekarang">
                    <p class="text-xs font-semibold text-stone-500">Kegiatan sekarang</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-stone-700">
                        <span>Status <span class="font-semibold text-stone-900">{{ $kini['label_status'] }}</span></span>
                        @if ($kini['total_teks'])
                            <span>Total RAB {{ $d['sumber'] }} <span class="font-semibold text-stone-900 tabular-nums">{{ $kini['total_teks'] }}</span></span>
                        @endif
                    </p>
                    @if ($kini['total_berubah'])
                        <p class="mt-1 flex items-start gap-1.5 text-xs text-gold-800" data-testid="total-berubah"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> Total RAB sudah berubah sejak dokumen ini dibuat ({{ $d['total_teks'] }}).</p>
                    @endif
                </div>
            @endif

            <section aria-labelledby="judul-buka-ulang" data-testid="buka-ulang">
                <h3 id="judul-buka-ulang" class="text-sm font-semibold text-stone-900">Buka ulang</h3>
                @if ($rincian['alasan_buka'])
                    <p class="mt-1.5 flex items-start gap-1.5 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200" data-testid="alasan-buka"><x-ui.icon name="info" class="mt-px size-3.5 shrink-0" /> {{ $rincian['alasan_buka'] }}</p>
                @else
                    <p class="mt-1 text-xs text-stone-500">
                        @if ($buka['arsip'])
                            PDF arsip final bertanda tangan tersimpan dan bisa dibuka apa adanya.
                        @endif
                        Berkas cetak & unduhan tidak disimpan — dokumen disusun ulang dari data kegiatan terbaru{{ $buka['draf'] ? ' (masih bercap DRAF)' : '' }}.
                    </p>
                    @if ($buka['arsip'])
                        <a href="{{ $buka['arsip'] }}" target="_blank" rel="noopener" class="btn-primary mt-2.5" data-testid="buka-arsip"
                           x-on:click="$dispatch('notify', { message: 'PDF arsip final dibuka di tab baru.', type: 'info' })"><x-ui.icon name="check-badge" class="size-4" /> Buka PDF arsip final</a>
                    @endif
                    <div data-perbedaan="{{ count($rincian['perbedaan']) }}" x-data="{ setuju: $el.dataset.perbedaan === '0' }">
                    @if ($rincian['perbedaan'] !== [])
                        <div x-show="! setuju" class="mt-2.5 rounded-lg bg-gold-50 px-3 py-2.5 text-xs text-gold-900 ring-1 ring-gold-200" role="alert" data-testid="konfirmasi-buka-ulang">
                            <p class="flex items-start gap-1.5 font-semibold"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> Dokumen yang dibuka ulang akan berbeda dari catatan v{{ $d['versi'] }} ini:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-6" role="list">
                                @foreach ($rincian['perbedaan'] as $beda)
                                    <li data-perbedaan="{{ $beda['kunci'] }}">{{ $beda['teks'] }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" x-on:click="setuju = true; $nextTick(() => $refs.bukaPertama?.focus())" class="btn-primary px-3 py-1.5 text-xs" data-testid="setujui-buka-ulang">Mengerti, buka dengan data terbaru</button>
                            </div>
                        </div>
                        <p x-cloak x-show="setuju" class="mt-2 flex items-start gap-1.5 text-xs text-gold-800" data-testid="buka-ulang-disetujui"><x-ui.icon name="info" class="mt-px size-3.5 shrink-0" /> Dibuka dengan data terbaru — {{ count($rincian['perbedaan']) }} perbedaan dari catatan v{{ $d['versi'] }}.</p>
                    @endif
                    <div @if ($rincian['perbedaan'] !== []) x-cloak @endif x-show="setuju" class="mt-2.5 flex flex-wrap items-start gap-2" data-testid="tombol-buka-ulang">
                        <a href="{{ $buka['pratinjau'] }}" wire:navigate class="btn-secondary" x-ref="bukaPertama" data-testid="buka-pratinjau"><x-ui.icon name="eye" class="size-4" /> Pratinjau</a>
                        <a href="{{ $buka['cetak'] }}" target="_blank" rel="noopener" class="btn-secondary" data-testid="cetak-ulang"
                           x-on:click="$dispatch('notify', { message: @js('Cetak ulang '.$d['label_jenis'].' dibuka di tab baru — isinya mengikuti data kegiatan terbaru.'), type: 'info' })"><x-ui.icon name="printer" class="size-4" /> Cetak ulang</a>
                        <x-dokumen.tombol-unduh-pdf :url="$buka['pdf']" :nama="$buka['nama_pdf']" label="PDF" testid="unduh-ulang-pdf" wire:key="ulang-pdf-{{ $d['id'] }}" />
                        @if ($buka['word'])
                            <x-dokumen.tombol-unduh-word :kegiatan-id="$d['kegiatan_id']" :sumber="$d['sumber']" :nama="$buka['nama_word']" label="Word" testid="unduh-ulang-word" wire:key="ulang-word-{{ $d['id'] }}" />
                        @endif
                        @if ($buka['excel'])
                            <x-dokumen.tombol-unduh-excel :kegiatan-id="$d['kegiatan_id']" :sumber="$d['sumber']" :nama="$buka['nama_excel']" label="Excel" testid="unduh-ulang-excel" wire:key="ulang-excel-{{ $d['id'] }}" />
                        @endif
                    </div>
                    </div>
                @endif
            </section>

            @if ($rincian['nomor']['boleh'])
                <section class="rounded-xl bg-brand-50/40 px-4 py-3 ring-1 ring-brand-100" aria-labelledby="judul-beri-nomor" data-testid="beri-nomor">
                    <h3 id="judul-beri-nomor" class="text-sm font-semibold text-stone-900">Nomor dokumen</h3>
                    <p class="mt-0.5 text-xs text-stone-500">
                        {{ $rincian['nomor']['usulan'] ? 'Diusulkan otomatis sesuai aturan penomoran; bisa diubah sebelum diberikan. Nomor hanya diberikan sekali.' : 'Jenis dokumen ini tidak dinomori otomatis — ketik nomornya. Nomor hanya diberikan sekali.' }}
                    </p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-start">
                        <label class="min-w-0 flex-1">
                            <span class="sr-only">Nomor dokumen</span>
                            <input type="text" wire:model="nomorDokumen" maxlength="{{ \App\Support\Dokumen\PerbaruiRiwayatDokumen::MAKS_NOMOR }}" autocomplete="off" x-on:keydown.enter.prevent="$wire.nomorDokumen = $el.value; $wire.beriNomor()"
                                   @class(['form-input w-full py-1.5 font-mono text-sm', 'ring-red-400' => $errors->has('nomorDokumen')]) data-testid="isian-nomor-dokumen" data-usulan="{{ $rincian['nomor']['usulan'] }}">
                            @error('nomorDokumen')<span class="mt-1 block text-xs font-medium text-red-700" role="alert" data-testid="galat-nomor-dokumen">{{ $message }}</span>@enderror
                        </label>
                        <button type="button" wire:click="beriNomor" wire:loading.attr="disabled" wire:target="beriNomor" class="btn-primary shrink-0 py-1.5" data-testid="beri-nomor-dokumen"><x-ui.icon name="tag" class="size-4" /> Beri nomor</button>
                    </div>
                </section>
            @endif

            <section aria-labelledby="judul-versi-dokumen">
                <h3 id="judul-versi-dokumen" class="text-sm font-semibold text-stone-900">Versi {{ $d['label_jenis'] }}{{ $d['sumber'] ? ' '.$d['sumber'] : '' }} · {{ $d['label_format'] }} <span class="font-normal text-stone-500">({{ count($rincian['versi']) }})</span></h3>
                <ol class="mt-2 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200" role="list" data-testid="versi-dokumen">
                    @foreach ($rincian['versi'] as $v)
                        <li class="flex items-center justify-between gap-3 px-3 py-2" data-versi="{{ $v['versi'] }}" data-id="{{ $v['id'] }}" @if ($v['id'] === $d['id']) aria-current="true" @endif>
                            <p class="min-w-0 text-xs text-stone-600">
                                <span class="font-semibold text-stone-900">v{{ $v['versi'] }}</span> · {{ $waktu($v['dibuat_pada']) }} · {{ $v['label_asal'] }} · {{ $v['label_status'] }}@if ($v['bertanda_draf']) <span class="font-bold text-red-700">DRAF</span>@endif
                            </p>
                            @if ($v['id'] === $d['id'])
                                <span class="shrink-0 text-[11px] font-semibold text-brand-700">Sedang dilihat</span>
                            @else
                                <button type="button" wire:click="bukaLihat('{{ $v['id'] }}')" class="shrink-0 text-xs font-semibold text-brand-700 hover:text-brand-800" data-testid="lihat-versi">Lihat</button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 px-5 py-3">
            <button type="button" wire:click="bukaDuplikat('{{ $d['id'] }}')" class="btn-ghost" data-testid="lihat-ke-duplikat"><x-ui.icon name="copy" class="size-4" /> Salin sebagai kegiatan baru</button>
            <button type="button" wire:click="tutupLihat" class="btn-secondary">Tutup</button>
        </footer>
    </div>
</div>
