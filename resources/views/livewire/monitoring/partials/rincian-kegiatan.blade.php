@use('App\Livewire\Monitoring\Anggaran')
@use('App\Support\Format')
@use('App\Support\Monitoring\RingkasanAnggaran')

{{--
    Rincian per kegiatan (PRD §11): satu baris per kegiatan — RAB tiap sumber dana dibandingkan dengan pagunya sendiri
    (pagu, total, pemakaian, kondisi baku StatusPagu) — bawaan diurutkan dari total RAB terbesar. Urutan lewat pilihan atau
    judul kolom, 20 kegiatan per halaman; baris jumlah memuat seluruh kegiatan tersaring. Layar sempit: kartu per kegiatan.
--}}
@php
    $semua = $this->rincian;
    $jumlahHalaman = $this->jumlahHalaman();
    $halaman = min($this->halaman, $jumlahHalaman);
    $baris = array_slice($semua, ($halaman - 1) * Anggaran::PER_HALAMAN, Anggaran::PER_HALAMAN);
    $mulai = $semua === [] ? 0 : ($halaman - 1) * Anggaran::PER_HALAMAN + 1;
    $total = $this->ringkasan->total();
    $teksPersen = fn (?float $p) => $p === null ? '—' : Format::angka($p).'%';
@endphp

