@php
    $peringatan = $form->peringatan();
    $tahunDok = $this->tahunAktif['tahun'];
    $mulaiDok = $form->tanggalMulai();
    $selesaiDok = $form->tanggalSelesai();
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field class="sm:col-span-2" label="Nama kegiatan" for="nama_kegiatan" error="form.nama_kegiatan" required
                :warning="$peringatan['nama_kegiatan'] ?? null"
                hint="Tulis tanpa awalan “Kegiatan”, mis. Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran.">
        <input type="text" id="nama_kegiatan" wire:model.live.debounce.500ms="form.nama_kegiatan" maxlength="255"
               autocomplete="off" class="form-input" placeholder="Nama kegiatan sesuai cascade Perkin"
               @error('form.nama_kegiatan') aria-invalid="true" aria-describedby="nama_kegiatan-error" @enderror>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Nama kegiatan (cover)" for="nama_kegiatan_cover" error="form.nama_kegiatan_cover" required
                hint="Selalu huruf kapital; tercetak di cover KAK/TOR dan judul RAB.">
        <x-slot:aside>
            @if ($form->cover_manual)
                <button type="button" wire:click="resetNamaCover" class="font-semibold text-brand-700 hover:text-brand-800">
                    Samakan dengan nama kegiatan
                </button>
            @else
                <span class="inline-flex items-center gap-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">
                    <x-ui.icon name="check" class="size-3" /> Otomatis
                </span>
            @endif
        </x-slot:aside>
        <input type="text" id="nama_kegiatan_cover" wire:model.live.blur="form.nama_kegiatan_cover"
               x-on:input.capture="const [a, b] = [$el.selectionStart, $el.selectionEnd]; $el.value = $el.value.toUpperCase(); $el.setSelectionRange(a, b)" maxlength="255"
               autocomplete="off" class="form-input uppercase tracking-wide" placeholder="Terisi otomatis dari nama kegiatan"
               @error('form.nama_kegiatan_cover') aria-invalid="true" aria-describedby="nama_kegiatan_cover-error" @enderror>
    </x-ui.field>

    <x-ui.uraian class="sm:col-span-2" id="latar_belakang" label="Latar belakang (paragraf khusus)" model="form.latar_belakang"
                 :max="5000" :rows="4" placeholder="Alasan khusus kegiatan ini diselenggarakan (opsional)."
                 hint="Paragraf baku visi madrasah dan kalimat “…adalah kegiatan {nama}” ditambahkan otomatis di atas paragraf ini." />

    <x-ui.uraian class="sm:col-span-2" id="tujuan" label="Tujuan" model="form.tujuan" :max="2000"
                 :warning="$peringatan['tujuan'] ?? null"
                 placeholder="mis. menghasilkan dokumen kurikulum yang terlegitimasi sesuai prinsip partisipatif, transparan, dan akuntabel."
                 hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Kegiatan {nama} bertujuan {tujuan}”." />

    <x-ui.uraian class="sm:col-span-2" id="sasaran" label="Sasaran" model="form.sasaran" :max="2000"
                 :warning="$peringatan['sasaran'] ?? null"
                 placeholder="mis. dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah."
                 hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Terwujudnya {sasaran}”." />

    <x-ui.field label="Tanggal dokumen" for="tanggal_dokumen" error="form.tanggal_dokumen"
                :hint="\App\Support\Format::tanggal($form->tanggal_dokumen) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_dokumen) : 'Tanggal pada lembar pengesahan.'">
        <input type="date" id="tanggal_dokumen" wire:model.live="form.tanggal_dokumen" class="form-input"
               min="{{ $tahunDok - 1 }}-01-01" max="{{ $mulaiDok?->toDateString() ?? $tahunDok.'-12-31' }}"
               @error('form.tanggal_dokumen') aria-invalid="true" aria-describedby="tanggal_dokumen-error" @enderror>
    </x-ui.field>

    <x-ui.field label="Tanggal pelaporan" for="tanggal_laporan" error="form.tanggal_laporan"
                :warning="$peringatan['tanggal_laporan'] ?? null"
                :hint="(\App\Support\Format::tanggal($form->tanggal_laporan) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_laporan).'. ' : 'Batas penyerahan laporan ke kepala madrasah. ')
                    .($form->laporan_otomatis ? 'Otomatis 10 hari setelah kegiatan selesai.' : 'Diatur manual — kosongkan untuk kembali otomatis.')">
        <input type="date" id="tanggal_laporan" wire:model.live="form.tanggal_laporan" class="form-input"
               @if ($selesaiDok) min="{{ $selesaiDok->toDateString() }}" @endif
               @error('form.tanggal_laporan') aria-invalid="true" aria-describedby="tanggal_laporan-error" @enderror>
    </x-ui.field>
</div>
