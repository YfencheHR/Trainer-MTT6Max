@extends('app')

@section('content')
  <div style="max-width: 800px; margin: 0 auto;" class="px-2 sm:px-3 py-6">
    <h1 class="text-3xl font-bold mb-8" style="color: #d1d5db;">{{ __('made-hands.edit') }}</h1>

    @if ($errors->any())
      <div class="mb-6 p-4 rounded" style="background-color: #3a1f1f; border-left: 4px solid #ef4444;">
        <p style="color: #ef4444;" class="font-semibold">{{ __('common.validation_error') }}</p>
        <ul style="color: #fca5a5;" class="mt-2 list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('made-hands.update', $madeHand) }}" style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 space-y-6">
      @csrf
      @method('PUT')

      <!-- Name -->
      <div>
        <label for="name" style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.name') }} *</label>
        <input id="name" type="text" name="name" value="{{ $madeHand->name }}" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
      </div>

      <!-- Description -->
      <div>
        <label for="description" style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.description_label') }}</label>
        <textarea id="description" name="description" rows="3" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500">{{ $madeHand->description }}</textarea>
      </div>

      <!-- Value and Order -->
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label for="value" style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.value') }} *</label>
          <input id="value" type="number" name="value" value="{{ $madeHand->value }}" step="0.01" min="0" max="999.99" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
        </div>

        <div>
          <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.order') }} *</label>
          <input type="number" name="order" value="{{ $madeHand->order }}" min="0" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
        </div>

        <div>
          <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.combos') }}</label>
          <input type="number" name="combos" value="{{ $madeHand->combos }}" min="0" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500">
        </div>
      </div>

      <!-- Type -->
      <div>
        <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.type') }} *</label>
        <select name="type" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
          <option value="">{{ __('select') }}</option>
          <option value="pair" {{ $madeHand->type === 'pair' ? 'selected' : '' }}>{{ __('made-hands.types.pair') }}</option>
          <option value="two_pair" {{ $madeHand->type === 'two_pair' ? 'selected' : '' }}>{{ __('made-hands.types.two_pair') }}</option>
          <option value="set" {{ $madeHand->type === 'set' ? 'selected' : '' }}>{{ __('made-hands.types.set') }}</option>
          <option value="straight" {{ $madeHand->type === 'straight' ? 'selected' : '' }}>{{ __('made-hands.types.straight') }}</option>
          <option value="flush" {{ $madeHand->type === 'flush' ? 'selected' : '' }}>{{ __('made-hands.types.flush') }}</option>
          <option value="full_house" {{ $madeHand->type === 'full_house' ? 'selected' : '' }}>{{ __('made-hands.types.full_house') }}</option>
          <option value="quads" {{ $madeHand->type === 'quads' ? 'selected' : '' }}>{{ __('made-hands.types.quads') }}</option>
          <option value="draw_project" {{ $madeHand->type === 'draw_project' ? 'selected' : '' }}>{{ __('made-hands.types.draw_project') }}</option>
          <option value="high_card" {{ $madeHand->type === 'high_card' ? 'selected' : '' }}>{{ __('made-hands.types.high_card') }}</option>
        </select>
      </div>

      <!-- Visuals -->
      <div>
        <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.visuals') }}</label>
        <textarea name="visuals" rows="4" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff; font-family: monospace;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500">{{ json_encode($madeHand->visuals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
        <p style="color: #6b7280;" class="text-xs mt-2">{{ __('made-hands.visuals_format_hint') }}</p>
      </div>

      <!-- Checkboxes -->
      <div class="flex items-center gap-6">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="is_special" value="1" {{ $madeHand->is_special ? 'checked' : '' }} style="accent-color: #047857;">
          <span style="color: #a1a5b0;">{{ __('made-hands.is_special') }}</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="applies_to_river" value="1" {{ $madeHand->applies_to_river ? 'checked' : '' }} style="accent-color: #047857;">
          <span style="color: #a1a5b0;">{{ __('made-hands.applies_to_river') }}</span>
        </label>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit" class="px-6 py-3 rounded font-semibold text-white transition" style="background-color: #047857;">
          {{ __('update') }}
        </button>
        <a href="{{ route('made-hands.index') }}" class="px-6 py-3 rounded font-semibold text-white transition" style="background-color: #6b7280;">
          {{ __('cancel') }}
        </a>
      </div>
    </form>

  </div>
@endsection
