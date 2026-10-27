{{--
    Bagian bawah satu kartu Kalimat KAK ($kunci, $b dari BagianAi::BAGIAN): bahan dari data kegiatan yang dipakai AI
    (belum diisi = abu-abu), apa yang dihasilkan AI, dan pratinjau bagian KAK tercetak dari teks di layar.
--}}
@php
    $bahan = \App\Support\Kegiatan\BagianAi::bahan($kunci, $kegiatan);
@endphp
<div class="mt-4 space-y-3 border-t border-stone-100 pt-3">
    <div>
        <p class="text-xs font-semibold text-stone-500">Bahan untuk AI dari data kegiatan</p>
        <ul class="mt-1.5 flex flex-wrap gap-1.5 text-xs" data-testid="bahan-{{ $kunci }}">
            @foreach ($bahan as $item)
                <li @class([
                        'inline-flex max-w-full items-center gap-1 rounded-md border px-2 py-0.5',
                        'border-stone-200 bg-stone-50 text-stone-700' => $item['nilai'] !== null,
                        'border-dashed border-stone-300 bg-white text-stone-400' => $item['nilai'] === null,
                    ]) data-terisi="{{ $item['nilai'] !== null ? 'ya' : 'tidak' }}" title="{{ $item['nilai'] ?? 'Belum diisi' }}">
                    <span class="shrink-0 font-semibold">{{ $item['label'] }}</span>
                    <span class="min-w-0 truncate">{{ $item['nilai'] ?? 'belum diisi' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    <p class="flex items-start gap-1.5 text-xs text-stone-500">
        <x-ui.icon name="sparkles" class="mt-px size-3.5 shrink-0 text-stone-400" />
        <span>AI menyarankan 2–3 versi: {{ mb_lcfirst($b['hasil']) }}</span>
    </p>

    @if ($b['kak'] !== null)
        <details class="rounded-lg bg-stone-50 ring-1 ring-stone-200" data-testid="pratinjau-{{ $kunci }}">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Lihat tercetak di KAK/TOR</summary>
            <div class="max-h-96 overflow-auto border-t border-stone-200 p-3">
                <x-dokumen.kak-tor :susunan="$susunan" :bagian="[$b['kak']]" />
            </div>
        </details>
    @else
        <p class="rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200" data-testid="pratinjau-{{ $kunci }}">
            Tercetak di cover: <span class="font-semibold tracking-wide text-stone-900">{{ $susunan->cover['kegiatan'] ?? '' }}</span>
        </p>
    @endif
</div>
