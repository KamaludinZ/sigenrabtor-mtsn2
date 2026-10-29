{{--
    Layout halaman tamu (belum masuk): panel identitas aplikasi & madrasah di kiri (layar lebar), isi halaman di kanan.
    Dipakai halaman Masuk (`Akun\Masuk`) dan Daftar akun (`Akun\Daftar`). Variabel global dari AppLayoutComposer ($madrasah, $tahunAktif).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('sigenrabtor.aplikasi.nama') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-50">
    <div class="flex min-h-full">
        <aside class="relative hidden w-[44%] max-w-xl flex-col justify-between overflow-hidden bg-brand-900 p-10 text-white lg:flex" data-testid="panel-merek">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-gold-400 text-base font-bold text-brand-950">SR</span>
                <span class="leading-tight">
                    <span class="block text-lg font-bold tracking-wide">{{ config('sigenrabtor.aplikasi.nama') }} <span class="text-xs font-medium text-brand-300">v{{ config('sigenrabtor.aplikasi.versi') }}</span></span>
                    <span class="block text-sm text-brand-200">{{ $madrasah['nama_singkat'] }}</span>
                </span>
            </div>
            <div class="max-w-md">
                <p class="text-2xl leading-snug font-semibold">KAK/TOR dan RAB seragam, dari satu data kegiatan.</p>
                <ul class="mt-6 space-y-3 text-sm text-brand-100" role="list">
                    <li class="flex gap-2.5"><x-ui.icon name="check-circle" class="size-5 shrink-0 text-gold-300" /> Isi data kegiatan & rincian anggaran sekali.</li>
                    <li class="flex gap-2.5"><x-ui.icon name="check-circle" class="size-5 shrink-0 text-gold-300" /> Cover, KAK/TOR, RAB, dan pengesahan tersusun otomatis.</li>
                    <li class="flex gap-2.5"><x-ui.icon name="check-circle" class="size-5 shrink-0 text-gold-300" /> Diperiksa admin, dicetak per leading & sumber dana.</li>
                </ul>
            </div>
            <p class="text-xs text-brand-300">{{ $madrasah['nama'] }} · Tahun Anggaran {{ $tahunAktif['tahun'] }}</p>
            <div class="pointer-events-none absolute -right-24 -bottom-24 size-80 rounded-full bg-white/5" aria-hidden="true"></div>
        </aside>
        <main class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-xl bg-gold-400 text-sm font-bold text-brand-950">SR</span>
                    <span class="leading-tight">
                        <span class="block text-base font-bold text-stone-900">{{ config('sigenrabtor.aplikasi.nama') }}</span>
                        <span class="block text-xs text-stone-500">{{ $madrasah['nama_singkat'] }}</span>
                    </span>
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
    <x-ui.toast />
</body>
</html>
