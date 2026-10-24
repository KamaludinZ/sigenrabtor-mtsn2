@use('App\Support\Kegiatan\JadwalPelaksanaan')

@php
    $tahun = $this->tahunAktif['tahun'];
    $mulai = $form->tanggalMulai();
    $selesai = $form->tanggalSelesai();
    $bulanDariTanggal = $mulai && $selesai ? JadwalPelaksanaan::bulanTercakup($mulai, $selesai) : null;
    $lama = (int) $form->lama_hari;
@endphp

<div class="space-y-6">
    {{-- Jadwal pelaksanaan --}}
    <div>
        <h3 class="text-sm font-semibold text-stone-800">Jadwal pelaksanaan</h3>
        <p class="text-xs text-stone-500">Tanggal selesai, bulan pelaksanaan, dan batas laporan dihitung otomatis.</p>

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

        @if ($mulai && $selesai && $lama > 0)
            <div class="mt-4 flex items-start gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900 ring-1 ring-brand-100">
                <x-ui.icon name="file-text" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                <p>
                    <span class="font-semibold">Tercetak di KAK:</span>
                    {{ 'Kegiatan '.($form->nama_kegiatan ?: '…').' akan dilaksanakan selama '.$lama.' hari, '.JadwalPelaksanaan::teksRentang($mulai, $selesai).'.' }}
                </p>
            </div>
        @endif
    </div>

    {{-- Bulan pelaksanaan --}}
    <div>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="form-label mb-0">Bulan pelaksanaan</p>
            @if ($bulanDariTanggal && $bulanDariTanggal !== $form->bulan_pelaksanaan)
                <button type="button" wire:click="sesuaikanBulan" class="text-xs font-semibold text-brand-700 hover:text-brand-800">
                    Sesuaikan dengan tanggal
                </button>
            @endif
        </div>

        <div class="mt-2 grid grid-cols-4 gap-1.5 sm:grid-cols-6 lg:grid-cols-12" role="group" aria-label="Bulan pelaksanaan">
            @foreach (JadwalPelaksanaan::NAMA_BULAN as $nomor => $nama)
                @php $dipilih = in_array($nomor, $form->bulan_pelaksanaan, true); @endphp
                <button type="button" wire:click="toggleBulan({{ $nomor }})" aria-pressed="{{ $dipilih ? 'true' : 'false' }}"
                        title="{{ $nama }} {{ $tahun }}"
                        @class([
                            'rounded-lg px-2 py-1.5 text-xs font-semibold ring-1 ring-inset transition',
                            'bg-brand-700 text-white ring-brand-700' => $dipilih,
                            'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50' => ! $dipilih,
                        ])>
                    {{ mb_substr($nama, 0, 3) }}
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

    {{-- Tempat & peserta --}}
    <div class="grid gap-5 border-t border-stone-100 pt-5 sm:grid-cols-2">
        <x-ui.field class="sm:col-span-2" label="Tempat pelaksanaan" for="tempat" error="form.tempat"
                    hint="Wajib untuk dokumen lengkap. Tercetak: “Kegiatan … bertempat di {tempat}”.">
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

        <x-ui.uraian class="sm:col-span-2" id="uraian_pelaksanaan" label="Uraian bentuk pelaksanaan" model="form.uraian_pelaksanaan"
                     :max="3000" :rows="3"
                     placeholder="mis. workshop tatap muka dengan narasumber pengawas madrasah, dilanjutkan kerja kelompok penyusunan perangkat."
                     hint="Melengkapi kalimat “Kegiatan … akan dilaksanakan selama … hari” pada bagian Tahapan: Pelaksanaan." />
    </div>
</div>
