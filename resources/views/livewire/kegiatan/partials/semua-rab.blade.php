@use('App\Support\Format')
@use('App\Support\Rab\MekanismePembayaran')

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
                    <h2 class="text-base font-semibold text-stone-900">
                        <x-rab.lencana-sumber :sumber="$anggaran->sumber" awalan="RAB" class="text-sm" />
                    </h2>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-stone-500">
                        <span>{{ $anggaran->jumlahItem() }} item · Pagu {{ $anggaran->pagu() !== null ? Format::rupiah($anggaran->pagu()) : 'belum diisi' }}</span>
                        <x-rab.status-pagu :status="$anggaran->keteranganPagu()" class="text-xs" data-testid="status-pagu-semua-{{ mb_strtolower($anggaran->sumber) }}" />
                    </p>
                </div>
                <button type="button" class="btn-secondary" wire:click="pilihSumber('{{ $anggaran->sumber }}')">
                    <x-ui.icon name="pencil" class="size-4" />
                    Ubah RAB {{ $anggaran->sumber }}
                </button>
            </header>
            <div class="p-3 sm:p-5">
                <x-rab.peringatan-pagu :sumber="$anggaran->sumber" :hasil="$anggaran->hasil" ringkas class="mb-4"
                                       :tautan-pagu="route('kegiatan.edit', ['kegiatan' => $kegiatanId]).'#sumber-dana'" />
                <x-rab.tabel-rincian :anggaran="$anggaran" :kelompok="$kelompok" />
                @if ($anggaran->adaTermin())
                    <h3 class="mt-5 mb-2 text-sm font-semibold text-stone-800">Termin pembayaran</h3>
                    <x-rab.tabel-termin :mekanisme="MekanismePembayaran::dari($anggaran)" :sumber="$anggaran->sumber" />
                @endif
            </div>
        </section>
    @endforeach
</div>
