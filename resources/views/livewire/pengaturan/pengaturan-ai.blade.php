@use('App\Livewire\Pengaturan\PengaturanAi')

{{-- Pengaturan AI (PRD §21): penyedia, prompt per bagian, dan batas pemakaian dalam tiga tab. --}}
<div class="mx-auto max-w-6xl">
    <x-pengaturan.kepala judul="Pengaturan AI"
                         deskripsi="Penyedia AI untuk tombol “Buat dengan AI” dan usulan jadwal. AI hanya menyarankan kalimat — angka, kode, dan nama pejabat tidak pernah diubah; NIP dan nama pejabat tidak pernah dikirim.">
        @php $terakhir = $this->terakhirDisimpan(); @endphp
        @if ($terakhir)
            <x-slot:aksi>
                <p class="text-xs text-stone-500 sm:text-right" data-testid="terakhir-disimpan-ai">
                    Terakhir disimpan {{ \Illuminate\Support\Carbon::parse($terakhir['waktu'])->locale('id')->translatedFormat('j M Y, H.i') }} oleh {{ $terakhir['pengguna'] }}
                    <span class="block">{{ $terakhir['label'] }} · <a href="{{ route('pengaturan.riwayat.index', ['bagian' => 'ai']) }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Riwayat</a></span>
                </p>
            </x-slot:aksi>
        @endif
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian pengaturan AI" data-testid="tab-pengaturan-ai">
                @foreach (PengaturanAi::TAB as $kunci => $label)
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">{{ $label }}</span>
                    @else
                        <button type="button" x-on:click="if (! window.SIRABTOR.adaFormKotor() || confirm('Perubahan yang belum disimpan di tab ini akan hilang. Pindah tab?')) $wire.set('tab', '{{ $kunci }}')" class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800" data-tab="{{ $kunci }}">{{ $label }}</button>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div wire:key="tab-ai-{{ $tab }}">
        @include('livewire.pengaturan.partials.ai-'.$tab)
    </div>
</div>
