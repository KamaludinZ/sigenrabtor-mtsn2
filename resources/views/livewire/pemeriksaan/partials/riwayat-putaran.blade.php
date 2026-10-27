@use('App\Enums\StatusKegiatan')
@use('App\Support\Pemeriksaan\RiwayatPemeriksaan')

@php
    // Riwayat pemeriksaan per putaran (RiwayatPemeriksaan::putaran): tiap pengajuan membuka putaran, hasilnya keputusan terakhir.
    $gayaHasil = [
        'penyusunan' => 'bg-stone-100 text-stone-600 ring-stone-200',
        'berjalan' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'ditarik' => 'bg-stone-100 text-stone-600 ring-stone-200',
        'dikembalikan' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'disetujui' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'final' => 'bg-brand-800 text-white ring-brand-800',
        'dibuka' => 'bg-gold-50 text-gold-700 ring-gold-200',
    ];
    $gayaTitik = ['info' => 'bg-sky-500', 'baik' => 'bg-brand-600', 'peringatan' => 'bg-gold-500', 'netral' => 'bg-stone-300'];
    $jumlahPengajuan = count(array_filter($putaran, fn ($p) => $p['nomor'] > 0));
@endphp

<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 lg:col-start-2 lg:row-start-3" aria-labelledby="judul-riwayat" data-testid="riwayat-pemeriksaan">
    <div class="flex items-baseline justify-between gap-2">
        <h2 id="judul-riwayat" class="text-sm font-semibold text-stone-900">Riwayat pemeriksaan</h2>
        @if ($jumlahPengajuan > 0)
            <span class="text-xs text-stone-500" data-testid="jumlah-putaran">{{ $jumlahPengajuan }}× diajukan</span>
        @endif
    </div>

    @if ($putaran === [])
        <p class="mt-2 text-xs text-stone-500">Belum ada perubahan status.</p>
    @else
        <ol class="mt-3 space-y-4">
            @foreach ($putaran as $p)
                <li data-putaran="{{ $p['nomor'] }}" data-hasil="{{ $p['hasil'] }}">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="text-xs font-semibold text-stone-800">{{ $p['nomor'] === 0 ? 'Sebelum diajukan' : 'Pengajuan ke-'.$p['nomor'] }}</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $gayaHasil[$p['hasil']] }}">{{ RiwayatPemeriksaan::HASIL[$p['hasil']] }}</span>
                        @if ($teksLama = RiwayatPemeriksaan::teksLama($p))
                            <span class="text-[11px] text-stone-500" data-lama>{{ $teksLama }}</span>
                        @endif
                    </div>
                    <ol class="mt-2 space-y-2.5 border-l border-stone-200 pl-3">
                        @foreach ($p['peristiwa'] as $e)
                            <li class="relative text-xs" data-aksi="{{ $e['aksi'] }}">
                                <span @class(['absolute top-1 -left-[17px] size-2 rounded-full ring-2 ring-white', $gayaTitik[RiwayatPemeriksaan::AKSI[$e['aksi']][1] ?? ($e['jenis'] === 'arsip' ? 'baik' : 'netral')]])></span>
                                <span class="font-medium text-stone-800">
                                    {{ $e['jenis'] === 'arsip' ? 'Arsip final RAB '.$e['sumber'].' diunggah' : StatusKegiatan::labelRiwayat($e['aksi']) }}
                                </span>
                                <span class="block text-stone-500">{{ $e['oleh'] }} · {{ $waktu($e['pada']) }}</span>
                                @if ($e['catatan'])
                                    <span class="mt-1 block rounded-lg bg-stone-50 px-2 py-1 whitespace-pre-line text-stone-700 ring-1 ring-stone-100">{{ $e['catatan'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </li>
            @endforeach
        </ol>
    @endif
</section>
