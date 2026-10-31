@props(['template', 'lembar' => 'kak'])

{{--
    Penanda template yang dipakai di kepala satu lembar pratinjau (TemplateDipakai; hanya di layar): lembar KAK/TOR =
    format susunan baku + master Word (nama, catatan versi, asal), lembar cover = template cover.
--}}
@if ($lembar === 'cover')
    <span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold tracking-normal text-stone-700 normal-case ring-1 ring-stone-200 ring-inset']) }}
          data-testid="kepala-template" data-kepala-lembar="cover" title="Template cover yang dipakai">
        <x-ui.icon name="file-text" class="size-3" /> {{ $template['cover'] ? 'Cover: '.$template['cover']['nama'] : 'Cover bawaan' }}
    </span>
@else
    <span {{ $attributes->class(['inline-flex max-w-full items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold tracking-normal text-stone-700 normal-case ring-1 ring-stone-200 ring-inset']) }}
          data-testid="kepala-template" data-kepala-lembar="kak" data-format="{{ $template['format']['kunci'] }}" data-template="{{ $template['kak']['id'] ?? '' }}"
          title="{{ $template['kak'] ? 'Unduhan Word memakai master “'.$template['kak']['nama'].'” — '.$template['kak']['label_asal'].($template['kak']['catatan'] ? '. '.$template['kak']['catatan'] : '') : 'Unduhan Word disusun dari susunan baku aplikasi' }}">
        <x-ui.icon name="layers" class="size-3 shrink-0" />
        <span class="truncate">{{ $template['format']['label'] }} · Word: {{ $template['kak']['nama'] ?? 'susunan baku' }}@if ($template['kak'] && $template['kak']['catatan']) <span class="font-normal text-stone-500">({{ $template['kak']['catatan'] }})</span>@endif</span>
    </span>
@endif
