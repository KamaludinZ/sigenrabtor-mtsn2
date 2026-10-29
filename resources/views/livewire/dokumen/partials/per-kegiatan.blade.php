{{--
    Riwayat dokumen per kegiatan (RiwayatDokumen::perKegiatan) dalam satu tahun anggaran: kartu kegiatan (nama, leading,
    tautan pratinjau selama kegiatannya tersimpan) berisi satu baris per jenis × sumber × format dengan versi terbaru dan
    versi sebelumnya yang bisa dibuka; kata kunci `$cari` disorot (RiwayatDokumen::sorot). Variabel: $kegiatan, $waktu,
    $nadaStatus, $cari.
--}}
<ul class="divide-y divide-stone-100" role="list">
    @foreach ($kegiatan as $k)
        <li class="px-4 py-4 sm:px-5" data-testid="kegiatan-riwayat" data-kunci="{{ $k['kunci'] }}" wire:key="kg-{{ $k['kunci'] }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="font-semibold text-stone-900">{!! \App\Support\Dokumen\RiwayatDokumen::sorot($k['nama_kegiatan'], $cari) !!}</h3>
                    <p class="mt-0.5 text-xs text-stone-500">
                        @if ($k['kode_leading'] !== '')
                            Leading {{ $k['kode_leading'] }}@if ($k['nama_leading']) · {{ $k['nama_leading'] }}@endif ·
                        @endif
                        {{ $k['jumlah'] }} dokumen
                    </p>
                </div>
                @if ($k['tersedia'])
                    <a href="{{ route('kegiatan.edit', ['kegiatan' => $k['kegiatan_id']]) }}" wire:navigate class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800" data-testid="buka-kegiatan-riwayat">Buka kegiatan</a>
                @else
                    <span class="shrink-0 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-500" title="Data kegiatan tahun itu tidak lagi tersimpan; dokumennya tetap tercatat." data-testid="kegiatan-tidak-tersimpan">Kegiatan tidak tersimpan</span>
                @endif
            </div>

            <ul class="mt-3 space-y-2" role="list">
                @foreach ($k['dokumen'] as $d)
                    @php
                        $t = $d['terbaru'];
                    @endphp
                    <li class="rounded-xl bg-stone-50 px-3 py-2.5 ring-1 ring-stone-200/70" data-testid="dokumen-kegiatan" data-versi="{{ $t['versi'] }}" data-id="{{ $t['id'] }}" wire:key="dk-{{ $t['id'] }}">
                        <div class="flex items-start gap-2.5">
                            <span @class(['mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg', 'bg-red-50 text-red-700' => $t['format'] === 'pdf', 'bg-sky-50 text-sky-700' => $t['format'] === 'docx', 'bg-brand-50 text-brand-700' => $t['format'] === 'xlsx'])>
                                <x-ui.icon :name="$t['ikon_format']" class="size-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
                                    <span class="font-semibold text-stone-900">{{ $t['label_jenis'] }}</span>
                                    @if ($t['sumber'])
                                        <x-rab.lencana-sumber :sumber="$t['sumber']" />
                                    @endif
                                    <span class="text-stone-500">{{ $t['label_format'] }} · v{{ $t['versi'] }}</span>
                                    <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', $nadaStatus[$t['status']] ?? 'bg-stone-100 text-stone-700 ring-stone-300'])>{{ $t['label_status'] }}</span>
                                    @if ($t['bertanda_draf'])
                                        <span class="text-[11px] font-bold tracking-wider text-red-700">DRAF</span>
                                    @endif
                                    @if ($t['nomor_dokumen'])
                                        <span class="font-mono text-[11px] text-stone-600">{!! \App\Support\Dokumen\RiwayatDokumen::sorot($t['nomor_dokumen'], $cari) !!}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 truncate font-mono text-[11px] text-stone-500" title="{{ $t['nama_berkas'] }}">{!! \App\Support\Dokumen\RiwayatDokumen::sorot($t['nama_berkas'], $cari) !!}</p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $waktu($t['dibuat_pada']) }} · {{ $t['label_asal'] }} oleh {{ $t['dibuat_oleh'] }}@if ($t['ukuran_teks']) · {{ $t['ukuran_teks'] }}@endif</p>

                                @if ($d['lama'] !== [])
                                    <details class="group mt-1.5" data-testid="versi-lama">
                                        <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                            <x-ui.icon name="chevron-right" class="size-3 transition group-open:rotate-90" /> {{ count($d['lama']) }} versi sebelumnya
                                        </summary>
                                        <ol class="mt-1.5 space-y-1 border-l-2 border-stone-200 pl-3" role="list">
                                            @foreach ($d['lama'] as $lama)
                                                <li class="text-xs text-stone-600" data-testid="versi-sebelumnya" data-versi="{{ $lama['versi'] }}" data-id="{{ $lama['id'] }}">
                                                    <span class="font-semibold text-stone-800">v{{ $lama['versi'] }}</span> · {{ $waktu($lama['dibuat_pada']) }} · {{ $lama['label_asal'] }} · {{ $lama['label_status'] }}@if ($lama['bertanda_draf']) <span class="font-bold text-red-700">DRAF</span>@endif
                                                    <span class="block truncate font-mono text-[11px] text-stone-400">{{ $lama['nama_berkas'] }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </li>
    @endforeach
</ul>
