@use('App\Support\Pengaturan\BagianPengaturan')
@use('App\Support\Pengaturan\RiwayatPengaturan')
@use('App\Support\Format')
@use('Illuminate\Support\Carbon')

@php
    $baris = $this->halamanIni;
    $jumlah = count($this->terfilter);
    $jumlahHalaman = $this->jumlahHalaman();
    $adaSaringan = $bagian !== '' || $pengguna !== '' || trim($cari) !== '';
    $bagianJudul = collect(BagianPengaturan::semua())->pluck('judul', 'kunci')->all();
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Riwayat Perubahan"
                         deskripsi="Siapa mengubah pengaturan apa dan kapan — tahun anggaran, cover, master KAK/TOR, daftar isi, dan nomor halaman. Riwayat tidak bisa diubah atau dihapus." />

    <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-riwayat" data-testid="tabel-riwayat">
        <header class="flex flex-wrap items-end gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
            <h2 id="judul-riwayat" class="sr-only">Daftar perubahan</h2>
            <label class="min-w-0 flex-1 sm:max-w-xs">
                <span class="form-label">Cari</span>
                <input type="search" wire:model.live.debounce.300ms="cari" class="form-input" placeholder="mis. cover, 2026, nomor halaman" autocomplete="off">
            </label>
            <label class="w-full sm:w-48">
                <span class="form-label">Bagian</span>
                <select wire:model.live="bagian" class="form-input">
                    <option value="">Semua bagian</option>
                    @foreach ($this->pilihanBagian() as $kunci => $judul)
                        <option value="{{ $kunci }}">{{ $judul }}</option>
                    @endforeach
                </select>
            </label>
            <label class="w-full sm:w-52">
                <span class="form-label">Pengguna</span>
                <select wire:model.live="pengguna" class="form-input">
                    <option value="">Semua pengguna</option>
                    @foreach ($this->pilihanPengguna() as $nama)
                        <option value="{{ $nama }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </label>
            <p class="w-full text-xs text-stone-500 sm:ml-auto sm:w-auto sm:pb-2.5" data-testid="jumlah-riwayat">
                {{ $jumlah }} perubahan{{ $adaSaringan ? ' cocok' : '' }}
                @if ($adaSaringan)
                    · <button type="button" wire:click="hapusSaringan" class="font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
                @endif
            </p>
        </header>

        @if ($baris === [])
            <div class="px-5 py-10 text-center sm:px-6" data-testid="riwayat-kosong">
                <x-ui.icon name="history" class="mx-auto size-8 text-stone-300" />
                <p class="mt-2 text-sm font-medium text-stone-700">{{ $adaSaringan ? 'Tidak ada perubahan yang cocok' : 'Belum ada perubahan tercatat' }}</p>
            </div>
        @else
            {{-- Layar lebar: tabel; layar sempit: kartu per perubahan. --}}
            <div class="hidden md:block">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs font-semibold text-stone-500">
                        <tr>
                            <th scope="col" class="w-44 px-6 py-2">Waktu</th>
                            <th scope="col" class="w-44 px-3 py-2">Pengguna</th>
                            <th scope="col" class="w-52 px-3 py-2">Perubahan</th>
                            <th scope="col" class="px-3 py-2">Keterangan</th>
                            <th scope="col" class="w-16 px-6 py-2 text-right">TA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($baris as $r)
                            @php
                                $waktu = Carbon::parse($r['waktu'])->timezone(config('app.timezone'));
                                [$labelAksi, $kunciBagian] = RiwayatPengaturan::AKSI[$r['aksi']] ?? [$r['aksi'], null];
                            @endphp
                            <tr class="align-top" data-riwayat="{{ $r['aksi'] }}" wire:key="riwayat-{{ $r['id'] }}">
                                <td class="px-6 py-3 text-stone-700">
                                    <span class="block">{{ Format::tanggal($waktu->toDateString()) }}</span>
                                    <span class="text-xs text-stone-400 tabular-nums">pukul {{ $waktu->format('H.i') }}</span>
                                </td>
                                <td class="px-3 py-3 text-stone-700">{{ $r['pengguna'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="block font-medium text-stone-900">{{ $labelAksi }}</span>
                                    @if ($kunciBagian)
                                        <span class="text-xs text-stone-500">{{ $bagianJudul[$kunciBagian] ?? $kunciBagian }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 break-words text-stone-700">{{ $r['keterangan'] }}</td>
                                <td class="px-6 py-3 text-right text-stone-500 tabular-nums">{{ $r['tahun'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-stone-100 md:hidden" aria-label="Daftar perubahan">
                @foreach ($baris as $r)
                    @php
                        $waktu = Carbon::parse($r['waktu'])->timezone(config('app.timezone'));
                        [$labelAksi] = RiwayatPengaturan::AKSI[$r['aksi']] ?? [$r['aksi']];
                    @endphp
                    <li class="px-5 py-3" wire:key="riwayat-m-{{ $r['id'] }}">
                        <p class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-sm font-medium text-stone-900">{{ $labelAksi }}</span>
                            <span class="text-xs text-stone-400 tabular-nums">{{ Format::tanggal($waktu->toDateString()) }}, {{ $waktu->format('H.i') }}</span>
                        </p>
                        <p class="mt-0.5 text-sm break-words text-stone-700">{{ $r['keterangan'] }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $r['pengguna'] }} · TA {{ $r['tahun'] }}</p>
                    </li>
                @endforeach
            </ul>

            @if ($jumlahHalaman > 1)
                <nav class="flex items-center justify-between gap-2 border-t border-stone-100 px-5 py-3 text-sm sm:px-6" aria-label="Halaman riwayat">
                    <button type="button" class="btn-ghost px-3 py-1.5 text-xs" wire:click="ke({{ $halaman - 1 }})" @disabled($halaman <= 1)>Sebelumnya</button>
                    <span class="text-xs text-stone-500">Halaman {{ $halaman }} dari {{ $jumlahHalaman }}</span>
                    <button type="button" class="btn-ghost px-3 py-1.5 text-xs" wire:click="ke({{ $halaman + 1 }})" @disabled($halaman >= $jumlahHalaman)>Berikutnya</button>
                </nav>
            @endif
        @endif
    </section>
</div>
