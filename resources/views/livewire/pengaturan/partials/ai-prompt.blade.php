@use('App\Support\Pengaturan\PromptAi')

{{-- Tab Prompt per bagian: gaya bahasa baku, batas aman, dan instruksi tiap bagian (bawaan selama belum diubah admin). --}}
<div class="mt-6 space-y-6" data-testid="panel-prompt-ai">
    <section class="grid gap-4 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 md:grid-cols-2" aria-label="Aturan umum prompt">
        <div>
            <h2 class="text-sm font-semibold text-stone-900">Gaya bahasa baku</h2>
            <p class="mt-1 text-sm text-stone-700" data-testid="gaya-bahasa-ai">{{ PromptAi::GAYA_BAHASA }}</p>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-stone-900">Batas aman (selalu disertakan)</h2>
            <p class="mt-1 text-sm text-stone-700">{{ PromptAi::BATAS_AMAN }}</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-prompt-bagian">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4">
            <div>
                <h2 id="judul-prompt-bagian" class="text-sm font-semibold text-stone-900">Instruksi per bagian</h2>
                <p class="mt-0.5 text-xs text-stone-500">Disusun bersama data kegiatan dan catatan konteks saat leading menekan “Buat dengan AI”.</p>
            </div>
            <button type="button" class="btn-secondary px-2.5 py-1.5 text-xs" disabled title="Sedang disiapkan"><x-ui.icon name="pencil" class="size-3.5" /> Ubah prompt <x-ui.segera /></button>
        </header>
        <ol class="divide-y divide-stone-100">
            @foreach ($this->prompt() as $p)
                <li class="grid gap-1 px-5 py-3 sm:grid-cols-[12rem_minmax(0,1fr)]" data-prompt="{{ $p['bagian'] }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-stone-900">{{ $p['judul'] }}</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">{{ $p['bawaan'] ? 'Bawaan' : 'Diubah' }}</span>
                    </div>
                    <p class="text-sm text-stone-700">{{ $p['instruksi'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</div>
