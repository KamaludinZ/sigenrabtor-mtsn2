{{-- Dialog konfirmasi duplikat: rencana tiap kegiatan terpilih (DuplikatKegiatan::rencana) sebelum disalin. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="judul-dialog-duplikat"
     data-testid="dialog-duplikat" x-data x-on:keydown.escape.window="$wire.batalDuplikat()" x-init="$nextTick(() => $refs.konfirmasi?.focus())">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-stone-200">
        <header class="border-b border-stone-100 px-5 py-4">
            <h2 id="judul-dialog-duplikat" class="text-base font-semibold text-stone-900">Duplikat {{ count($rencana) }} kegiatan ke TA {{ $tahunAktif }}?</h2>
            <p class="mt-0.5 text-sm text-stone-500">Setiap kegiatan disalin menjadi draf baru beserta RAB, termin, matrik, dan jadwalnya. Kegiatan aslinya tidak berubah.</p>
        </header>
        <ul class="flex-1 divide-y divide-stone-100 overflow-y-auto px-5 text-sm" data-testid="rencana-duplikat">
            @foreach ($rencana as $r)
                <li class="py-2.5" data-rencana="{{ $r['id'] }}">
                    <p class="font-medium text-stone-900">{{ $r['nama'] }}</p>
                    @forelse ($r['catatan'] as $c)
                        <p @class(['mt-0.5 flex items-start gap-1.5 text-xs', 'text-gold-700' => $c['jenis'] !== 'pagu', 'text-stone-500' => $c['jenis'] === 'pagu']) data-catatan="{{ $c['jenis'] }}">
                            <x-ui.icon :name="$c['jenis'] === 'pagu' ? 'check-circle' : 'alert'" class="mt-px size-3.5 shrink-0" /> {{ $c['teks'] }}
                        </p>
                    @empty
                        <p class="mt-0.5 text-xs text-stone-500">Semua isian bisa disalin apa adanya.</p>
                    @endforelse
                </li>
            @endforeach
        </ul>
        <footer class="flex justify-end gap-2 border-t border-stone-100 px-5 py-3">
            <button type="button" wire:click="batalDuplikat" class="btn-ghost">Batal</button>
            <button type="button" wire:click="duplikat" wire:loading.attr="disabled" wire:target="duplikat" class="btn-primary" x-ref="konfirmasi" data-testid="konfirmasi-duplikat">
                <x-ui.icon name="copy" class="size-4" /> Ya, duplikat
            </button>
        </footer>
    </div>
</div>
