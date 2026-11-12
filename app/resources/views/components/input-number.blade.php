@props([
    'name' => '',
    'id' => '',
    'label' => '',
    'value' => '',
    'min' => null,
    'max' => null,
    'step' => '1',
    'required' => false,
    'help' => '',
    'bgColor' => '#0f1419',
    'borderColor' => '#2e3a48'
])

<div {{ $attributes->merge(['class' => 'mb-4']) }}>
  @if ($label)
    <label for="{{ $id }}" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
      {{ $label }}
    </label>
  @endif

  <input type="number"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ $value }}"
    @if($min !== null) min="{{ $min }}" @endif
    @if($max !== null) max="{{ $max }}" @endif
    step="{{ $step }}"
    style="background-color: {{ $bgColor }}; border-color: {{ $borderColor }}; color: #ffffff;"
    class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500"
    @if($required) required @endif
  >

  @if ($help)
    <p style="color: #9ca3af;" class="text-xs mt-1">{{ $help }}</p>
  @endif

  @error($name)
    <p style="color: #ef4444;" class="text-xs mt-1">{{ $message }}</p>
  @enderror
</div>
