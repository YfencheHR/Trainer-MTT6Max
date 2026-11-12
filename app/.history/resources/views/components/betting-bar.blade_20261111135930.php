@props([
    'label' => 'Apuesta',
    'min' => 0,
    'max' => 100,
    'step' => 0.1,
    'value' => 10,
    'unit' => 'bb'
])

<div>
  <label for="bettingSlider" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
    {{ $label }}
    <span style="color: #6b7280; font-size: 0.75rem;">{{ $unit }}</span>
  </label>

  <div style="display: flex; gap: 0.75rem; align-items: center;">
    <input type="range"
      id="bettingSlider"
      min="{{ $min }}"
      max="{{ $max }}"
      step="{{ $step }}"
      value="{{ $value }}"
      style="accent-color: #047857; flex: 1;"
      class="h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer"
    >
    <span id="bettingValue" style="color: #10b981; font-weight: bold; min-width: 70px; text-align: right; font-size: 1rem;">
      {{ $value }} {{ $unit }}
    </span>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const slider = document.getElementById('bettingSlider');
  const valueDisplay = document.getElementById('bettingValue');

  if (slider && valueDisplay) {
    slider.addEventListener('input', function() {
      valueDisplay.textContent = this.value + ' {{ $unit }}';
    });
  }
});
</script>
