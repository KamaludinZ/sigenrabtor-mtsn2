@props(['ringkasan', 'sumber' => null])

@use('App\Enums\SumberDana')
@use('App\Support\Format')
@use('App\Support\Monitoring\RingkasanAnggaran')
@use('App\Support\Rab\StatusPagu')

{{--
    Kartu perbandingan pagu DIPA vs Komite (Monitoring Anggaran) dari RingkasanAnggaran::perbandinganSumber(): porsi tiap
    sumber terhadap gabungan pagu & total RAB (batang 100% dua segmen, label langsung + legenda), lalu satu kartu per
    sumber dana — meter pemakaian pagu, total RAB tanpa pagu, dan jumlah RAB per kondisi. Warna identitas sumber dana
    (indigo DIPA, emas Komite) tidak dipakai untuk teks; status melebihi tetap merah + ikon + teks. $sumber = saringan
    sumber dana aktif (hanya kartu sumber itu yang tampil).
--}}
@php
    $data = $ringkasan->perbandinganSumber();
    $sumberTampil = $sumber === null ? array_keys($data) : [$sumber];
    $warnaSegmen = [SumberDana::Dipa->value => 'bg-indigo-500', SumberDana::Komite->value => 'bg-gold-600'];
    $persen = fn (?float $p) => $p === null ? '—' : Format::angka($p).'%';
    $kombinasi = $ringkasan->kombinasiSumber();
    $ikonKondisi = ['sesuai' => 'check-circle', 'sisa' => 'check-circle', 'melebihi' => 'alert', 'tanpa_pagu' => 'info', 'rab_kosong' => 'info'];
@endphp

