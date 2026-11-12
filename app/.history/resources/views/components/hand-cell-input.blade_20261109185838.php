@props(['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'size' => 'normal'])

@php
  $initialValue = (float)($value ?? 0);
  $stepValue = (float)$step;
  $minValue = (float)$min;
  $maxValue = (float)$max;

  $isSmall = $size === 'small';
  $gapClass = $isSmall ? 'gap-0.5' : 'gap-1';
  $inputClass = $isSmall ? 'px-1 py-0.5 text-xs' : 'px-2 py-1 text-sm';
@endphp

<div x-data="{
    value: {{ $initialValue }},
    min: {{ $minValue }},
    max: {{ $maxValue }},
    step: {{ $stepValue }},
    increment() {
      this.value = Math.min(this.max, parseFloat((this.value + this.step).toFixed(4)));
      this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
    },
    decrement() {
      this.value = Math.max(this.min, parseFloat((this.value - this.step).toFixed(4)));
      this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }" class="flex items-center {{ $gapClass }}" style="min-width: 0;">
    <!-- Input -->
    <input
      x-ref="input"
      type="text"
      x-model.number="value"
      @input="value = isNaN(value) ? 0 : Math.min(max, Math.max(min, value))"
      @keydown.arrow-up.prevent="increment()"
      @keydown.arrow-down.prevent="decrement()"
      @blur="value = parseFloat(value.toFixed(4)); value = Math.max(min, Math.min(max, value)); $refs.input.dispatchEvent(new Event('change', { bubbles: true }));"
      style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff; width: 50px; flex: 0 0 auto;"
      class="rounded border text-center font-bold hover:border-slate-500 transition {{ $inputClass }}"
      placeholder="0"
    >

    <!-- Botones a la derecha -->
    <x-increment-decrement-buttons
      size="{{ $size }}"
      onIncrement="increment()"
      onDecrement="decrement()"
    />

    <!-- Input oculto para enviar el valor real -->
    <input
      type="hidden"
      name="{{ $name }}"
      x-model.number="value"
    >
  </div>
