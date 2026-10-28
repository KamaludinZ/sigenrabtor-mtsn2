@props(['kolom'])

{{-- Blok tanda tangan lembar pengesahan KAK & RAB (Mengetahui, Pembuat, Menyetujui) dari SusunanKak::pengesahan(). --}}
<div {{ $attributes->class(['grid gap-6 pt-4 text-center text-sm sm:gap-4 cetak:gap-4', 'sm:grid-cols-3 cetak:grid-cols-3' => count($kolom) === 3, 'sm:grid-cols-2 cetak:grid-cols-2' => count($kolom) === 2]) }}>
    @foreach ($kolom as $ttd)
        <div class="flex flex-col">
            <p class="min-h-5">{{ $ttd['tempat_tanggal'] }}</p>
            <p>{{ $ttd['peran'] }}</p>
            <p class="font-semibold">{{ $ttd['jabatan'] }}</p>
            <div class="h-16" aria-hidden="true"></div>
            <p @class(['font-semibold underline underline-offset-2' => $ttd['nama'], 'text-stone-400' => ! $ttd['nama']])>
                {{ $ttd['nama'] ?? '( …………………………… )' }}
            </p>
            <p class="text-stone-600">NIP. {{ $ttd['nip'] ?? '……………………' }}</p>
        </div>
    @endforeach
</div>
