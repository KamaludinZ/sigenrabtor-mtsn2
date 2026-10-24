@use('App\Support\Format')

@php
    $sumberRab = $form->sumberDana();
    $rabTersedia = Route::has('kegiatan.rab');
@endphp

{{-- Penutup form: simpan draf dan transisi ke penyusunan RAB (per sumber dana). --}}
<div class="overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="lanjut-rab">
    <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gold-100 text-gold-700 ring-1 ring-gold-200">
                <x-ui.icon name="coins" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wider text-stone-400 uppercase">Langkah berikutnya</p>
                <p class="text-base font-semibold text-stone-900">Susun Rencana Anggaran Biaya (RAB)</p>
                @if ($sumberRab === [])
                    <p class="mt-0.5 text-sm text-stone-500">Pilih sumber dana di bagian 6 — RAB disusun terpisah untuk tiap sumber dana.</p>
                @else
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($sumberRab as $sumber)
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-stone-100 px-2 py-1 text-xs font-medium text-stone-700">
                                RAB {{ $sumber['sumber'] }}
                                @if ($rekap = $this->rekapRab[$sumber['sumber']] ?? null)
                                    <span class="text-stone-500">· {{ Format::rupiah($rekap->total) }} dari pagu {{ $sumber['pagu'] ? Format::rupiah($sumber['pagu']) : '—' }}</span>
                                @else
                                    <span class="text-stone-500">· pagu {{ $sumber['pagu'] ? Format::rupiah($sumber['pagu']) : 'belum diisi' }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            @if ($this->dapatDiedit())
                <button type="submit" class="btn-secondary">
                    <x-ui.icon name="save" class="size-4" />
                    Simpan Draf
                </button>
            @endif
            <button type="button" wire:click="lanjutKeRab" wire:loading.attr="disabled" wire:target="lanjutKeRab" class="btn-primary">
                {{ $this->dapatDiedit() ? 'Simpan & lanjut ke RAB' : 'Lanjut ke RAB' }}
                <x-ui.icon name="chevron-right" class="size-4" />
            </button>
        </div>
    </div>

    <p class="border-t border-stone-100 bg-stone-50 px-5 py-2.5 text-xs text-stone-500 sm:px-6">
        @if ($rabTersedia)
            Data kegiatan disimpan dulu, lalu halaman RAB dibuka. Dokumen yang belum lengkap tetap bisa dicetak dengan tanda “DRAF”.
        @else
            Halaman RAB disiapkan pada tahap berikutnya; tombol ini sekarang menyimpan data kegiatan Anda.
        @endif
    </p>
</div>
