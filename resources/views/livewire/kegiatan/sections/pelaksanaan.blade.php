@use('App\Support\Kegiatan\JadwalPelaksanaan')
@use('App\Support\Kegiatan\KalimatKak')
@use('Carbon\CarbonImmutable')

@php
    $tahun = $this->tahunAktif['tahun'];
    $mulai = $form->tanggalMulai();
    $selesai = $form->tanggalSelesai();
    $bulanDariTanggal = $mulai && $selesai ? JadwalPelaksanaan::bulanTercakup($mulai, $selesai) : null;
    $lama = (int) $form->lama_hari;
    $kelompok = $form->kelengkapanPelaksanaan();
    $nama = $form->nama_kegiatan ?: '…';
    $batasLaporan = $form->tanggal_laporan ? rescue(fn () => CarbonImmutable::parse($form->tanggal_laporan), null, false) : null;

    // Pratinjau bagian KAK yang disusun dari isian ini; null = isian pendukungnya belum lengkap.
    $pratinjau = [
        ['bagian' => '4b. Pelaksanaan', 'kunci' => 'pelaksanaan', 'kurang' => 'tanggal mulai & lama kegiatan',
         'teks' => $mulai && $selesai && $lama > 0
            ? KalimatKak::pelaksanaan($nama, $lama, $mulai, $selesai, (int) $form->peserta_jumlah ?: null, $form->peserta_jenis ?: null, $form->uraian_pelaksanaan)
            : null],
        ['bagian' => '4c. Pelaporan', 'kunci' => 'pelaporan', 'kurang' => 'tanggal pelaporan (bagian Identitas)',
         'teks' => $batasLaporan ? KalimatKak::pelaporan($nama, $batasLaporan) : null],
        ['bagian' => '5. Tempat Pelaksanaan', 'kunci' => 'tempat', 'kurang' => 'tempat pelaksanaan',
         'teks' => filled($form->tempat) ? KalimatKak::tempat($nama, $form->tempat) : null],
    ];
@endphp

