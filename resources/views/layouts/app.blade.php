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
<body class="h-full">
    <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-full">
        {{-- Sidebar mobile (off-canvas) --}}
        <div x-cloak x-show="sidebarOpen" class="relative z-50 lg:hidden print:hidden" role="dialog" aria-modal="true">
            <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-stone-900/50" @click="sidebarOpen = false"></div>
            <div x-show="sidebarOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                 class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw]">
                <div class="relative flex w-full flex-col">
                    <button type="button" class="absolute top-3 right-3 rounded-md p-1.5 text-brand-100 hover:bg-white/10" @click="sidebarOpen = false">
                        <span class="sr-only">Tutup menu</span>
                        <x-ui.icon name="x" />
                    </button>
                    @include('layouts.partials.sidebar')
                </div>
            </div>
        </div>

        {{-- Sidebar desktop --}}
        <aside class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-68 lg:flex-col print:hidden">
            @include('layouts.partials.sidebar')
        </aside>

        <div class="lg:pl-68 print:pl-0">
            @include('layouts.partials.topbar')

            <main class="px-4 py-6 sm:px-6 lg:px-8 print:p-0">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast />
</body>
</html>
