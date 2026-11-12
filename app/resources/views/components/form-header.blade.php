@props(['title', 'description', 'cancelUrl'])

<!-- Encabezado del Formulario -->
<div class="flex justify-between items-center mb-8">
  <div>
    <h1 class="text-4xl font-bold text-white mb-2">{{ $title }}</h1>
    <p style="color: #9ca3af;" class="text-lg">{{ $description }}</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ $cancelUrl }}" style="background-color: #6b7280;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition">Cancelar</a>
  </div>
</div>
