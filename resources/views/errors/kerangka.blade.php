{{--
    Kerangka halaman galat (403, 404, 419): berdiri sendiri tanpa bingkai aplikasi & Livewire — $kode, $judul, $pesan,
    $tombol (list [label, url|null = kembali ke halaman sebelumnya, testid]).
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} · {{ config('sigenrabtor.aplikasi.nama') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-stone-50">
    <main class="flex min-h-full items-center justify-center px-4 py-12" data-testid="halaman-galat" data-kode="{{ $kode }}">
        <div class="w-full max-w-md text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-xl bg-gold-400 text-sm font-bold text-brand-950">SR</span>
            <p class="mt-6 text-sm font-semibold tracking-wide text-brand-700">{{ $kode }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-stone-900">{{ $judul }}</h1>
            <p class="mt-2 text-sm text-stone-600" data-testid="pesan-galat">{{ $pesan }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @foreach ($tombol as [$label, $url, $testid])
                    @if ($url === null)
                        <a href="javascript:history.back()" class="btn-secondary" data-testid="{{ $testid }}">{{ $label }}</a>
                    @else
                        <a href="{{ $url }}" class="{{ $loop->first ? 'btn-primary' : 'btn-secondary' }}" data-testid="{{ $testid }}">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
            <p class="mt-8 text-xs text-stone-400">{{ config('sigenrabtor.aplikasi.nama') }} · {{ config('sigenrabtor.aplikasi.versi') }}</p>
        </div>
    </main>
</body>
</html>
