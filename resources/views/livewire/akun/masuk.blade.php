<div>
    <h1 class="text-2xl font-bold tracking-tight text-stone-900">Masuk</h1>
    <p class="mt-1 text-sm text-stone-500">Gunakan akun yang dibuatkan admin madrasah. Dokumen yang Anda susun tersimpan atas nama akun dan leading Anda.</p>

    <form wire:submit="masuk" class="mt-8 space-y-5" data-testid="form-masuk" novalidate>
        <div>
            <label for="surel" class="form-label">Surel</label>
            <input id="surel" type="email" wire:model="surel" autocomplete="username" inputmode="email" autofocus
                   @class(['form-input mt-1', 'ring-red-400' => $errors->has('surel')]) aria-describedby="galat-surel" data-testid="isian-surel">
            @error('surel')<p id="galat-surel" class="mt-1 text-xs font-medium text-red-700" role="alert" data-testid="galat-surel">{{ $message }}</p>@enderror
        </div>
        <div x-data="{ lihat: false }">
            <div class="flex items-center justify-between">
                <label for="sandi" class="form-label">Sandi</label>
                <span class="text-xs text-stone-500" title="Sandi diatur ulang oleh admin di Pengaturan › Leading & User">Lupa sandi? Hubungi admin.</span>
            </div>
            <div class="relative mt-1">
                <input id="sandi" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model="sandi" autocomplete="current-password"
                       @class(['form-input pr-10', 'ring-red-400' => $errors->has('sandi')]) aria-describedby="galat-sandi" data-testid="isian-sandi">
                <button type="button" x-on:click="lihat = ! lihat" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-stone-400 hover:text-stone-700" x-bind:aria-label="lihat ? 'Sembunyikan sandi' : 'Tampilkan sandi'" data-testid="lihat-sandi">
                    <x-ui.icon name="eye" class="size-4" />
                </button>
            </div>
            @error('sandi')<p id="galat-sandi" class="mt-1 text-xs font-medium text-red-700" role="alert" data-testid="galat-sandi">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model="ingat" class="size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-500" data-testid="ingat-saya">
            Ingat saya di perangkat ini
        </label>
        <button type="submit" wire:loading.attr="disabled" wire:target="masuk" class="btn-primary w-full justify-center py-2.5" data-testid="tombol-masuk">
            <span wire:loading.remove wire:target="masuk">Masuk</span>
            <span wire:loading wire:target="masuk">Memeriksa…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">Belum punya akun? <a href="{{ route('akun.daftar') }}" class="font-medium text-brand-700 hover:underline" data-testid="tautan-daftar">Ajukan pendaftaran</a></p>

    @if ($persona !== [])
        <section class="mt-8 rounded-2xl bg-gold-50 p-4 ring-1 ring-gold-200" aria-labelledby="judul-demo" data-testid="akun-demo">
            <h2 id="judul-demo" class="flex items-center gap-1.5 text-sm font-semibold text-gold-900"><x-ui.icon name="info" class="size-4" /> Mode demo</h2>
            <p class="mt-1 text-xs text-gold-900/80">Autentikasi belum diaktifkan; sandi belum diperiksa. Masuk dengan akun contoh:</p>
            <ul class="mt-3 space-y-2" role="list">
                @foreach ($persona as $p)
                    <li>
                        <button type="button" wire:click="masukSebagai({{ $p['id'] }})" class="flex w-full items-center justify-between gap-3 rounded-xl bg-white px-3 py-2.5 text-left ring-1 ring-gold-200 hover:ring-gold-400" data-testid="masuk-sebagai" data-peran="{{ $p['peran'] }}">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-stone-900">{{ $p['nama'] }}</span>
                                <span class="block truncate text-xs text-stone-500">{{ $p['surel'] }} · {{ $p['peran'] === 'admin' ? 'Admin' : 'Leading' }}</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="size-4 shrink-0 text-stone-400" />
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
