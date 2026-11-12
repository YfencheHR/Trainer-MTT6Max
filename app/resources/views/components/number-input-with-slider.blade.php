@props([
    'name' => '',
    'id' => '',
    'label' => '',
    'value' => '',
    'min' => '0',
    'max' => '100',
    'step' => '1',
    'required' => false,
    'suffix' => '',
    'class' => ''
])

<div {{ $attributes->merge(['class' => $class]) }}>
  <label for="{{ $id }}" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
    {{ $label }}
    @if ($suffix)
      <span style="color: #6b7280; font-size: 0.75rem;">{{ $suffix }}</span>
    @endif
  </label>

  <input type="number"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ $value }}"
    min="{{ $min }}"
    max="{{ $max }}"
    step="{{ $step }}"
    style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;"
    class="w-full px-3 py-2 rounded border focus:outline-none focus:border-blue-500 text-center text-sm mb-2 @if($required) required @endif"
    @if($required) required @endif
  >

  <input type="range"
    id="{{ $id }}Slider"
    min="{{ $min }}"
    max="{{ $max }}"
    step="{{ $step }}"
    value="{{ $value }}"
    style="accent-color: #047857;"
    class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer"
  >
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('#{{ $id }}');
    const slider = document.querySelector('#{{ $id }}Slider');

    if (input && slider) {
      input.addEventListener('input', function() {
        slider.value = this.value;
      });

      slider.addEventListener('input', function() {
        input.value = this.value;
      });
    }
  });
</script>
