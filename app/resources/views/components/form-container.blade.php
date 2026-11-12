@props(['method' => 'POST', 'action', 'title', 'description', 'submitText', 'submitColor' => '#3b82f6', 'cancelUrl'])

@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <x-form-header
      :title="$title"
      :description="$description"
      :cancelUrl="$cancelUrl"
    />

    <!-- Formulario -->
    <form method="{{ $method }}" action="{{ $action }}">
      @csrf
      @if($method !== 'POST')
        @method(strtoupper($method))
      @endif

      {{ $slot }}

      <!-- Botones de Acción -->
      <x-form-actions
        :submitText="$submitText"
        :submitColor="$submitColor"
        :cancelUrl="$cancelUrl"
      />
    </form>
  </main>
@endsection
