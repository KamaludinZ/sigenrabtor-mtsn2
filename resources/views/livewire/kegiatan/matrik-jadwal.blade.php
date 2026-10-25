@use('App\Support\Format')
@use('App\Support\Kegiatan\JadwalPelaksanaan')
@use('App\Support\Kegiatan\MatrikTahapan')

@php
    $matrik = $this->matrik;
    $jadwal = $this->jadwal;
    $pelaksanaan = $this->pelaksanaan;
    $tahun = $this->tahun();
    $bulanPelaksanaan = $pelaksanaan->bulan;
    $selesai = $pelaksanaan->selesai();
    $batasLaporan = Format::tanggal($dataKegiatan['tanggal_laporan'] ?? null);
    $namaBulan = JadwalPelaksanaan::NAMA_BULAN;
    $kolomPelaksanaan = fn (int $b) => in_array($b, $bulanPelaksanaan, true);
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="text-stone-700">Matrik &amp; Jadwal</span>
        </nav>
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Matrik &amp; Jadwal Kegiatan</h1>
            <x-ui.status-badge :status="$this->status()->value" />
        </div>
        <p class="mt-1 text-sm text-stone-500">{{ $dataKegiatan['nama_kegiatan'] }}</p>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="matrik" :kegiatan-id="$kegiatanId" />
    </div>

    {{-- Acuan waktu dari Data Kegiatan --}}
    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-600 ring-1 ring-stone-200" data-testid="acuan-pelaksanaan">
        <span class="flex items-center gap-2">
            <x-ui.icon name="calendar" class="size-4 shrink-0 text-stone-400" />
            Bulan pelaksanaan
            <strong @class(['font-semibold', 'text-stone-900' => $bulanPelaksanaan !== [], 'text-gold-700' => $bulanPelaksanaan === []])>{{ JadwalPelaksanaan::teksBulan($bulanPelaksanaan, $tahun) ?? 'belum diisi' }}</strong>
        </span>
        @if ($pelaksanaan->mulai && $selesai)
            <span>Tanggal <strong class="font-semibold text-stone-900">{{ JadwalPelaksanaan::teksRentang($pelaksanaan->mulai, $selesai) }}</strong></span>
        @endif
        @if ($batasLaporan)
            <span>Batas laporan <strong class="font-semibold text-stone-900">{{ $batasLaporan }}</strong></span>
        @endif
        <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#pelaksanaan" class="text-xs font-semibold text-brand-700 hover:text-brand-800 sm:ml-auto">Ubah di Data Kegiatan</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="min-w-0 space-y-6">
            {{-- A. Matrik tahapan (KAK bagian 4) --}}
            <section id="matrik" class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-matrik" data-testid="matrik-tahapan">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-matrik" class="text-base font-semibold text-stone-900">Matrik tahapan</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Bulan pelaksanaan tiap butir tahapan — tercetak di KAK bagian 4. Tahapan Kegiatan.</p>
                    </div>
                    <p class="text-xs font-medium text-stone-500" data-testid="ringkasan-matrik">{{ $matrik->jumlahTanda() }} tanda bulan</p>
                </header>

                <div class="overflow-x-auto" data-testid="matrik-wadah">
                    <table class="w-full min-w-[33rem] text-sm sm:min-w-[44rem]">
                        <caption class="sr-only">Matrik tahapan kegiatan per bulan, Tahun Anggaran {{ $tahun }}</caption>
                        <thead>
                            <tr class="bg-stone-50 text-xs font-semibold text-stone-500">
                                <th scope="col" class="sticky left-0 z-10 w-36 bg-stone-50 px-4 py-2 text-left sm:w-auto sm:px-6">Kegiatan</th>
                                @foreach ($namaBulan as $nomor => $nama)
                                    <th scope="col" title="{{ $nama }} {{ $tahun }}" data-bulan="{{ $nomor }}"
                                        @class(['w-8 px-0.5 py-2 text-center sm:w-11 sm:px-1', 'bg-brand-50 text-brand-800' => $kolomPelaksanaan($nomor)])>{{ mb_substr($nama, 0, 3) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach (MatrikTahapan::TAHAPAN as $kunci => $judul)
                            <tbody class="border-t border-stone-200" data-tahapan="{{ $kunci }}">
                                <tr>
                                    <th scope="rowgroup" class="sticky left-0 z-10 bg-white px-4 pt-3 pb-1.5 text-left font-semibold text-stone-900 sm:px-6">{{ $loop->iteration }}. {{ $judul }}</th>
                                    @foreach ($namaBulan as $nomor => $nama)
                                        <td @class(['px-1', 'bg-brand-50/60' => $kolomPelaksanaan($nomor)])></td>
                                    @endforeach
                                </tr>
                                @foreach ($matrik->butir[$kunci] as $butir)
                                    <tr class="border-t border-stone-100" data-testid="butir-{{ $kunci }}-{{ $loop->iteration }}">
                                        {{-- Layar sempit: kolom uraian dipersempit agar lebih banyak bulan terlihat tanpa menggeser. --}}
                                        <th scope="row" class="sticky left-0 z-10 w-36 max-w-36 bg-white py-2 pr-2 pl-6 text-left text-xs font-normal text-stone-700 sm:w-auto sm:max-w-64 sm:pr-3 sm:pl-10 sm:text-sm">
                                            {{ $butir['uraian'] ?: 'Uraian belum diisi' }}
                                        </th>
                                        @foreach ($namaBulan as $nomor => $nama)
                                            @php $ditandai = in_array($nomor, $butir['bulan'], true); @endphp
                                            <td data-bulan="{{ $nomor }}" data-ditandai="{{ $ditandai ? 'ya' : 'tidak' }}"
                                                @class(['px-1 py-2 text-center', 'bg-brand-50/60' => $kolomPelaksanaan($nomor)])>
                                                @if ($ditandai)
                                                    <x-ui.icon name="check" class="mx-auto size-4 text-brand-700" />
                                                    <span class="sr-only">{{ $nama }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>

                <footer class="flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-brand-50 ring-1 ring-brand-200" aria-hidden="true"></span> Bulan pelaksanaan (Data Kegiatan)</span>
                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="check" class="size-3.5 text-brand-700" /> Bulan tahapan dilaksanakan</span>
                    <span class="sm:hidden">Geser tabel ke samping untuk melihat semua bulan.</span>
                </footer>
            </section>

            {{-- B. Jadwal kegiatan (KAK bagian 7) --}}
            <section id="jadwal" class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-jadwal" data-testid="jadwal-kegiatan">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-jadwal" class="text-base font-semibold text-stone-900">Jadwal kegiatan</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Urutan kegiatan dan waktu pelaksanaannya — tercetak di KAK bagian 7. Jadwal Kegiatan.</p>
                    </div>
                    <p class="text-xs font-medium text-stone-500" data-testid="ringkasan-jadwal">{{ $jadwal->jumlahTerisi() }} dari {{ count($jadwal->baris) }} baris terisi</p>
                </header>

                <table class="w-full text-sm">
                    <caption class="sr-only">Jadwal kegiatan, Tahun Anggaran {{ $tahun }}</caption>
                    <thead>
                        <tr class="bg-stone-50 text-xs font-semibold text-stone-500">
                            <th scope="col" class="w-12 py-2 pr-2 pl-5 text-left sm:pl-6">No</th>
                            <th scope="col" class="px-3 py-2 text-left">Uraian kegiatan</th>
                            <th scope="col" class="w-36 py-2 pr-5 pl-3 text-left sm:w-48 sm:pr-6">Waktu pelaksanaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($jadwal->baris as $index => $baris)
                            @php $waktu = $jadwal->waktu($index, $tahun); @endphp
                            <tr class="align-top" data-testid="baris-jadwal-{{ $index + 1 }}">
                                <td class="py-2.5 pr-2 pl-5 text-stone-500 tabular-nums sm:pl-6">{{ $index + 1 }}</td>
                                <td @class(['px-3 py-2.5', 'text-stone-800' => $baris['uraian'] !== '', 'text-stone-400 italic' => $baris['uraian'] === ''])>{{ $baris['uraian'] ?: 'Belum diisi' }}</td>
                                <td @class(['py-2.5 pr-5 pl-3 sm:pr-6', 'text-stone-800' => $waktu, 'text-stone-400' => ! $waktu])>{{ $waktu ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        {{-- Samping: kelengkapan untuk dokumen --}}
        <aside class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tercetak" data-testid="kelengkapan-matrik-jadwal">
                <h2 id="judul-tercetak" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tercetak di KAK</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach ([
                        ['kunci' => 'matrik', 'judul' => '4. Tahapan Kegiatan', 'lengkap' => $matrik->lengkap(),
                         'teks' => $matrik->lengkap() ? 'Ketiga tahapan sudah bertanda bulan.' : 'Tandai minimal satu bulan di setiap tahapan.'],
                        ['kunci' => 'jadwal', 'judul' => '7. Jadwal Kegiatan', 'lengkap' => $jadwal->lengkap(),
                         'teks' => $jadwal->jumlahTerisi().' dari '.count($jadwal->baris).' baris berisi uraian & waktu.'],
                    ] as $bagian)
                        <li class="flex items-start gap-2.5" data-testid="kelengkapan-{{ $bagian['kunci'] }}" data-lengkap="{{ $bagian['lengkap'] ? 'ya' : 'tidak' }}">
                            <x-ui.icon :name="$bagian['lengkap'] ? 'check-circle' : 'circle'" class="mt-0.5 size-4 shrink-0 {{ $bagian['lengkap'] ? 'text-brand-600' : 'text-stone-300' }}" />
                            <div class="min-w-0">
                                <a href="#{{ $bagian['kunci'] }}" class="font-medium text-stone-800 hover:text-brand-700">{{ $bagian['judul'] }}</a>
                                <p class="text-xs text-stone-500">{{ $bagian['teks'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="rounded-2xl bg-gold-50 p-4 text-xs text-gold-700 ring-1 ring-gold-200">
                <p class="flex items-center gap-1.5 font-semibold">
                    <x-ui.icon name="info" class="size-4" />
                    Satu tahun untuk semua
                </p>
                <p class="mt-1.5 leading-relaxed">Waktu jadwal cukup memilih bulan; tahunnya selalu Tahun Anggaran {{ $tahun }} sehingga tidak ada tahun lama yang tertinggal di dokumen.</p>
            </div>
        </aside>
    </div>
</div>
