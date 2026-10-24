<div class="space-y-3">
    <x-ui.uraian id="konteks" label="Catatan konteks untuk AI" model="form.konteks" :max="3000" :rows="5"
                 placeholder="Contoh: kegiatan lanjutan dari workshop tahun lalu; peserta 49 guru dan 6 tenaga kependidikan; narasumber pengawas madrasah; output berupa dokumen KTSP yang disahkan kepala madrasah."
                 hint="Opsional dan tidak tercetak di dokumen. Dipakai sebagai bahan tombol “Buat dengan AI” pada latar belakang, tujuan, sasaran, pelaksanaan, jadwal, dan nama cover." />

    <p class="flex items-start gap-2 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200">
        <x-ui.icon name="lock" class="mt-px size-4 shrink-0 text-stone-400" />
        Jangan menuliskan NIP atau nama pejabat di sini. Data pejabat tidak pernah dikirim ke penyedia AI.
    </p>
</div>
