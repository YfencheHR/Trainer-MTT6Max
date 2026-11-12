@props(['id' => 'confirmation-modal', 'title' => '¿Confirmar acción?', 'message' => '¿Estás seguro?', 'cancelText' => 'Cancelar', 'confirmText' => 'Confirmar', 'confirmColor' => 'ef4444', 'action' => null, 'method' => 'POST'])

@php
  $jsId = str_replace('-', '_', $id);
@endphp

<div x-data="{ showModal: false }" x-init="window['{{ $jsId }}'] = { show: () => { showModal = true }, hide: () => { showModal = false }, confirm: () => { showModal = false; setTimeout(() => document.getElementById('{{ $id }}-form')?.submit(), 100); } }" style="display: contents;">
  <!-- Modal backdrop -->
  <div x-show="showModal" @click="showModal = false" class="fixed inset-0 bg-black/50 z-40"></div>

  <!-- Modal -->
  <div x-show="showModal" class="fixed inset-0 flex items-center justify-center z-50">
    <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 max-w-md w-full mx-4 shadow-lg">
      <h2 class="text-xl font-bold text-white mb-2">{{ $title }}</h2>
      <p style="color: #9ca3af;" class="text-sm mb-6">{{ $message }}</p>

      <div class="flex gap-3 justify-end">
        <button
          @click="showModal = false"
          style="background-color: #6b7280;"
          class="hover:opacity-80 text-white px-4 py-2 rounded font-semibold transition"
          type="button"
        >
          {{ $cancelText }}
        </button>
        <button
          @click="confirm()"
          style="background-color: #{{ $confirmColor }};"
          class="hover:opacity-80 text-white px-4 py-2 rounded font-semibold transition"
          type="button"
        >
          {{ $confirmText }}
        </button>
      </div>
    </div>
  </div>

  <!-- Hidden form for submission -->
  <form id="{{ $id }}-form" method="POST" @if($action) action="{{ $action }}" @endif style="display: none;">
    @csrf
    @if($method === 'DELETE')
      @method('DELETE')
    @elseif($method !== 'POST')
      @method('{{ strtoupper($method) }}')
    @endif
  </form>
</div>
