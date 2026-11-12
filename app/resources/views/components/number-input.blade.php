@props(['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'label' => ''])

@php
  $initialValue = (float)($value ?? 0);
  $stepValue = (float)$step;
  $minValue = (float)$min;
  $maxValue = (float)$max;
@endphp

<div class="flex flex-col min-w-0">
  <label for="{{ $name }}" class="mb-2">{{ $label }}</label>
  <div x-data="{
    value: {{ $initialValue }},
    min: {{ $minValue }},
    max: {{ $maxValue }},
    step: {{ $stepValue }},
    increment() {
      this.value = Math.min(this.max, parseFloat((this.value + this.step).toFixed(4)));
    },
    decrement() {
      this.value = Math.max(this.min, parseFloat((this.value - this.step).toFixed(4)));
    }
  }" class="flex items-center gap-1 sm:gap-2 min-w-0">
    <!-- Campo de entrada -->
    <input
        id="{{ $name }}"
        type="text"
        x-model.number="value"
        x-on:input="value = isNaN(value) ? 0 : Math.min(max, Math.max(min, value))"
        x-on:keydown.up.prevent="increment()"
        x-on:keydown.down.prevent="decrement()"
        x-on:blur="value = Math.max(min, Math.min(max, parseFloat(Number(value || 0).toFixed(4))))"
        style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;"
        class="min-w-0 flex-1 px-2 sm:px-4 py-3 rounded border text-center font-bold hover:border-slate-500 transition"
        placeholder="0"
    >

    <!-- Botones a la derecha -->
    <div class="flex gap-1">
      <!-- Botón Decrementar -->
      <button
        type="button"
        @click="decrement()"
        style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
        class="px-2 sm:px-3 py-3 rounded border hover:text-white hover:border-slate-500 transition text-lg font-semibold"
        title="Restar"
      >
        −
      </button>

      <!-- Botón Incrementar -->
      <button
        type="button"
        @click="increment()"
        style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
        class="px-2 sm:px-3 py-3 rounded border hover:text-white hover:border-slate-500 transition text-lg font-semibold"
        title="Sumar"
      >
        +
      </button>
    </div>

    <!-- Input oculto para enviar el valor real -->
    <input
      type="hidden"
      name="{{ $name }}"
      x-model.number="value"
    >
  </div>

  <!-- Información de rango -->
  <div style="color: #6b7280;" class="text-xs mt-2 text-left">
    Rango: {{ number_format($minValue, 2) }} - {{ number_format($maxValue, 2) }}
  </div>
</div>