<section id="monitoring-kegiatan" class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-monitoring-kegiatan" data-testid="bagian-monitoring-kegiatan">
    <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-monitoring-kegiatan" class="text-base font-semibold text-stone-900">Rincian per kegiatan</h2>
            <p class="mt-0.5 text-sm text-stone-500">Pagu, total RAB, dan kondisi setiap RAB terhadap pagunya sendiri.</p>
        </div>
        <label class="flex shrink-0 items-center gap-2 text-xs text-stone-500">
            Urutkan
            <select wire:model.live="urut" class="form-input w-auto py-1 text-xs" data-testid="urut-rincian">
                @foreach (RingkasanAnggaran::URUT_RINCIAN as $kunci => [$label])
                    <option value="{{ $kunci }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </header>

    @if ($semua === [])
        <div class="px-5 py-10 text-center sm:px-6" data-testid="rincian-kosong">
            <p class="text-sm font-medium text-stone-700">Tidak ada kegiatan yang cocok dengan saringan.</p>
            @if ($this->saringanAktif() !== [])
                <button type="button" wire:click="hapusSaringan" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus semua saringan</button>
            @endif
        </div>
    @else
        {{-- Layar lebar: tabel --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[60rem] text-sm" data-testid="tabel-rincian">
                <caption class="sr-only">Rincian anggaran per kegiatan, diurutkan menurut {{ mb_strtolower(RingkasanAnggaran::URUT_RINCIAN[$this->urut][0]) }}</caption>
                <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                    <tr>
                        <th scope="col" class="w-12 py-2 pr-2 pl-5 text-right sm:pl-6">No</th>
                        <x-monitoring.judul-urut kolom="nama" label="Kegiatan" :urut="$this->urut" :arah="$this->arah" />
                        <x-monitoring.judul-urut kolom="status" label="Status" :urut="$this->urut" :arah="$this->arah" />
                        <th scope="col" class="px-3 py-2 text-left whitespace-nowrap">Sumber dana</th>
                        <x-monitoring.judul-urut kolom="pagu" label="Pagu" rata="right" :urut="$this->urut" :arah="$this->arah" />
                        <x-monitoring.judul-urut kolom="total" label="Total RAB" rata="right" :urut="$this->urut" :arah="$this->arah" />
                        <x-monitoring.judul-urut kolom="pemakaian" label="Pemakaian" class="w-36" :urut="$this->urut" :arah="$this->arah" />
                        <x-monitoring.judul-urut kolom="kelebihan" label="Kondisi pagu" :urut="$this->urut" :arah="$this->arah" />
                    </tr>
                </thead>
                @foreach ($baris as $i => $k)
                    @php
                        $rab = $k['rab'] === [] ? [null] : $k['rab'];
                        $rentang = count($rab);
                    @endphp
                    <tbody class="border-t border-stone-100" data-kegiatan="{{ $k['id'] }}" wire:key="rincian-{{ $k['id'] }}">
                        @foreach ($rab as $j => $r)
                            <tr class="align-top">
                                @if ($j === 0)
                                    <td rowspan="{{ $rentang }}" class="py-3 pr-2 pl-5 text-right text-xs text-stone-400 tabular-nums sm:pl-6">{{ $mulai + $i }}</td>
                                    <th scope="row" rowspan="{{ $rentang }}" class="max-w-80 px-3 py-3 text-left font-normal">
                                        <a href="{{ route('kegiatan.edit', ['kegiatan' => $k['id']]) }}" wire:navigate class="font-medium text-stone-900 hover:text-brand-700">{{ $k['nama'] }}</a>
                                        <span class="mt-0.5 block text-xs text-stone-500">
                                            @if ($k['kode_rktm'])
                                                <span class="font-mono text-stone-400">{{ $k['kode_rktm'] }}</span> ·
                                            @endif
                                            {{ $k['leading'] ? $k['leading'].' '.($k['leading_nama'] ?? '') : 'Leading belum dipilih' }}
                                        </span>
                                        @if ($rentang > 1)
                                            <span class="mt-1 block text-xs text-stone-500 tabular-nums">Jumlah {{ $k['rab_jumlah'] }} RAB <span class="font-semibold text-stone-700">{{ Format::rupiah($k['total']) }}</span></span>
                                        @endif
                                    </th>
                                    <td rowspan="{{ $rentang }}" class="px-3 py-3"><x-ui.status-badge :status="$k['status']->value" /></td>
                                @endif
                                @if ($r === null)
                                    <td colspan="5" class="px-3 py-3 text-xs font-medium text-gold-700 sm:pr-6">Sumber dana belum dipilih</td>
                                @else
                                    @php
                                        $bahaya = $r['status']->melebihi();
                                    @endphp
                                    <td class="px-3 py-3" data-rab="{{ $r['sumber'] }}">
                                        <a href="{{ route('kegiatan.rab', ['kegiatan' => $k['id'], 'sumber' => $r['sumber']]) }}" wire:navigate class="inline-flex items-center gap-1.5 hover:opacity-80">
                                            <x-rab.lencana-sumber :sumber="$r['sumber']" awalan="RAB" class="text-[11px]" />
                                        </a>
                                        <span class="mt-0.5 block text-[11px] text-stone-400">{{ $r['item'] }} item</span>
                                    </td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums">
                                        @if ($r['pagu'] !== null)
                                            <span class="text-stone-700">{{ Format::rupiah($r['pagu']) }}</span>
                                        @else
                                            <span class="text-xs font-medium text-gold-700">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold whitespace-nowrap text-stone-900 tabular-nums">{{ Format::rupiah($r['total']) }}</td>
                                    <td class="px-3 py-3">
                                        @if ($r['persen'] !== null)
                                            <span class="flex items-center gap-2">
                                                <span class="block h-1.5 flex-1 overflow-hidden rounded-full {{ $bahaya ? 'bg-red-100' : 'bg-brand-100' }}" aria-hidden="true">
                                                    <span class="block h-full rounded-full {{ $bahaya ? 'bg-red-600' : 'bg-brand-600' }}" style="width: {{ min(100, $r['persen']) }}%"></span>
                                                </span>
                                                <span @class(['w-12 text-right text-xs tabular-nums', 'font-semibold text-red-700' => $bahaya, 'text-stone-600' => ! $bahaya])>{{ $teksPersen($r['persen']) }}</span>
                                            </span>
                                        @else
                                            <span class="text-xs text-stone-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs whitespace-nowrap sm:pr-6"><x-rab.status-pagu :status="$r['status']" /></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
                <tfoot class="border-t-2 border-stone-200 bg-stone-50" data-testid="jumlah-rincian">
                    <tr class="align-top">
                        <th scope="row" colspan="4" class="py-3 pr-3 pl-5 text-left font-semibold text-stone-800 sm:pl-6">
                            Jumlah {{ count($semua) }} kegiatan
                            <span class="block text-xs font-normal text-stone-500">{{ $total['rab'] }} RAB{{ $total['tanpa_pagu'] > 0 ? ' · '.$total['tanpa_pagu'].' pagu belum diisi' : '' }}</span>
                        </th>
                        <td class="px-3 py-3 text-right font-semibold whitespace-nowrap text-stone-800 tabular-nums">{{ Format::rupiah($total['pagu']) }}</td>
                        <td class="px-3 py-3 text-right font-bold whitespace-nowrap text-stone-900 tabular-nums">{{ Format::rupiah($total['total']) }}</td>
                        <td colspan="2" class="px-3 py-3 text-xs sm:pr-6">
                            @if ($total['melebihi'] > 0)
                                <span class="inline-flex items-center gap-1 font-semibold text-red-700"><x-ui.icon name="alert" class="size-3.5" /> {{ $total['melebihi'] }} RAB melebihi pagunya</span>
                            @else
                                <span class="text-stone-500">Tidak ada RAB yang melebihi pagunya.</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Layar sempit: kartu per kegiatan --}}
        <ol class="divide-y divide-stone-100 md:hidden" start="{{ $mulai }}">
            @foreach ($baris as $i => $k)
                <li class="px-4 py-3.5" data-kegiatan-kartu="{{ $k['id'] }}" wire:key="kartu-rincian-{{ $k['id'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('kegiatan.edit', ['kegiatan' => $k['id']]) }}" wire:navigate class="text-sm font-medium text-stone-900 hover:text-brand-700">
                                <span class="text-xs text-stone-400 tabular-nums">{{ $mulai + $i }}.</span> {{ $k['nama'] }}
                            </a>
                            <p class="mt-0.5 text-xs text-stone-500">
                                @if ($k['kode_rktm'])
                                    <span class="font-mono text-stone-400">{{ $k['kode_rktm'] }}</span> ·
                                @endif
                                {{ $k['leading'] ? $k['leading'].' '.($k['leading_nama'] ?? '') : 'Leading belum dipilih' }}
                            </p>
                        </div>
                        <x-ui.status-badge :status="$k['status']->value" class="shrink-0" />
                    </div>
                    @if ($k['rab'] === [])
                        <p class="mt-2 text-xs font-medium text-gold-700">Sumber dana belum dipilih</p>
                    @else
                        <div class="mt-2 space-y-1.5 text-xs">
                            @foreach ($k['rab'] as $r)
                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $k['id'], 'sumber' => $r['sumber']]) }}" wire:navigate class="block rounded-lg bg-stone-50 px-3 py-2 hover:bg-stone-100">
                                    <span class="flex items-center justify-between gap-2">
                                        <x-rab.lencana-sumber :sumber="$r['sumber']" awalan="RAB" />
                                        <x-rab.status-pagu :status="$r['status']" />
                                    </span>
                                    <span class="mt-1 block text-stone-500 tabular-nums">
                                        Pagu {{ $r['pagu'] !== null ? Format::rupiah($r['pagu']) : 'belum diisi' }} · RAB <span class="font-semibold text-stone-800">{{ Format::rupiah($r['total']) }}</span>
                                        @if ($r['persen'] !== null)
                                            · {{ $teksPersen($r['persen']) }}
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
            <li class="bg-stone-50 px-4 py-3.5 text-xs text-stone-600" data-testid="jumlah-rincian-kartu">
                <p class="text-sm font-semibold text-stone-800">Jumlah {{ count($semua) }} kegiatan</p>
                <p class="mt-0.5 tabular-nums">Pagu {{ Format::rupiah($total['pagu']) }} · RAB <span class="font-semibold text-stone-800">{{ Format::rupiah($total['total']) }}</span></p>
                @if ($total['melebihi'] > 0)
                    <p class="mt-0.5 flex items-center gap-1 font-semibold text-red-700"><x-ui.icon name="alert" class="size-3.5" /> {{ $total['melebihi'] }} RAB melebihi pagunya</p>
                @endif
            </li>
        </ol>

        {{-- Halaman --}}
        <footer class="flex flex-col gap-2 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <span data-testid="posisi-rincian">Menampilkan {{ $mulai }}–{{ $mulai + count($baris) - 1 }} dari {{ count($semua) }} kegiatan</span>
            @if ($jumlahHalaman > 1)
                <nav class="flex items-center gap-1" aria-label="Halaman rincian kegiatan">
                    <button type="button" wire:click="keHalaman({{ $halaman - 1 }})" @disabled($halaman <= 1) class="btn-ghost px-2 py-1 text-xs disabled:opacity-40">Sebelumnya</button>
                    @for ($n = 1; $n <= $jumlahHalaman; $n++)
                        @if ($n === 1 || $n === $jumlahHalaman || abs($n - $halaman) <= 1)
                            <button type="button" wire:click="keHalaman({{ $n }})" @if ($n === $halaman) aria-current="page" @endif
                                    @class(['min-w-8 rounded-md px-2 py-1 text-xs font-semibold tabular-nums', 'bg-brand-700 text-white' => $n === $halaman, 'text-stone-600 hover:bg-stone-100' => $n !== $halaman])>{{ $n }}</button>
                        @elseif (abs($n - $halaman) === 2)
                            <span class="px-1 text-stone-400" aria-hidden="true">…</span>
                        @endif
                    @endfor
                    <button type="button" wire:click="keHalaman({{ $halaman + 1 }})" @disabled($halaman >= $jumlahHalaman) class="btn-ghost px-2 py-1 text-xs disabled:opacity-40">Berikutnya</button>
                </nav>
            @endif
        </footer>
    @endif
</section>
