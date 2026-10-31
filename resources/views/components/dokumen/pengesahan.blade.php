@props(['kolom'])

@use('App\Support\Dokumen\SusunanKak')

{{--
    Blok tanda tangan lembar pengesahan KAK & RAB dari SusunanKak::pengesahan(). Format 9 bagian: Mengetahui, Pembuat,
    Menyetujui sejajar. Format 5 bab: kolom ber-`baris` — Menyetujui & pembuat/Penanggungjawab berdampingan, Mengetahui di
    tengah di bawahnya (SusunanKak::barisPengesahan); `tanpa_nip` = baris NIP tidak dicetak.
--}}
@php
    $daftarBaris = SusunanKak::barisPengesahan($kolom);
    $bertingkat = count($daftarBaris) > 1 || isset($kolom[0]['baris']);
@endphp
<div {{ $attributes->class(['break-inside-avoid pt-4 text-center text-sm']) }} data-tata-letak="{{ $bertingkat ? 'dua_baris' : 'sejajar' }}">
    @foreach ($daftarBaris as $i => $baris)
        <div @class([
            'grid gap-6 sm:gap-4 cetak:gap-4',
            'mt-6' => $i > 0,
            'sm:grid-cols-3 cetak:grid-cols-3' => count($baris) === 3,
            'sm:grid-cols-2 cetak:grid-cols-2' => count($baris) === 2,
            'mx-auto sm:w-1/2 cetak:w-1/2' => count($baris) === 1 && $bertingkat,
        ]) data-baris-pengesahan="{{ $i }}">
            @foreach ($baris as $ttd)
                <div class="flex flex-col" data-ttd="{{ $ttd['sebutan'] ?? rtrim($ttd['peran'], ',') }}">
                    <p class="min-h-5">{{ $ttd['tempat_tanggal'] }}</p>
                    <p class="min-h-5">{{ $ttd['peran'] }}</p>
                    <p class="font-semibold">{{ $ttd['jabatan'] }}</p>
                    <div class="h-16" aria-hidden="true"></div>
                    <p @class(['font-semibold underline underline-offset-2' => $ttd['nama'], 'text-stone-400' => ! $ttd['nama']])>
                        {{ $ttd['nama'] ?? '( …………………………… )' }}
                    </p>
                    @unless ($ttd['tanpa_nip'] ?? false)
                        <p class="text-stone-600">NIP. {{ $ttd['nip'] ?? '……………………' }}</p>
                    @endunless
                </div>
            @endforeach
        </div>
    @endforeach
</div>
