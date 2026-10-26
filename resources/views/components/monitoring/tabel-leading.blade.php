@props(['ringkasan', 'sumber' => null, 'leading' => null, 'urut' => 'kode', 'arah' => null])

@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\Monitoring\RingkasanAnggaran')

{{--
    Tabel "Total per leading" Monitoring Anggaran dari RingkasanAnggaran::perLeading(): jumlah kegiatan, lalu pagu, total RAB,
    dan selisih DIPA dan Komite terpisah (plus kelompok Jumlah bila keduanya tampil) dengan baris jumlah dari perSumber().
    Tanpa saringan leading semua leading master tampil (yang belum punya kegiatan ikut, bertanda —). Layar sempit: kartu
    per leading. $sumber / $leading = saringan aktif halaman; $urut / $arah = urutan (RingkasanAnggaran::urutLeading(), lewat
    judul kolom → urutkanLeading() atau pilihan Urutkan → urutLeading di komponen Monitoring).
--}}
@php
    $sumberTampil = $sumber === null ? SumberDana::urutan() : [$sumber];
    $ganda = count($sumberTampil) > 1;
    $baris = array_values(array_filter($ringkasan->perLeading(urut: $urut, arah: $arah), fn (array $b) => $leading === null || $b['kode'] === $leading));
    $pilihanUrut = array_filter(RingkasanAnggaran::urutLeading(), fn (string $k) => in_array(explode('_', $k)[0], ['kode', 'jumlah', ...array_map('mb_strtolower', $sumberTampil), ...($ganda ? ['semua'] : [])], true), ARRAY_FILTER_USE_KEY);
    $arahAktif = $arah ?? (RingkasanAnggaran::urutLeading()[$urut][1] ?? 'asc');
    $perSumber = $ringkasan->perSumber();
    $jumlahSumber = array_map(fn (array $s) => RingkasanAnggaran::sel($s), $perSumber);
    $jumlahSemua = RingkasanAnggaran::sel($ringkasan->total());
    $kelompok = array_map(fn (string $s) => ['label' => $s, 'kunci' => mb_strtolower($s), 'jumlah' => fn (array $b) => $b['sumber'][$s]], $sumberTampil);
    if ($ganda) {
        $kelompok[] = ['label' => null, 'kunci' => 'semua', 'jumlah' => fn (array $b) => $b];
    }
    $berkegiatan = count(array_filter($baris, fn (array $b) => $b['jumlah'] > 0));
    $teksSelisih = function (array $sel): string {
        return match (true) {
            $sel['rab'] === 0 => 'Belum ada RAB',
            $sel['selisih'] === null => 'Selisih belum bisa dihitung',
            $sel['selisih'] < 0 => 'Melebihi '.Format::rupiah(-$sel['selisih']),
            $sel['selisih'] === 0 => 'Tepat pagu',
            default => 'Sisa '.Format::rupiah($sel['selisih']),
        };
    };
@endphp

