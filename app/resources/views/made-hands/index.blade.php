@extends('app')

@section('content')
  <div style="max-width: 1440px; margin: 0 auto;" class="px-2 sm:px-3 py-6">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold mb-2" style="color: #d1d5db;">{{ __('made-hands.title') }}</h1>
      <p style="color: #a1a5b0;" class="text-lg">{{ __('made-hands.description') }}</p>
    </div>

    <!-- Messages -->
    @if ($message = session('success'))
      <div class="mb-6 p-4 rounded" style="background-color: #1e3a1f; color: #10b981;">
        {{ $message }}
      </div>
    @endif

    <!-- Made Hands Section -->
    <div class="mb-12" style="padding-bottom: 50px;">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;">{{ __('made-hands.normal_hands') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($madeHandsGrouped['normal'] as $hand)
          <x-hand-card
            :hand="$hand"
            bgColor="#047857"
            hoverBorderColor="emerald-500"
            nameColor="#ffffff"
            valueColor="#10b981"
            buttonBgColor="#10b981"
            :isSpecial="false"
          />
        @empty
          <div class="col-span-full text-center text-slate-400 py-12">
            {{ __('no_data') }}
          </div>
        @endforelse
      </div>
    </div>

    <!-- Special Projects Section -->
    <div style="padding-bottom: 50px;">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;">{{ __('made-hands.special_hands') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($madeHandsGrouped['special'] as $hand)
          <x-hand-card
            :hand="$hand"
            bgColor="#9333ea"
            hoverBorderColor="purple-500"
            nameColor="#d8b4fe"
            valueColor="#f59e0b"
            buttonBgColor="#9333ea"
            :isSpecial="true"
          />
        @empty
          <div class="col-span-full text-center text-slate-400 py-12">
            {{ __('no_data') }}
          </div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
