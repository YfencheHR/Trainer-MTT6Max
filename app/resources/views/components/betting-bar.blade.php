@props([
    'label' => 'Apuesta',
    'id' => 'bettingSlider',
    'min' => 0,
    'max' => 100,
    'step' => 0.1,
    'value' => 10,
    'unit' => 'bb',
    'model' => null,
    'valueExpression' => null,
    'class' => '',
])

<div {{ $attributes->only(['x-show', 'style']) }} class="{{ $class }}">
  <label for="{{ $id }}" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
    {{ $label }}
    <span style="color: #6b7280; font-size: 0.75rem;">{{ $unit }}</span>
  </label>

  <div style="display: flex; gap: 0.75rem; align-items: center;">
    <input type="range"
      id="{{ $id }}"
      min="{{ $min }}"
      max="{{ $max }}"
      step="{{ $step }}"
      value="{{ $value }}"
      style="accent-color: #047857; flex: 1;"
      class="h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer"
      @if($model) x-model.number="{{ $model }}" @endif
      {{ $attributes->except(['x-show', 'style', 'class']) }}
    >
    <span id="{{ $id }}Value" style="color: #10b981; font-weight: bold; min-width: 70px; text-align: right; font-size: 1rem;" @if($valueExpression) x-text="{{ $valueExpression }}" @endif>
      {{ $value }} {{ $unit }}
    </span>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const slider = document.getElementById(@json($id));
  const valueDisplay = document.getElementById(@json($id . 'Value'));

  if (slider && valueDisplay && !slider.hasAttribute('x-model') && !slider.hasAttribute('x-model.number')) {
    slider.addEventListener('input', function() {
      valueDisplay.textContent = this.value + ' {{ $unit }}';
    });
  }
});
</script>