<section {{ $attributes->class(['rounded-2xl bg-white shadow-xs ring-1 ring-stone-200']) }} id="monitoring-leading" aria-labelledby="judul-monitoring-leading" data-testid="bagian-monitoring-leading">
    <header class="flex flex-col gap-1 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:gap-4 sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-monitoring-leading" class="text-base font-semibold text-stone-900">Total per leading</h2>
            <p class="mt-0.5 text-sm text-stone-500">Pagu, total RAB, dan selisih tiap leading — {{ $ganda ? 'DIPA dan Komite terpisah' : 'sumber dana '.$sumber }}. Selisih dihitung bila semua RAB sudah berpagu.</p>
        </div>
        <div class="flex shrink-0 flex-col gap-2 sm:items-end">
            <p class="text-xs text-stone-500" data-testid="jumlah-leading">{{ $berkegiatan }} dari {{ count($baris) }} leading punya kegiatan</p>
            @if (count($baris) > 1)
                <label class="flex items-center gap-2 text-xs text-stone-500">
                    Urutkan
                    <select wire:model.live="urutLeading" class="form-input w-auto py-1 text-xs" data-testid="urut-leading">
                        @foreach ($pilihanUrut as $kunci => [$label])
                            <option value="{{ $kunci }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
    </header>

    {{-- Layar lebar: tabel --}}
    <div class="hidden overflow-x-auto md:block">
        <table @class(['w-full text-sm', 'min-w-[64rem]' => $ganda, 'min-w-[40rem]' => ! $ganda])>
            <caption class="sr-only">Total anggaran per leading{{ $ganda ? ', DIPA dan Komite terpisah' : ', sumber dana '.$sumber }}</caption>
            <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
                <tr>
                    <x-monitoring.judul-urut kolom="kode" label="Leading" aksi="urutkanLeading" :urut="$urut" :arah="$arahAktif" rowspan="2"
                                             class="sticky left-0 z-10 min-w-52 border-r border-stone-100 bg-stone-50 pl-5 align-bottom sm:pl-6" />
                    <x-monitoring.judul-urut kolom="jumlah" label="Kegiatan" aksi="urutkanLeading" rata="right" :urut="$urut" :arah="$arahAktif" rowspan="2" class="align-bottom" />
                    @foreach ($kelompok as $k)
                        <th scope="colgroup" colspan="3" class="border-l border-stone-200 px-3 pt-2 pb-1 text-center">
                            @if ($k['label'])
                                <x-rab.lencana-sumber :sumber="$k['label']" />
                            @else
                                <span class="text-stone-700">Jumlah</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($kelompok as $k)
                        <x-monitoring.judul-urut :kolom="$k['kunci'].'_pagu'" label="Pagu" aksi="urutkanLeading" rata="right" :urut="$urut" :arah="$arahAktif" class="border-l border-stone-100 pt-1 font-medium" />
                        <x-monitoring.judul-urut :kolom="$k['kunci'].'_total'" label="Total RAB" aksi="urutkanLeading" rata="right" :urut="$urut" :arah="$arahAktif" class="pt-1 font-medium" />
                        <x-monitoring.judul-urut :kolom="$k['kunci'].'_selisih'" label="Selisih" aksi="urutkanLeading" rata="right" :urut="$urut" :arah="$arahAktif" class="pt-1 font-medium" />
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($baris as $b)
                    <tr class="align-top" data-leading="{{ $b['kode'] ?? 'tanpa' }}" wire:key="total-leading-{{ $b['kode'] ?? 'tanpa' }}">
                        <th scope="row" class="sticky left-0 z-10 border-r border-stone-100 bg-white px-5 py-2.5 text-left font-normal sm:pl-6">
                            @if ($b['kode'])
                                <a href="{{ route('kegiatan.index', ['leading' => $b['kode']]) }}" wire:navigate class="hover:text-brand-700">
                                    <span class="font-mono text-xs text-stone-400">{{ $b['kode'] }}</span>
                                    <span class="font-medium text-stone-800">{{ $b['nama_unit'] }}</span>
                                </a>
                            @else
                                <span class="text-stone-500 italic">{{ $b['nama_unit'] }}</span>
                            @endif
                        </th>
                        <td @class(['px-3 py-2.5 text-right tabular-nums', 'font-semibold text-stone-800' => $b['jumlah'] > 0, 'text-stone-300' => $b['jumlah'] === 0])>{{ $b['jumlah'] }}</td>
                        @foreach ($kelompok as $k)
                            <x-monitoring.sel-anggaran :sel="$k['jumlah']($b)" :tebal="$k['label'] === null" />
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (count($baris) > 1)
                <tfoot class="border-t-2 border-stone-200 bg-stone-50" data-testid="jumlah-per-leading">
                    <tr class="align-top">
                        <th scope="row" class="sticky left-0 z-10 border-r border-stone-100 bg-stone-50 px-5 py-2.5 text-left font-semibold text-stone-800 sm:pl-6">Jumlah</th>
                        <td class="px-3 py-2.5 text-right font-semibold text-stone-800 tabular-nums">{{ $ringkasan->jumlahKegiatan() }}</td>
                        @foreach ($sumberTampil as $s)
                            <x-monitoring.sel-anggaran :sel="$jumlahSumber[$s]" tebal />
                        @endforeach
                        @if ($ganda)
                            <x-monitoring.sel-anggaran :sel="$jumlahSemua" tebal />
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- Layar sempit: kartu per leading --}}
    <ul class="divide-y divide-stone-100 md:hidden">
        @foreach ($baris as $b)
            <li class="px-4 py-3.5" data-leading-kartu="{{ $b['kode'] ?? 'tanpa' }}" wire:key="kartu-leading-{{ $b['kode'] ?? 'tanpa' }}">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="min-w-0 text-sm">
                        @if ($b['kode'])
                            <span class="font-mono text-xs text-stone-400">{{ $b['kode'] }}</span>
                        @endif
                        <span @class(['font-medium text-stone-800' => $b['kode'], 'text-stone-500 italic' => ! $b['kode']])>{{ $b['nama_unit'] }}</span>
                    </p>
                    <span @class(['shrink-0 text-xs tabular-nums', 'font-semibold text-stone-700' => $b['jumlah'] > 0, 'text-stone-400' => $b['jumlah'] === 0])>{{ $b['jumlah'] }} kegiatan</span>
                </div>
                @if ($b['rab'] > 0)
                    <dl class="mt-2 space-y-1.5 text-xs">
                        @foreach ($sumberTampil as $s)
                            @php
                                $sel = $b['sumber'][$s];
                            @endphp
                            @if ($sel['rab'] > 0)
                                <div class="rounded-lg bg-stone-50 px-3 py-2">
                                    <dt class="flex items-center justify-between gap-2">
                                        <x-rab.lencana-sumber :sumber="$s" />
                                        <span @class(['font-semibold tabular-nums', 'text-red-700' => ($sel['selisih'] ?? 0) < 0, 'text-stone-700' => ($sel['selisih'] ?? 0) >= 0])>{{ $teksSelisih($sel) }}</span>
                                    </dt>
                                    <dd class="mt-1 text-stone-500 tabular-nums">
                                        Pagu {{ Format::rupiah($sel['pagu']) }} · RAB <span class="font-semibold text-stone-800">{{ Format::rupiah($sel['total']) }}</span>
                                        @if ($sel['tanpa_pagu'] > 0)
                                            <span class="block font-medium text-gold-700">{{ $sel['tanpa_pagu'] }} RAB belum berpagu</span>
                                        @endif
                                        @if ($sel['melebihi'] > 0)
                                            <span class="block font-medium text-red-700">{{ $sel['melebihi'] }} RAB melebihi pagunya</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @else
                    <p class="mt-1 text-xs text-stone-400">{{ $b['jumlah'] > 0 ? 'Belum ada RAB.' : 'Belum ada kegiatan.' }}</p>
                @endif
            </li>
        @endforeach
        @if (count($baris) > 1)
            <li class="bg-stone-50/70 px-4 py-3.5" data-testid="jumlah-per-leading-kartu">
                <p class="text-sm font-semibold text-stone-800">Jumlah · {{ $ringkasan->jumlahKegiatan() }} kegiatan</p>
                <dl class="mt-2 space-y-1 text-xs">
                    @foreach ($sumberTampil as $s)
                        <div class="flex items-center justify-between gap-2">
                            <dt><x-rab.lencana-sumber :sumber="$s" /></dt>
                            <dd class="text-right text-stone-600 tabular-nums">RAB <span class="font-semibold text-stone-800">{{ Format::rupiah($jumlahSumber[$s]['total']) }}</span> · {{ $teksSelisih($jumlahSumber[$s]) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </li>
        @endif
    </ul>
</section>
