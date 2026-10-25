@use('App\Support\Format')
@use('App\Support\Rab\StatusPagu')
@use('App\Support\Rab\TabelPagu')

@php
    $tabel = TabelPagu::dari($this->anggaran);
    $baris = $tabel->baris();
    $jumlah = $tabel->jumlah();
    $ganda = count($baris) > 1;
    $warnaNada = ['netral' => 'bg-stone-100 text-stone-700 ring-stone-200', 'aman' => 'bg-brand-50 text-brand-800 ring-brand-200', 'bahaya' => 'bg-red-50 text-red-700 ring-red-200'];
    $ikonNada = ['netral' => 'info', 'aman' => 'check-circle', 'bahaya' => 'alert'];
    $teksPersen = fn (?float $persen) => $persen === null ? '—' : Format::angka($persen).'%';
    $teksSelisih = fn (?int $selisih) => $selisih === null ? '—' : Format::rupiah($selisih);
    $urlPagu = route('kegiatan.edit', ['kegiatan' => $kegiatanId]).'#sumber-dana';
    $warnaStatus = ['netral' => 'text-stone-500', 'aman' => 'text-brand-700', 'bahaya' => 'text-red-600'];
    $keterangan = [
        ['status' => StatusPagu::hitung(1, 1), 'judul' => 'Sesuai pagu', 'arti' => 'Total RAB sama persis dengan pagu.'],
        ['status' => StatusPagu::hitung(1, 2), 'judul' => 'Sisa pagu', 'arti' => 'Total RAB di bawah pagu (berbeda); pastikan tidak ada belanja yang terlewat.'],
        ['status' => StatusPagu::hitung(2, 1), 'judul' => 'Melebihi pagu', 'arti' => 'Total RAB di atas pagu; kurangi volume/harga atau hapus item sebelum diajukan.'],
        ['status' => StatusPagu::hitung(1, null), 'judul' => 'Pagu belum diisi', 'arti' => 'Isi pagu sumber dana di Data Kegiatan agar RAB bisa dibandingkan.'],
    ];
@endphp

