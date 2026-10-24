@props([
    'opsi',
    'terpilih' => null,
    'model' => 'form.leading',
    'peran' => 'leading',
])

{{--
    Pemilih leading penanggung jawab berbentuk kartu radio. Akun leading hanya menerima daftar leading miliknya
    (PRD F29: akun leading terikat ke satu/lebih leading); admin menerima semua leading aktif.
--}}
<div {{ $attributes }}>
    @if ($opsi === [])
        <p class="rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-700 ring-1 ring-gold-200">
            Akun Anda belum ditautkan ke leading mana pun. Hubungi admin untuk menautkan akun ke leading.
        </p>
    @else
        <div role="radiogroup" aria-label="Leading penanggung jawab" @class(['grid gap-2', 'sm:grid-cols-2' => count($opsi) > 1])>
            @foreach ($opsi as $leading)
                @php $aktif = (string) $terpilih === $leading['kode']; @endphp
                <label wire:key="leading-{{ $leading['kode'] }}" data-leading="{{ $leading['kode'] }}"
                       @class([
                           'relative flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 transition has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-600',
                           'bg-brand-50 ring-2 ring-brand-600' => $aktif,
                           'bg-white ring-stone-200 hover:ring-stone-300' => ! $aktif,
                       ])>
                    <input type="radio" name="leading" value="{{ $leading['kode'] }}" wire:model.live="{{ $model }}" class="sr-only"
                           aria-describedby="leading-jabatan-{{ str_replace('.', '-', $leading['kode']) }}">
                    <span @class([
                            'grid h-8 min-w-10 shrink-0 place-items-center rounded-lg px-1.5 text-xs font-bold tabular-nums',
                            'bg-brand-700 text-white' => $aktif,
                            'bg-stone-100 text-stone-600' => ! $aktif,
                        ])>{{ $leading['kode'] }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-stone-800">{{ $leading['nama_unit'] }}</span>
                        <span class="block text-xs text-stone-500" id="leading-jabatan-{{ str_replace('.', '-', $leading['kode']) }}">
                            Penanda tangan: {{ $leading['jabatan_penanda_tangan'] }}
                        </span>
                    </span>
                    @if ($aktif)
                        <x-ui.icon name="check-circle" class="size-5 shrink-0 text-brand-700" />
                    @endif
                </label>
            @endforeach
        </div>

        @if ($peran !== 'admin')
            <p class="mt-2 text-xs text-stone-500">
                {{ count($opsi) === 1 ? 'Akun Anda terikat ke leading ini.' : 'Hanya leading yang ditautkan ke akun Anda yang ditampilkan.' }}
                Leading lain diatur admin.
            </p>
        @endif
    @endif
</div>
