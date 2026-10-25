@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\Rab\StatusPagu')

@php
    // $varian: "samping" (kolom kanan desktop, ikut menempel) atau "bawah" (layar sempit, sebelum Langkah berikutnya).
    $awalan = 'ringkasan-pagu-'.$varian;
    $paguTerpilih = array_column($form->sumberDana(), 'pagu', 'sumber');
    // Total RAB tersimpan per sumber — termasuk sumber yang dilepas, agar langsung tampil bila dicentang lagi.
    $rabSumber = [];
    foreach (SumberDana::cases() as $jenis) {
        $rekap = $this->rekapRab[$jenis->value] ?? null;
        $lepas = $this->rabTerlepas[$jenis->value] ?? null;
        $rabSumber[$jenis->value] = match (true) {
            $rekap !== null => ['total' => $rekap->total, 'item' => $rekap->jumlahItem()],
            $lepas !== null => ['total' => $lepas->total(), 'item' => $lepas->jumlahItem()],
            default => ['total' => 0, 'item' => 0],
        };
    }
    $warnaNada = ['netral' => 'text-stone-500', 'aman' => 'text-brand-700', 'bahaya' => 'text-red-600'];
@endphp

{{--
    Ringkasan pagu di halaman Data Kegiatan: pagu tiap sumber terpilih (ikut ketikan), total RAB-nya, bilah pemakaian, dan
    status batas pagu — terlihat tanpa menggulir ke bagian Sumber dana. Dihitung di klien lewat Alpine "paguKegiatan".
--}}
{{-- wire:key ikut total RAB: bila RAB berubah (mis. dari tab lain), komponen Alpine dibuat ulang dengan angka baru. --}}
<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="{{ $awalan }}-judul" data-testid="{{ $awalan }}"
         wire:key="{{ $awalan }}-{{ md5(json_encode($rabSumber)) }}"
         x-data="paguKegiatan({ sumber: @js(array_map(fn ($j) => ['label' => $j->value, 'kunci' => $j->kunci()], SumberDana::cases())), rab: @js($rabSumber) })">
    <div class="flex items-baseline justify-between gap-2">
        <h2 id="{{ $awalan }}-judul" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Ringkasan pagu</h2>
        @if ($kegiatanId)
            <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'halaman' => 'pagu']) }}" wire:navigate class="text-xs font-semibold text-brand-700 hover:text-brand-800">Pagu &amp; RAB →</a>
        @endif
    </div>

    <p class="mt-2 text-xs text-stone-500" x-show="terpilih.length === 0" @style(['display: none' => $paguTerpilih !== []])>
        Belum ada sumber dana dipilih. <a href="#sumber-dana" class="font-semibold text-brand-700 hover:text-brand-800">Pilih sumber dana</a>
    </p>

    <ul class="mt-3 space-y-3">
        @foreach (SumberDana::cases() as $jenis)
            @php
                $label = $jenis->value;
                $kunci = $jenis->kunci();
                $dipilih = array_key_exists($label, $paguTerpilih);
                $pagu = $paguTerpilih[$label] ?? null;
                $rab = $rabSumber[$label];
                $statusAwal = $rab['item'] > 0 ? StatusPagu::hitung($rab['total'], $pagu ?: null) : null;
                $s = "{ label: '{$label}', kunci: '{$kunci}' }";
            @endphp
            <li x-show="dipilih('{{ $kunci }}')" @style(['display: none' => ! $dipilih]) data-testid="{{ $awalan }}-{{ $kunci }}">
                <div class="flex items-center justify-between gap-2">
                    <x-rab.lencana-sumber :sumber="$label" awalan="RAB" class="text-[10px]" />
                    <span class="text-sm font-semibold text-stone-900 tabular-nums" x-text="$rupiah(totalRab('{{ $label }}'))">{{ Format::rupiah($rab['total']) }}</span>
                </div>
                <p class="mt-0.5 flex items-baseline justify-between gap-2 text-[11px] text-stone-500">
                    <span>Pagu</span>
                    <span class="tabular-nums" x-text="pagu('{{ $kunci }}') ? $rupiah(pagu('{{ $kunci }}')) : 'belum diisi'">{{ $pagu ? Format::rupiah($pagu) : 'belum diisi' }}</span>
                </p>
                <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                    <span class="block h-full rounded-full" x-bind:class="status({{ $s }})?.nada === 'bahaya' ? 'bg-red-500' : 'bg-brand-600'"
                          x-bind:style="`width: ${lebar({{ $s }})}%`"></span>
                </span>
                <p @class(['mt-1 text-[11px] font-medium', $statusAwal ? $warnaNada[$statusAwal->nada()] : 'text-stone-400'])
                   x-bind:class="{ 'text-stone-500': status({{ $s }})?.nada === 'netral', 'text-brand-700': status({{ $s }})?.nada === 'aman', 'text-red-600': status({{ $s }})?.nada === 'bahaya', 'text-stone-400': ! status({{ $s }}) }"
                   x-text="! status({{ $s }}) ? 'RAB belum diisi' : (status({{ $s }}).status === 'tanpa_pagu' ? 'Belum bisa dibandingkan' : status({{ $s }}).teks)"
                   data-testid="{{ $awalan }}-status-{{ $kunci }}">{{ match ($statusAwal?->status) { null => 'RAB belum diisi', 'tanpa_pagu' => 'Belum bisa dibandingkan', default => $statusAwal->teks() } }}</p>
            </li>
        @endforeach
    </ul>

    <div class="mt-3 space-y-0.5 border-t border-stone-100 pt-2 text-[11px] text-stone-500"
         x-show="terpilih.length > 1" @style(['display: none' => count($paguTerpilih) < 2]) data-testid="{{ $awalan }}-total">
        <p class="flex items-baseline justify-between gap-2">
            <span>Total RAB</span>
            <span class="font-semibold text-stone-800 tabular-nums" x-text="$rupiah(totalRabTerpilih)">{{ Format::rupiah(array_sum(array_map(fn ($l) => $rabSumber[$l]['total'], array_keys($paguTerpilih)))) }}</span>
        </p>
        <p class="flex items-baseline justify-between gap-2">
            <span>Total pagu</span>
            <span class="font-semibold text-stone-800 tabular-nums" x-text="$rupiah(totalPagu)">{{ Format::rupiah($form->totalPagu()) }}</span>
        </p>
    </div>
</section>
