{{--
    Status simpan satu formulir Pengaturan AI ($bagian; di dalam x-data="formAi(...)"): penanda "Belum disimpan" selama
    isian berbeda dari yang tersimpan, "Tersimpan" sesaat sesudah berhasil, dan kegagalan simpan ($gagalSimpan) dengan
    ajakan mencoba lagi — isian tidak dibuang.
--}}
@if (($gagalSimpan['bagian'] ?? null) === $bagian)
    <p class="flex w-full items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200" role="alert" data-testid="gagal-simpan-{{ $bagian }}">
        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $gagalSimpan['pesan'] }} Isian Anda masih ada — tekan Simpan untuk mencoba lagi.
    </p>
@endif
<span class="inline-flex items-center gap-1 text-xs font-medium text-gold-700" x-show="kotor && ! tersimpan" x-cloak data-testid="belum-disimpan-{{ $bagian }}">
    <span class="size-1.5 rounded-full bg-gold-500"></span> Belum disimpan
</span>
<span class="inline-flex items-center gap-1 text-xs font-medium text-brand-700" x-show="tersimpan" x-cloak role="status" data-testid="tersimpan-{{ $bagian }}">
    <x-ui.icon name="check-circle" class="size-3.5" /> Tersimpan
</span>
