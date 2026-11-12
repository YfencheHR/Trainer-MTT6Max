@extends('app')

@section('content')
  <div style="max-width: 800px; margin: 0 auto;" class="px-2 sm:px-3 py-6">
    <h1 class="text-3xl font-bold mb-8" style="color: #d1d5db;">{{ __('made-hands.create') }}</h1>

    @if ($errors->any())
      <div class="mb-6 p-4 rounded" style="background-color: #3a1f1f; border-left: 4px solid #ef4444;">
        <p style="color: #ef4444;" class="font-semibold">{{ __('validation_error') }}</p>
        <ul style="color: #fca5a5;" class="mt-2 list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('made-hands.store') }}" style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 space-y-6">
      @csrf

      <!-- Name -->
      <div>
        <label for="name" style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.name') }} *</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
      </div>

      <!-- Description -->
      <div>
        <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.description_label') }}</label>
        <textarea name="description" rows="3" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500"></textarea>
      </div>

      <!-- Value -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.value') }} *</label>
          <input type="number" name="value" value="{{ old('value') }}" step="0.01" min="0" max="999.99" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
        </div>

        <div>
          <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.order') }} *</label>
          <input type="number" name="order" value="{{ old('order', 0) }}" min="0" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
        </div>
      </div>

      <!-- Type -->
      <div>
        <label style="color: #a1a5b0;" class="block text-sm font-semibold mb-2">{{ __('made-hands.type') }} *</label>
        <select name="type" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-2 rounded border focus:outline-none focus:border-blue-500" required>
          <option value="">{{ __('select') }}</option>
          <option value="pair" {{ old('type') === 'pair' ? 'selected' : '' }}>{{ __('made-hands.types.pair') }}</option>
          <option value="two_pair" {{ old('type') === 'two_pair' ? 'selected' : '' }}>{{ __('made-hands.types.two_pair') }}</option>
          <option value="set" {{ old('type') === 'set' ? 'selected' : '' }}>{{ __('made-hands.types.set') }}</option>
          <option value="straight" {{ old('type') === 'straight' ? 'selected' : '' }}>{{ __('made-hands.types.straight') }}</option>
          <option value="flush" {{ old('type') === 'flush' ? 'selected' : '' }}>{{ __('made-hands.types.flush') }}</option>
          <option value="full_house" {{ old('type') === 'full_house' ? 'selected' : '' }}>{{ __('made-hands.types.full_house') }}</option>
          <option value="quads" {{ old('type') === 'quads' ? 'selected' : '' }}>{{ __('made-hands.types.quads') }}</option>
          <option value="draw_project" {{ old('type') === 'draw_project' ? 'selected' : '' }}>{{ __('made-hands.types.draw_project') }}</option>
        </select>
      </div>

      <!-- Checkboxes -->
      <div class="flex items-center gap-6">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="is_special" value="1" {{ old('is_special') ? 'checked' : '' }} style="accent-color: #047857;">
          <span style="color: #a1a5b0;">{{ __('made-hands.is_special') }}</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="applies_to_river" value="1" {{ old('applies_to_river', true) ? 'checked' : '' }} style="accent-color: #047857;">
          <span style="color: #a1a5b0;">{{ __('made-hands.applies_to_river') }}</span>
        </label>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit" class="px-6 py-3 rounded font-semibold text-white transition" style="background-color: #047857;">
          {{ __('save') }}
        </button>
        <a href="{{ route('made-hands.index') }}" class="px-6 py-3 rounded font-semibold text-white transition" style="background-color: #6b7280;">
          {{ __('cancel') }}
        </a>
      </div>
    </form>
  </div>
@endsection
