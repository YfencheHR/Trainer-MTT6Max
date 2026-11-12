@extends('layouts.app')

@section('content')
<div class="container mx-auto">
  <!-- Alpine.js debe estar cargado antes -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <!-- Importar servicios y controladores -->
  <script src="{{ asset('js/game-api.js') }}"></script>
  <script src="{{ asset('js/game-table.js') }}"></script>

  <!-- Mesa de Poker -->
  <x-poker-table :hand="$hand" :currentUser="auth()->user()" />
</div>
@endsection
