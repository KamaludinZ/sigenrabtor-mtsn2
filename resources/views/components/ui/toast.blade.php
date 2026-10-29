{{-- Notifikasi singkat. Picu dari Livewire: $this->dispatch('notify', message: '...', type: 'success|error|info|warning'[, tautan: ['label' => …, 'url' => …]]); dari JS: event `notify` (resources/js/unduhan.js `beritahu`). Galat dan notifikasi bertautan tampil 8 detik (galat sebagai role=alert); tautan = langkah lanjutan (`toast-tautan`, wire:navigate). --}}
<div x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random();
            const tautan = detail.tautan?.url ? detail.tautan : null;
            this.toasts.push({ id, message: detail.message, type: detail.type ?? 'success', tautan });
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), (detail.type ?? 'success') === 'error' || tautan ? 8000 : 4000);
        },
     }"
     @notify.window="push($event.detail)"
     @if (session('notify')) x-init="push(@js(session('notify')))" @endif
     aria-live="polite"
     class="print:hidden pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity x-bind:role="toast.type === 'error' ? 'alert' : 'status'" data-testid="toast" x-bind:data-jenis="toast.type"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-white p-3.5 shadow-lg ring-1 ring-stone-200">
            <span class="mt-0.5 size-2.5 shrink-0 rounded-full"
                  :class="{ 'bg-brand-500': toast.type === 'success', 'bg-red-500': toast.type === 'error', 'bg-sky-500': toast.type === 'info', 'bg-gold-500': toast.type === 'warning' }"></span>
            <div class="flex-1 text-sm text-stone-700">
                <p x-text="toast.message"></p>
                <template x-if="toast.tautan">
                    <a x-bind:href="toast.tautan.url" wire:navigate class="mt-1 inline-block font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-800" data-testid="toast-tautan" x-text="toast.tautan.label"></a>
                </template>
            </div>
            <button type="button" class="text-stone-400 hover:text-stone-600" @click="toasts = toasts.filter(t => t.id !== toast.id)">
                <span class="sr-only">Tutup</span>
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
