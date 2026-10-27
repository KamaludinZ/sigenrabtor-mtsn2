{{--
    Panel saran AI satu bagian ($kunci, $b): 2–3 versi dari PenyusunSaranKalimat yang sudah dirapikan. Memakai, menyunting,
    dan menolak versi menyusul; Buat ulang meminta versi baru (dihitung dalam batas harian).
--}}
<section class="mb-4 rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-200 sm:p-4" aria-labelledby="judul-saran-{{ $kunci }}" data-testid="saran-{{ $kunci }}" wire:key="saran-{{ $kunci }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 id="judul-saran-{{ $kunci }}" class="flex items-center gap-1.5 text-sm font-semibold text-brand-900">
            <x-ui.icon name="sparkles" class="size-4" /> {{ count($saran[$kunci]) }} saran AI untuk {{ mb_strtolower($b['judul']) }}
        </h3>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="mintaSaran('{{ $kunci }}')" wire:loading.attr="disabled" wire:target="mintaSaran" class="btn-ghost px-2 py-1 text-xs" data-testid="buat-ulang-{{ $kunci }}">
                <x-ui.icon name="history" class="size-3.5" /> Buat ulang
            </button>
            <button type="button" wire:click="tutupSaran" class="btn-ghost px-2 py-1 text-xs">Tutup</button>
        </div>
    </div>
    <ol class="mt-2 space-y-2" aria-live="polite">
        @foreach ($saran[$kunci] as $i => $versi)
            <li class="rounded-lg bg-white p-3 ring-1 ring-brand-100" data-versi="{{ $i + 1 }}">
                <p class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold text-stone-900">{{ $versi['judul'] }}</span>
                    @if ($versi['ciri'] !== '')
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $versi['ciri'] }}</span>
                    @endif
                </p>
                <p @class(['mt-1.5 text-sm whitespace-pre-line text-stone-800', 'font-semibold tracking-wide' => $kunci === 'nama_cover'])>{{ $versi['teks'] }}</p>
                <div class="mt-2 flex justify-end">
                    <button type="button" class="btn-secondary px-2.5 py-1 text-xs" disabled title="Sedang disiapkan">Pakai versi ini <x-ui.segera /></button>
                </div>
            </li>
        @endforeach
    </ol>
</section>
