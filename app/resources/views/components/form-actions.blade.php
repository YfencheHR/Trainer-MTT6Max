@props(['submitText', 'submitColor', 'cancelUrl'])

<!-- Botones de Acción -->
<div class="flex gap-4 pt-8" style="border-top: 1px solid #2e3a48;">
  <button type="submit" style="background-color: {{ $submitColor }};" class="hover:opacity-80 text-white px-6 py-3 rounded font-semibold transition">
    {{ $submitText }}
  </button>
  <a href="{{ $cancelUrl }}" style="background-color: #6b7280;" class="hover:opacity-80 text-white px-6 py-3 rounded font-semibold transition">Cancelar</a>
</div>