<div class="space-y-8">
    {{-- A. Waktu pelaksanaan --}}
    <div data-kelompok="waktu">
        <x-ui.sub-bagian huruf="A" judul="Waktu pelaksanaan" :kelengkapan="$kelompok['waktu']"
                         keterangan="Tanggal selesai, bulan pelaksanaan, dan batas laporan dihitung otomatis." />

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Tanggal mulai" for="tanggal_mulai" error="form.tanggal_mulai"
                        :warning="$form->peringatanJadwal()['tanggal_mulai'] ?? null">
                <input type="date" id="tanggal_mulai" wire:model.live="form.tanggal_mulai"
                       min="{{ $tahun }}-01-01" max="{{ $tahun }}-12-31" class="form-input"
                       @error('form.tanggal_mulai') aria-invalid="true" aria-describedby="tanggal_mulai-error" @enderror>
            </x-ui.field>

            <x-ui.field label="Lama kegiatan" for="lama_hari" error="form.lama_hari">
                <div class="relative">
                    <input type="number" id="lama_hari" wire:model.live.debounce.400ms="form.lama_hari" min="1" max="366" step="1"
                           inputmode="numeric" placeholder="1" class="form-input pr-12"
                           @error('form.lama_hari') aria-invalid="true" aria-describedby="lama_hari-error" @enderror>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-500">hari</span>
                </div>
            </x-ui.field>

            <div>
                <p class="form-label">Tanggal selesai</p>
                <output for="tanggal_mulai lama_hari" aria-live="polite"
                        class="flex min-h-9.5 items-center gap-2 rounded-lg bg-stone-100 px-3 py-2 text-sm ring-1 ring-stone-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-4 shrink-0 text-stone-400" />
                    @if ($selesai)
                        <span class="font-semibold text-stone-800" data-testid="tanggal-selesai">{{ $selesai->locale('id')->translatedFormat('l, j F Y') }}</span>
                    @else
                        <span class="text-stone-400">Isi tanggal mulai &amp; lama</span>
                    @endif
                </output>
                <p class="form-hint">Dihitung otomatis{{ $form->hari_kerja ? ' (hari kerja)' : '' }}.</p>
            </div>
        </div>

        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-stone-700">
            <input type="checkbox" wire:model.live="form.hari_kerja" class="size-4 rounded accent-brand-700">
            Hitung hari kerja saja (lewati Sabtu &amp; Minggu)
        </label>

        <div class="mt-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="form-label mb-0">Bulan pelaksanaan</p>
                @if ($bulanDariTanggal && $bulanDariTanggal !== $form->bulan_pelaksanaan)
                    <button type="button" wire:click="sesuaikanBulan" class="text-xs font-semibold text-brand-700 hover:text-brand-800">
                        Sesuaikan dengan tanggal
                    </button>
                @endif
            </div>

            <div class="mt-2 grid grid-cols-4 gap-1.5 sm:grid-cols-6 lg:grid-cols-12" role="group" aria-label="Bulan pelaksanaan">
                @foreach (JadwalPelaksanaan::NAMA_BULAN as $nomor => $namaBulan)
                    @php $dipilih = in_array($nomor, $form->bulan_pelaksanaan, true); @endphp
                    <button type="button" wire:click="toggleBulan({{ $nomor }})" aria-pressed="{{ $dipilih ? 'true' : 'false' }}"
                            title="{{ $namaBulan }} {{ $tahun }}"
                            @class([
                                'rounded-lg px-2 py-1.5 text-xs font-semibold ring-1 ring-inset transition',
                                'bg-brand-700 text-white ring-brand-700' => $dipilih,
                                'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50' => ! $dipilih,
                            ])>
                        {{ mb_substr($namaBulan, 0, 3) }}
                    </button>
                @endforeach
            </div>

            @error('form.bulan_pelaksanaan') <p class="form-error">{{ $message }}</p> @enderror
            <p class="form-hint">
                @if ($teksBulan = JadwalPelaksanaan::teksBulan($form->bulan_pelaksanaan, $tahun))
                    Tercetak: {{ $teksBulan }}. Dipakai juga untuk matrik tahapan &amp; jadwal.
                @else
                    Pilih satu atau lebih bulan sesuai timeline cascade Perkin; terisi otomatis dari tanggal mulai.
                @endif
            </p>
        </div>
    </div>

    {{-- B. Tempat & peserta --}}
    <div class="border-t border-stone-100 pt-6" data-kelompok="tempat">
        <x-ui.sub-bagian huruf="B" judul="Tempat & peserta" :kelengkapan="$kelompok['tempat']"
                         keterangan="Tempat tercetak di bagian 5 KAK; peserta melengkapi kalimat Tahapan: Pelaksanaan." />

        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <x-ui.field class="sm:col-span-2" label="Tempat pelaksanaan" for="tempat" error="form.tempat"
                        hint="Wajib untuk dokumen lengkap.">
                <input type="text" id="tempat" wire:model.live.blur="form.tempat" list="saran-tempat" maxlength="255" autocomplete="off"
                       placeholder="mis. Aula Madrasah" class="form-input"
                       @error('form.tempat') aria-invalid="true" aria-describedby="tempat-error" @enderror>
                <datalist id="saran-tempat">
                    @foreach (['Aula Madrasah', 'Lingkungan Madrasah', 'Ruang Kelas', 'Laboratorium', 'Masjid Madrasah', 'Ruang Rapat', 'Perpustakaan'] as $saran)
                        <option value="{{ $saran }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>

            <x-ui.field label="Jumlah peserta" for="peserta_jumlah" error="form.peserta_jumlah">
                <div class="relative">
                    <input type="number" id="peserta_jumlah" wire:model.live.blur="form.peserta_jumlah" min="1" max="100000" step="1"
                           inputmode="numeric" placeholder="0" class="form-input pr-16"
                           @error('form.peserta_jumlah') aria-invalid="true" aria-describedby="peserta_jumlah-error" @enderror>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-500">orang</span>
                </div>
            </x-ui.field>

            <x-ui.field label="Jenis peserta" for="peserta_jenis" error="form.peserta_jenis">
                <input type="text" id="peserta_jenis" wire:model.live.blur="form.peserta_jenis" list="saran-peserta" maxlength="150" autocomplete="off"
                       placeholder="mis. Guru dan tenaga kependidikan" class="form-input"
                       @error('form.peserta_jenis') aria-invalid="true" aria-describedby="peserta_jenis-error" @enderror>
                <datalist id="saran-peserta">
                    @foreach (['Guru', 'Siswa', 'Guru dan tenaga kependidikan', 'Tenaga kependidikan', 'Orang tua/wali siswa', 'Pengurus komite'] as $saran)
                        <option value="{{ $saran }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>
        </div>
    </div>

    {{-- C. Bentuk pelaksanaan --}}
    <div class="border-t border-stone-100 pt-6" data-kelompok="bentuk">
        <x-ui.sub-bagian huruf="C" judul="Bentuk pelaksanaan" :kelengkapan="$kelompok['bentuk']"
                         keterangan="Gambaran singkat jalannya kegiatan; disambung setelah kalimat Tahapan: Pelaksanaan." />

        <x-ui.uraian class="mt-4" id="uraian_pelaksanaan" label="Uraian bentuk pelaksanaan" model="form.uraian_pelaksanaan"
                     :max="3000" :rows="3"
                     placeholder="mis. workshop tatap muka dengan narasumber pengawas madrasah, dilanjutkan kerja kelompok penyusunan perangkat." />
    </div>

    {{-- Pratinjau KAK dari isian di atas --}}
    <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" data-testid="pratinjau-pelaksanaan">
        <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">
            <x-ui.icon name="file-text" class="size-3.5" /> Tercetak di KAK
        </p>
        <dl class="mt-3 space-y-3 text-sm">
            @foreach ($pratinjau as $baris)
                <div data-pratinjau="{{ $baris['kunci'] }}">
                    <dt class="text-xs font-semibold text-stone-500">{{ $baris['bagian'] }}</dt>
                    @if ($baris['teks'])
                        <dd class="mt-0.5 leading-relaxed text-stone-800">{{ $baris['teks'] }}</dd>
                    @else
                        <dd class="mt-0.5 flex items-start gap-1.5 text-gold-700">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                            Lengkapi {{ $baris['kurang'] }} agar bagian ini tercetak.
                        </dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</div>
