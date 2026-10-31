@php
    // Teks untuk `content:` CSS @page: tanpa kutip/garis miring terbalik/tanda kurung sudut agar tidak keluar dari <style>.
    $cssTeks = fn (?string $teks) => str_replace(['\\', '"', '<', '>', "\n", "\r"], ['\\\\', '\\"', '', '', ' ', ' '], (string) $teks);
    $sumberAktif = $kak->sumber;
    $pratinjau = route('kegiatan.pratinjau', ['kegiatan' => $kegiatanId] + ($sumberAktif ? ['sumber' => $sumberAktif] : []));
    $semuaLembar = \App\Support\Dokumen\CetakDokumen::LEMBAR;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Judul = nama berkas: "Simpan sebagai PDF" di dialog cetak memakai nama ini. --}}
    <title>{{ $judul }}</title>
    @if ($template['teks'])
        <meta name="description" content="{{ $template['teks'] }}">
    @endif
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4;
            margin: 18mm 0 16mm;
            @bottom-left { content: "{!! $cssTeks($kaki) !!}"; margin-left: 15mm; font: 8pt sans-serif; color: #b91c1c; }
            @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); margin-right: 15mm; font: 8pt sans-serif; color: #57534e; }
        }
        @page sampul {
            margin: 0;
            @bottom-left { content: none; }
            @bottom-right { content: none; }
        }
        @media print {
            .lembar-sampul { page: sampul; }
            .lembar + .lembar { break-before: page; }
            .lembar article, .lembar > div { box-shadow: none !important; max-width: none !important; }
            .lembar [data-testid="cap-draf"] { display: none; }
            .lembar [data-testid="pratinjau-cover"] { height: 296mm; aspect-ratio: auto; }
        }
    </style>
</head>
<body class="bg-stone-200 text-stone-900 print:bg-white">
    {{-- Bilah layar (tidak ikut tercetak). --}}
    <header class="sticky top-0 z-20 border-b border-stone-300 bg-white/95 px-4 py-3 font-sans backdrop-blur print:hidden" data-testid="bilah-cetak">
        <div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ $pratinjau }}" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                    <x-ui.icon name="chevron-right" class="size-3.5 rotate-180" /> Kembali ke pratinjau
                </a>
                <p class="mt-0.5 truncate font-mono text-xs text-stone-600" data-testid="nama-berkas-cetak">{{ $nama_berkas }}</p>
                @if ($template['teks'])
                    <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-stone-500" data-testid="template-cetak" data-format="{{ $template['format']['kunci'] }}" data-template="{{ $template['kak']['id'] ?? '' }}">
                        <x-ui.icon name="file-text" class="size-3.5 shrink-0" /> <span class="truncate">{{ $template['teks'] }}</span>
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <nav class="flex flex-wrap items-center gap-1 text-xs" aria-label="Lembar yang dicetak">
                    @foreach ($semuaLembar as $kunciLembar => $labelLembar)
                        @php
                            $terpilih = in_array($kunciLembar, $lembar, true);
                            $berikut = $terpilih ? array_values(array_diff($lembar, [$kunciLembar])) : [...$lembar, $kunciLembar];
                        @endphp
                        <a href="{{ \App\Support\Dokumen\CetakDokumen::tautan($kegiatanId, $sumberAktif, $berikut ?: [$kunciLembar]) }}" aria-pressed="{{ $terpilih ? 'true' : 'false' }}" data-lembar-pilihan="{{ $kunciLembar }}"
                           @class(['inline-flex items-center gap-1 rounded-md px-2 py-1 font-semibold ring-1', 'bg-brand-50 text-brand-800 ring-brand-200' => $terpilih, 'text-stone-500 ring-stone-200 hover:text-stone-800' => ! $terpilih])>
                            <x-ui.icon :name="$terpilih ? 'check' : 'plus'" class="size-3" /> {{ $labelLembar }}
                        </a>
                    @endforeach
                </nav>
                <button type="button" onclick="window.print()" class="btn-primary" data-testid="tombol-cetak"><x-ui.icon name="printer" class="size-4" /> Cetak / Simpan PDF</button>
            </div>
        </div>
        @if ($draf->draf)
            <div class="mx-auto mt-2 max-w-[210mm] rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200" data-testid="peringatan-draf-cetak">
                <p class="font-semibold">Tercetak dengan tanda air DRAF di setiap halaman:</p>
                <ul class="mt-0.5 ml-4 list-disc">
                    @foreach ($draf->alasan as $alasan)
                        <li>{{ $alasan['teks'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <p class="mx-auto mt-2 max-w-[210mm] text-xs text-stone-500">Pilih <span class="font-semibold">Simpan sebagai PDF</span> di dialog cetak untuk mengunduh PDF. Atur ukuran kertas A4, skala 100%, dan nonaktifkan header & footer peramban.</p>
    </header>

    @if ($draf->draf)
        {{-- Tanda air DRAF di setiap halaman cetak (posisi tetap berulang per halaman). --}}
        <div class="pointer-events-none fixed inset-0 z-10 hidden items-center justify-center print:flex" aria-hidden="true" data-testid="tanda-air-cetak">
            <span class="-rotate-30 text-[110pt] font-bold tracking-[0.2em] text-stone-900/[0.07]">DRAF</span>
        </div>
    @endif

    <main class="mx-auto w-[210mm] max-w-full space-y-8 py-8 print:w-full print:space-y-0 print:py-0" data-testid="lembar-cetak">
        @foreach ($lembar as $kunciLembar)
            <section @class(['lembar lembar-cetak', 'lembar-sampul' => $kunciLembar === 'cover']) data-lembar-cetak="{{ $kunciLembar }}" aria-label="{{ $semuaLembar[$kunciLembar] }}">
                @switch($kunciLembar)
                    @case('cover')
                        <x-dokumen.cover :gambar="$cover['gambar']" :teks="$cover['teks']" :nilai="$cover['nilai']" :draf="$cetak->set->draf" :jangkar="false" class="shadow-md print:shadow-none print:ring-0" />
                        @break
                    @case('kak')
                        <x-dokumen.kak-tor :susunan="$kak" :bagian="range(1, 9)" class="shadow-md print:shadow-none print:ring-0" />
                        @break
                    @default
                        <x-dokumen.rab :susunan="$rab" class="shadow-md print:shadow-none print:ring-0" />
                @endswitch
            </section>
        @endforeach
    </main>

    @if ($otomatis)
        <script>window.addEventListener('load', () => window.setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
