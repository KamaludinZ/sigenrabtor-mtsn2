@use('App\Support\Format')

{{--
    Halaman "Semua RAB" (kegiatan dua sumber dana): setiap RAB tampil utuh berdampingan, baca-saja, memakai komponen
    tabel RAB tunggal. Mengubah isi tetap lewat Rincian belanja per sumber dana.
--}}
<div class="mt-5 space-y-6" data-testid="semua-rab">
    @foreach ($this->anggaran as $anggaran)
        @continue(! $anggaran->dipilih)
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="semua-rab-{{ mb_strtolower($anggaran->sumber) }}">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-stone-900">RAB {{ $anggaran->sumber }}</h2>
                    <p class="text-sm text-stone-500">
                        {{ $anggaran->jumlahItem() }} item · Pagu {{ $anggaran->pagu() !== null ? Format::rupiah($anggaran->pagu()) : 'belum diisi' }}
                        @if ($anggaran->statusPagu() === 'melebihi')
                            · <span class="font-medium text-red-600">melebihi pagu {{ Format::rupiah(-$anggaran->hasil->selisih()) }}</span>
                        @endif
                    </p>
                </div>
                <button type="button" class="btn-secondary" wire:click="pilihSumber('{{ $anggaran->sumber }}')">
                    <x-ui.icon name="pencil" class="size-4" />
                    Ubah RAB {{ $anggaran->sumber }}
                </button>
            </header>
            <div class="p-3 sm:p-5">
                <x-rab.tabel-rincian :anggaran="$anggaran" :kelompok="$kelompok" />
            </div>
        </section>
    @endforeach
</div>
