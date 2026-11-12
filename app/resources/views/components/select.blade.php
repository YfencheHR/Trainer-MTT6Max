@props([
    'name' => '',
    'id' => '',
    'label' => '',
    'options' => [],
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'bgColor' => '#0f1419',
    'borderColor' => '#2e3a48'
])

<div {{ $attributes->merge(['class' => 'mb-4']) }}>
  @if ($label)
    <label for="{{ $id }}" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
      {{ $label }}
    </label>
  @endif

  <select id="{{ $id }}"
    name="{{ $name }}"
    style="background-color: {{ $bgColor }}; border-color: {{ $borderColor }}; color: #ffffff;"
    class="w-full px-4 py-3 rounded border focus:outline-none focus:border-blue-500"
    @if($required) required @endif
    @if($disabled) disabled @endif
  >
    @if ($placeholder)
      <option value="">{{ $placeholder }}</option>
    @endif

    @forelse ($options as $optionValue => $optionLabel)
      <option value="{{ $optionValue }}" @if($value == $optionValue) selected @endif>
        {{ $optionLabel }}
      </option>
    @empty
      <option value="">{{ __('common.no_options') }}</option>
    @endforelse
  </select>

  @error($name)
    <p style="color: #ef4444;" class="text-xs mt-1">{{ $message }}</p>
  @enderror
</div>