<section {{ $attributes->class(['rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 sm:p-6']) }} aria-labelledby="judul-perbandingan-sumber" data-testid="perbandingan-sumber">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div class="min-w-0">
            <h2 id="judul-perbandingan-sumber" class="text-base font-semibold text-stone-900">Perbandingan pagu DIPA &amp; Komite</h2>
            <p class="mt-0.5 text-sm text-stone-500">Pagu dari Cascade Perkin dibandingkan dengan total RAB tiap sumber dana.</p>
        </div>
        @if ($sumber === null)
            {{-- Legenda: identitas sumber dana tidak hanya lewat warna (label langsung di bawah tiap batang). --}}
            <ul class="flex shrink-0 gap-3 text-xs text-stone-600" aria-label="Legenda sumber dana">
                @foreach ($sumberTampil as $s)
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ $warnaSegmen[$s] ?? 'bg-stone-400' }}" aria-hidden="true"></span>{{ $s }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($sumber === null)
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            @foreach (['pagu' => 'Porsi pagu', 'total' => 'Porsi total RAB'] as $nilai => $judul)
                @php
                    $gabungan = array_sum(array_column($data, $nilai));
                    $deskripsi = implode(', ', array_map(fn ($s) => $s.' '.$persen($data[$s]['porsi_'.$nilai]).' ('.Format::rupiah($data[$s][$nilai]).')', $sumberTampil));
                @endphp
                <div data-testid="porsi-{{ $nilai }}">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-xs font-semibold text-stone-500">{{ $judul }}</h3>
                        <p class="text-xs text-stone-500 tabular-nums">Gabungan {{ Format::rupiah($gabungan) }}</p>
                    </div>
                    @if ($gabungan > 0)
                        <div class="mt-2 flex h-3 gap-0.5" role="img" aria-label="{{ $judul }}: {{ $deskripsi }}">
                            @foreach ($sumberTampil as $s)
                                @if ($data[$s][$nilai] > 0)
                                    <span class="h-full rounded {{ $warnaSegmen[$s] ?? 'bg-stone-400' }}" style="width: {{ $data[$s]['porsi_'.$nilai] }}%"
                                          title="{{ $s }}: {{ Format::rupiah($data[$s][$nilai]) }} ({{ $persen($data[$s]['porsi_'.$nilai]) }})" data-porsi="{{ $s }}"></span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="mt-2 h-3 rounded bg-stone-100" aria-hidden="true"></div>
                    @endif
                    <dl class="mt-2 flex justify-between gap-3 text-xs">
                        @foreach ($sumberTampil as $s)
                            <div @class(['min-w-0', 'text-right' => ! $loop->first])>
                                <dt class="font-medium text-stone-600">{{ $s }} <span class="font-semibold text-stone-900 tabular-nums">{{ $persen($data[$s]['porsi_'.$nilai]) }}</span></dt>
                                <dd class="text-stone-500 tabular-nums">{{ Format::rupiah($data[$s][$nilai]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-stone-500" data-testid="kombinasi-sumber">
            Kegiatan: hanya DIPA <span class="font-semibold text-stone-700 tabular-nums">{{ $kombinasi[SumberDana::Dipa->value] }}</span>
            · hanya Komite <span class="font-semibold text-stone-700 tabular-nums">{{ $kombinasi[SumberDana::Komite->value] }}</span>
            · DIPA &amp; Komite <span class="font-semibold text-stone-700 tabular-nums">{{ $kombinasi['keduanya'] }}</span>
            @if ($kombinasi['belum'] > 0)
                · <span class="text-gold-700">belum memilih sumber dana {{ $kombinasi['belum'] }}</span>
            @endif
        </p>
    @else
        <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600" data-testid="perbandingan-disaring">
            <x-ui.icon name="info" class="size-3.5 shrink-0 text-stone-400" />
            Saringan sumber dana aktif — hanya {{ $sumber }} yang ditampilkan.
            <button type="button" wire:click="hapusSaringan('sumber')" class="font-semibold text-brand-700 hover:text-brand-800">Bandingkan DIPA &amp; Komite</button>
        </p>
    @endif

    <div @class(['mt-5 grid gap-4', 'md:grid-cols-2' => count($sumberTampil) > 1])>
        @foreach ($sumberTampil as $s)
            @php
                $d = $data[$s];
                $status = StatusPagu::hitung($d['total'], $d['pagu'] > 0 ? $d['pagu'] : null);
                $jenis = SumberDana::tryFrom($s);
            @endphp
            <article class="@container flex flex-col rounded-xl p-4 ring-1 ring-stone-200" data-testid="perbandingan-{{ mb_strtolower($s) }}" data-status="{{ $status->status }}" wire:key="perbandingan-{{ $s }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-stone-900">
                            <span class="size-2.5 shrink-0 rounded-sm {{ $warnaSegmen[$s] ?? 'bg-stone-400' }}" aria-hidden="true"></span>
                            {{ $s }}
                        </h3>
                        @if ($jenis)
                            <p class="mt-0.5 text-xs text-stone-500">{{ $jenis->keterangan() }}</p>
                        @endif
                    </div>
                    <p class="shrink-0 text-xs text-stone-500 tabular-nums">{{ $d['rab'] }} RAB</p>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 @sm:grid-cols-3">
                    <div class="min-w-0">
                        <dt class="text-xs text-stone-500">Pagu</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-stone-900 tabular-nums sm:text-base">{{ $d['pagu'] > 0 ? Format::rupiah($d['pagu']) : '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-stone-500">Total RAB</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-stone-900 tabular-nums sm:text-base">{{ Format::rupiah($d['total']) }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-stone-500">Pemakaian</dt>
                        <dd @class(['mt-0.5 text-sm font-semibold tabular-nums sm:text-base', 'text-red-700' => $status->melebihi(), 'text-stone-900' => ! $status->melebihi()])>{{ $persen($d['persen']) }}</dd>
                    </div>
                </dl>

                <x-monitoring.meter-pagu :sumber="$s" :pagu="$d['pagu']" :total="$d['total']" :kepala="false" class="mt-3" />

                @if ($d['total_tanpa_pagu'] > 0)
                    <p class="mt-2 text-xs text-gold-700" data-testid="tanpa-pagu-{{ mb_strtolower($s) }}">
                        Total RAB termasuk {{ Format::rupiah($d['total_tanpa_pagu']) }} dari {{ $d['tanpa_pagu'] }} RAB yang pagunya belum diisi.
                    </p>
                @endif

                <h4 class="mt-4 text-xs font-semibold text-stone-500">Kondisi tiap RAB terhadap pagunya</h4>
                <ul class="mt-1.5 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2" aria-label="Kondisi RAB {{ $s }}">
                    @foreach (RingkasanAnggaran::KONDISI_RAB as $kunci => $label)
                        @php
                            $jumlah = $d['kondisi'][$kunci];
                        @endphp
                        <li @class(['flex items-center justify-between gap-2', 'text-stone-300' => $jumlah === 0, 'text-stone-600' => $jumlah > 0 && $kunci !== 'melebihi', 'font-medium text-red-700' => $jumlah > 0 && $kunci === 'melebihi'])
                            data-kondisi="{{ $kunci }}">
                            <span class="flex min-w-0 items-center gap-1"><x-ui.icon :name="$ikonKondisi[$kunci]" class="size-3.5 shrink-0" /> {{ $label }}
                                @if ($kunci === 'melebihi' && $d['kelebihan'] > 0)
                                    <span class="truncate font-normal">(lebih {{ Format::rupiah($d['kelebihan']) }})</span>
                                @endif
                            </span>
                            <span class="font-semibold tabular-nums">{{ $jumlah }}</span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('kegiatan.index', ['sumber' => mb_strtolower($s)]) }}" wire:navigate class="mt-4 inline-flex items-center gap-1 self-start text-xs font-semibold text-brand-700 hover:text-brand-800">
                    Lihat kegiatan {{ $s }} <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </article>
        @endforeach
    </div>
</section>