{{--
    Halaman "Pagu & RAB" (PRD F12 Batas pagu): pagu dan total RAB tiap sumber dana berdampingan dengan selisih, pemakaian
    pagu, dan kondisinya. Setiap RAB dibandingkan dengan pagunya sendiri; baris jumlah hanya ringkasan kegiatan.
--}}
<section class="mt-5 space-y-4" data-testid="tabel-pagu-rab">
    <div class="overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-stone-200">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-stone-900">Pagu dan RAB</h2>
                <p class="mt-0.5 text-sm text-stone-500">Setiap RAB dibandingkan dengan pagu sumber dananya sendiri.</p>
            </div>
            <p @class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1', $warnaNada[$tabel->nada()]])
               role="status" data-testid="ringkasan-pagu-rab" data-nada="{{ $tabel->nada() }}">
                <x-ui.icon :name="$ikonNada[$tabel->nada()]" class="size-3.5 shrink-0" />
                {{ $tabel->ringkasan() }}
            </p>
        </header>

        {{-- Layar lebar: tabel --}}
        <table class="hidden w-full text-sm md:table">
            <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                <tr>
                    <th scope="col" class="px-5 py-2.5 text-left sm:pl-6">Sumber dana</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Pagu</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Total RAB</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Selisih</th>
                    <th scope="col" class="w-40 px-3 py-2.5 text-left">Pemakaian pagu</th>
                    <th scope="col" class="px-3 py-2.5 text-left">Kondisi</th>
                    <th scope="col" class="px-5 py-2.5 sm:pr-6"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($baris as $b)
                    @php
                        $kunci = mb_strtolower($b['sumber']);
                        $nada = $b['status']->nada();
                    @endphp
                    <tr class="align-top" data-testid="baris-pagu-{{ $kunci }}" data-status="{{ $b['status']->status }}">
                        <th scope="row" class="px-5 py-3 text-left font-normal sm:pl-6">
                            <x-rab.lencana-sumber :sumber="$b['sumber']" awalan="RAB" class="text-xs" />
                            <span class="mt-1 block text-xs text-stone-500">{{ $b['jumlah_item'] }} item</span>
                        </th>
                        <td class="px-3 py-3 text-right tabular-nums">
                            @if ($b['pagu'] !== null)
                                <span class="text-stone-800">{{ Format::rupiah($b['pagu']) }}</span>
                            @else
                                <span class="font-medium text-gold-700">Belum diisi</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right font-bold text-stone-900 tabular-nums">{{ Format::rupiah($b['total']) }}</td>
                        <td @class(['px-3 py-3 text-right tabular-nums', 'font-semibold text-red-600' => $nada === 'bahaya', 'text-stone-700' => $nada !== 'bahaya'])>{{ $teksSelisih($b['selisih']) }}</td>
                        <td class="px-3 py-3">
                            @if ($b['persen'] !== null)
                                <span class="flex items-center gap-2">
                                    <span class="block h-1.5 flex-1 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                        <span @class(['block h-full rounded-full', 'bg-brand-600' => $nada !== 'bahaya', 'bg-red-500' => $nada === 'bahaya'])
                                              style="width: {{ min(100, $b['persen']) }}%"></span>
                                    </span>
                                    <span @class(['w-12 text-right text-xs tabular-nums', 'font-semibold text-red-600' => $nada === 'bahaya', 'text-stone-600' => $nada !== 'bahaya'])>{{ $teksPersen($b['persen']) }}</span>
                                </span>
                            @else
                                <span class="text-xs text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-xs"><x-rab.status-pagu :status="$b['status']" /></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap sm:pr-6">
                            @if ($b['pagu'] === null)
                                <a href="{{ $urlPagu }}" class="text-xs font-semibold text-gold-700 hover:text-gold-800">Isi pagu</a>
                                <span class="mx-1 text-stone-300" aria-hidden="true">·</span>
                            @endif
                            <button type="button" wire:click="pilihSumber('{{ $b['sumber'] }}')" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Buka rincian</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @if ($ganda)
                <tfoot class="border-t-2 border-stone-200 bg-stone-50/70" data-testid="jumlah-pagu-rab">
                    <tr class="align-top">
                        <th scope="row" class="px-5 py-3 text-left font-semibold text-stone-800 sm:pl-6">
                            Jumlah kegiatan
                            <span class="mt-0.5 block text-xs font-normal text-stone-500">{{ $jumlah['jumlah_item'] }} item</span>
                        </th>
                        <td class="px-3 py-3 text-right tabular-nums">
                            <span class="font-semibold text-stone-800">{{ Format::rupiah($jumlah['pagu']) }}</span>
                            @if ($jumlah['pagu_kosong'] > 0)
                                <span class="block text-xs text-gold-700">{{ $jumlah['pagu_kosong'] }} pagu belum diisi</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right font-bold text-stone-900 tabular-nums">{{ Format::rupiah($jumlah['total']) }}</td>
                        <td class="px-3 py-3 text-right text-stone-700 tabular-nums">{{ $teksSelisih($jumlah['selisih']) }}</td>
                        <td class="px-3 py-3 text-right text-xs text-stone-600 tabular-nums">{{ $teksPersen($jumlah['persen']) }}</td>
                        <td colspan="2" class="px-3 py-3 text-xs text-stone-500 sm:pr-6">Ringkasan saja — dokumen dicetak per sumber dana.</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        {{-- Layar sempit: kartu per sumber dana --}}
        <ul class="divide-y divide-stone-100 md:hidden">
            @foreach ($baris as $b)
                @php
                    $kunci = mb_strtolower($b['sumber']);
                    $nada = $b['status']->nada();
                @endphp
                <li class="px-4 py-4" data-testid="kartu-pagu-{{ $kunci }}" data-status="{{ $b['status']->status }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-rab.lencana-sumber :sumber="$b['sumber']" awalan="RAB" class="text-xs" />
                        <x-rab.status-pagu :status="$b['status']" class="text-xs" />
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Pagu</dt>
                            <dd @class(['tabular-nums', 'text-stone-800' => $b['pagu'] !== null, 'font-medium text-gold-700' => $b['pagu'] === null])>{{ $b['pagu'] !== null ? Format::rupiah($b['pagu']) : 'Belum diisi' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Total RAB · {{ $b['jumlah_item'] }} item</dt>
                            <dd class="font-bold text-stone-900 tabular-nums">{{ Format::rupiah($b['total']) }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Selisih</dt>
                            <dd @class(['tabular-nums', 'font-semibold text-red-600' => $nada === 'bahaya', 'text-stone-700' => $nada !== 'bahaya'])>{{ $teksSelisih($b['selisih']) }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Pemakaian pagu</dt>
                            <dd @class(['tabular-nums', 'font-semibold text-red-600' => $nada === 'bahaya', 'text-stone-700' => $nada !== 'bahaya'])>{{ $teksPersen($b['persen']) }}</dd>
                        </div>
                    </dl>
                    @if ($b['persen'] !== null)
                        <span class="mt-2.5 block h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                            <span @class(['block h-full rounded-full', 'bg-brand-600' => $nada !== 'bahaya', 'bg-red-500' => $nada === 'bahaya'])
                                  style="width: {{ min(100, $b['persen']) }}%"></span>
                        </span>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold">
                        <button type="button" wire:click="pilihSumber('{{ $b['sumber'] }}')" class="text-brand-700 hover:text-brand-800">Buka rincian</button>
                        @if ($b['pagu'] === null)
                            <a href="{{ $urlPagu }}" class="text-gold-700 hover:text-gold-800">Isi pagu</a>
                        @endif
                    </div>
                </li>
            @endforeach
            @if ($ganda)
                <li class="bg-stone-50/70 px-4 py-4" data-testid="jumlah-pagu-rab-kartu">
                    <p class="text-sm font-semibold text-stone-800">Jumlah kegiatan <span class="font-normal text-stone-500">· {{ $jumlah['jumlah_item'] }} item</span></p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Pagu</dt>
                            <dd class="font-semibold text-stone-800 tabular-nums">{{ Format::rupiah($jumlah['pagu']) }}</dd>
                            @if ($jumlah['pagu_kosong'] > 0)
                                <dd class="text-xs text-gold-700">{{ $jumlah['pagu_kosong'] }} pagu belum diisi</dd>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Total RAB</dt>
                            <dd class="font-bold text-stone-900 tabular-nums">{{ Format::rupiah($jumlah['total']) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-stone-500">Ringkasan saja — dokumen dicetak per sumber dana.</p>
                </li>
            @endif
        </ul>
    </div>

    {{-- Arti setiap kondisi --}}
    <dl class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4" data-testid="keterangan-kondisi-pagu">
        @foreach ($keterangan as $k)
            <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-stone-200">
                <dt @class(['flex items-center gap-1 text-xs font-medium', $warnaStatus[$k['status']->nada()]])>
                    <x-ui.icon :name="$k['status']->ikon()" class="size-3.5 shrink-0" />{{ $k['judul'] }}
                </dt>
                <dd class="mt-1 text-xs text-stone-500">{{ $k['arti'] }}</dd>
            </div>
        @endforeach
    </dl>
</section>
