@props(['id', 'title', 'message', 'primaryBtnText' => 'Confirmar', 'secondaryBtnText' => 'Cancelar', 'primaryBtnColor' => 'ef4444', 'onConfirm' => null])

<div x-data="{ {{ $id }}: false }">
  <!-- Contenido del slot (botones y contenido) -->
  {{ $slot }}

  <!-- Modal Flotante -->
    <dialog
      x-show="{{ $id }}"
      @click.self="{{ $id }} = false"
      @keyup.escape.window="{{ $id }} = false"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50"
      style="display: none; z-index: 9999;"
      aria-modal="true"
      open>

    <div
        @click.stop
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="rounded-lg shadow-xl max-w-md w-full p-6 border"
        style="background-color: #1a212a; border-color: #2e3a48;">

      <h2 class="text-xl font-bold text-white mb-2">{{ $title }}</h2>
      <p class="text-sm text-gray-400 mb-6">{{ $message }}</p>

      <div class="flex justify-center gap-3">
        <button
            type="button"
            @click="{{ $id }} = false"
            class="text-white px-4 py-2 rounded font-semibold transition hover:opacity-80"
            style="background-color: #6b7280;">
          {{ $secondaryBtnText }}
        </button>
        <button
            type="button"
            @click="{{ $onConfirm ?? ($id . ' = false') }}"
            class="text-white px-4 py-2 rounded font-semibold transition hover:opacity-80"
            style="background-color: #{{ $primaryBtnColor }};">
          {{ $primaryBtnText }}
        </button>
      </div>
    </div>
  </dialog>
</div>
