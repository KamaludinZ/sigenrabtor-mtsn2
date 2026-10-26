{{-- Kesiapan tahun aktif untuk bagian Profil & Pejabat (butir KesiapanPengaturan berbagian "madrasah"). --}}
<section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kesiapan-madrasah" data-testid="kesiapan-madrasah">
    <h2 id="judul-kesiapan-madrasah" class="text-sm font-semibold text-stone-900">Kesiapan TA {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }}</h2>
    <p class="mt-0.5 text-xs text-stone-500">Wajib lengkap sebelum leading mencetak dokumen.</p>
    <ul class="mt-3 space-y-2.5">
        @foreach ($this->kesiapan() as $butir)
            <li class="flex items-start gap-2 text-sm" data-kesiapan="{{ $butir['kunci'] }}" data-siap="{{ $butir['siap'] ? 'ya' : 'tidak' }}">
                <x-ui.icon :name="$butir['siap'] ? 'check-circle' : 'alert'" @class(['mt-0.5 size-4 shrink-0', 'text-brand-600' => $butir['siap'], 'text-gold-600' => ! $butir['siap']]) />
                <span class="min-w-0">
                    <span class="block font-medium text-stone-800">{{ $butir['judul'] }}</span>
                    <span class="block text-xs text-stone-500">{{ $butir['keterangan'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</section>
